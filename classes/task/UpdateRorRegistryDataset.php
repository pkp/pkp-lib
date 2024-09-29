<?php
/**
 * @file classes/task/UpdateRorRegistryDataset.php
 *
 * Copyright (c) 2024 Simon Fraser University
 * Copyright (c) 2024 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class UpdateRorRegistryDataset
 *
 * @ingroup tasks
 *
 * @brief Class responsible to bi-weekly update of the Ror Registry tables in the database.
 */

/**
 * Mapping Ror data dump and OJS
 *
 *  0 id                rors.ror
 * 25 names.types.label ror_settings['locale' => 'en', 'setting_name' => 'name',           'setting_value' => 'name']
 * 27 ror_display_lang  ror_settings['locale' =>   '', 'setting_name' => 'default_locale', 'setting_value' => 'en']
 * 29 status            ror_settings['locale' =>   '', 'setting_name' => 'is_active',      'setting_value' => '1']
 *
 * | index | ror registry                            | ojs          |
 * |-------|-----------------------------------------|--------------|
 * | 0     | id                                      | rors.ror     |
 * | 1     | admin.created.date                      |              |
 * | 2     | admin.created.schema_version            |              |
 * | 3     | admin.last_modified.date                |              |
 * | 4     | admin.last_modified.schema_version      |              |
 * | 5     | domains                                 |              |
 * | 6     | established                             |              |
 * | 7     | external_ids.type.fundref.all           |              |
 * | 8     | external_ids.type.fundref.preferred     |              |
 * | 9     | external_ids.type.grid.all              |              |
 * | 10    | external_ids.type.grid.preferred        |              |
 * | 11    | external_ids.type.isni.all              |              |
 * | 12    | external_ids.type.isni.preferred        |              |
 * | 13    | external_ids.type.wikidata.all          |              |
 * | 14    | external_ids.type.wikidata.preferred    |              |
 * | 15    | links.type.website                      |              |
 * | 16    | links.type.wikipedia                    |              |
 * | 17    | locations.geonames_id                   |              |
 * | 18    | locations.geonames_details.country_code |              |
 * | 19    | locations.geonames_details.country_name |              |
 * | 20    | locations.geonames_details.lat          |              |
 * | 21    | locations.geonames_details.lng          |              |
 * | 22    | locations.geonames_details.name         |              |
 * | 23    | names.types.acronym                     |              |
 * | 24    | names.types.alias                       |              |
 * | 25    | names.types.label                       | ror_settings |
 * | 26    | names.types.ror_display                 | ror_settings |
 * | 27    | ror_display_lang                        | ror_settings |
 * | 28    | relationships                           |              |
 * | 29    | status                                  | ror_settings |
 */

namespace PKP\task;

use APP\core\Application;
use DirectoryIterator;
use Exception;
use GuzzleHttp\Exception\GuzzleException;
use PKP\facades\Repo;
use PKP\file\PrivateFileManager;
use PKP\scheduledTask\ScheduledTask;
use PKP\scheduledTask\ScheduledTaskHelper;
use ZipArchive;

class UpdateRorRegistryDataset extends ScheduledTask
{
    /** @var PrivateFileManager */
    private PrivateFileManager $fileManager;

    /** @var string API Url of the data dump versions. */
    private string $urlVersions = 'https://zenodo.org/api/communities/ror-data/records?q=&sort=newest';

    /** @var string The file contains the following text in the name. */
    private string $csvNameContains = 'ror-data_schema_v2.csv';

    /** @var string The prefix used for the temporary zip file and the extracted directory. */
    private string $prefix = 'TemporaryRorRegistryCache';

    /** @var array|int[] Mappings database vs CSV */
    private array $dataMapping = [
        'ror' => 0,
        'displayLocale' => 27,
        'isActive' => 29,
        'names' => 25
    ];

    /** @var string No language code available key in registry */
    private string $noLocale = 'no_lang_code';

    /** @var string Mapping OJS for no language code available in registry */
    private string $noLocaleMapping = 'en';

    /** @copydoc ScheduledTask::getName() */
    public function getName(): string
    {
        return __('admin.scheduledTask.UpdateRorRegistryDataset');
    }

    /** @copydoc ScheduledTask::executeActions() */
    public function executeActions(): bool
    {
        $this->fileManager = new PrivateFileManager();
        $pathZipFile = $this->fileManager->getBasePath() . DIRECTORY_SEPARATOR . $this->prefix . '.zip';
        $pathZipDir = $this->fileManager->getBasePath() . DIRECTORY_SEPARATOR . $this->prefix;
        $downloadUrl = '';
        $pathCsv = '';

        try {
            // cleanup
            $this->cleanup([$pathZipFile, $pathZipDir]);

            $client = Application::get()->getHttpClient();

            // get url of the latest version
            $response = $client->request('GET', $this->urlVersions);
            $responseArray = json_decode($response->getBody(), true);
            if ($response->getStatusCode() !== 200 || json_last_error() !== JSON_ERROR_NONE || empty($responseArray)) {
                return '';
            }
            if (!empty($responseArray['hits']['hits'][0]['files'][0]['links']['self'])) {
                $downloadUrl = $responseArray['hits']['hits'][0]['files'][0]['links']['self'];
            }
            if (empty($downloadUrl)) return false;

            // download file
            $client->request('GET', $downloadUrl, ['sink' => $pathZipFile]);
            if (!$this->fileManager->fileExists($pathZipFile)) return false;

            // extract file
            $zip = new ZipArchive();
            if ($zip->open($pathZipFile) === true) {
                $zip->extractTo($pathZipDir);
                $zip->close();
            }
            if (!$this->fileManager->fileExists($pathZipDir, 'dir')) return false;

            // find csv file
            $iterator = new DirectoryIterator($pathZipDir);
            foreach ($iterator as $fileinfo) {
                if (!$fileinfo->isDot()) {
                    if (str_contains($fileinfo->getFilename(), $this->csvNameContains)) {
                        $pathCsv = $fileinfo->getPathname();
                        break;
                    }
                }
            }
            if (!$this->fileManager->fileExists($pathCsv)) return false;

            // process csv file
            $i = 1;
            if (($handle = fopen($pathCsv, 'r')) !== false) {
                while (($row = fgetcsv($handle, 0)) !== false) {
                    if ($i > 1) $this->processRow($row);
                    $i++;
                }
                fclose($handle);
            }

            // cleanup
            $this->cleanup([$pathZipFile, $pathZipDir]);

            return true;

        } catch (GuzzleException|Exception $e) {
            $this->addExecutionLogEntry(
                $e->getMessage(),
                ScheduledTaskHelper::SCHEDULED_TASK_MESSAGE_TYPE_ERROR
            );

            return false;
        }
    }

    /**
     * Process row and update database
     *
     * @param array $row
     */
    private function processRow(array $row): void
    {
        $params = [];

        // ror < id
        $params['ror'] = $row[$this->dataMapping['ror']];

        // display_locale : ror_display_lang
        $params['displayLocale'] = str_replace(
            $this->noLocale,
            $this->noLocaleMapping,
            $row[$this->dataMapping['displayLocale']]
        );

        // is_active < status
        $params['isActive'] = 0;
        if (strtolower($row[$this->dataMapping['isActive']]) === 'active') {
            $params['isActive'] = 1;
        }

        // locale, name < names.types.label
        // [["name"]["en"] => "label1"],["name"]["it"] => "label2"]] < "en: label1; it: label2"
        if (!empty($row[$this->dataMapping['names']])) {
            $names = array_map('trim', explode(';', $row[$this->dataMapping['names']]));
            for ($i = 0; $i < count($names); $i++) {
                $name = array_map('trim', explode(':', $names[$i]));
                if (count($name) === 2) {
                    $name[0] = str_replace($this->noLocale, $this->noLocaleMapping, $name[0]);
                    $params['name'][$name[0]] = trim($name[1]);
                }
            }
        }

        Repo::ror()->updateOrInsert(Repo::ror()->newDataObject($params));
    }

    /**
     * Cleanup temporary files and directories.
     *
     * @param array $paths
     * @return void
     */
    private function cleanup(array $paths = []): void
    {
        foreach ($paths as $path) {
            if ($this->fileManager->fileExists($path, 'dir')) {
                foreach (new DirectoryIterator($path) as $fileInfo) {
                    if (!$fileInfo->isDot()) {
                        $this->fileManager->deleteByPath($fileInfo->getPathname());
                    }
                }
                $this->fileManager->rmdir($path);
            } elseif ($this->fileManager->fileExists($path)) {
                $this->fileManager->deleteByPath($path);
            }
        }
    }
}

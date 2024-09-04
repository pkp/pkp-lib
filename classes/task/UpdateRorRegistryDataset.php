<?php
/**
 * @file classes/task/UpdateRorRegistryDataset.php
 *
 * Copyright (c) 2022 Simon Fraser University
 * Copyright (c) 2022 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class UpdateRorRegistryDataset
 *
 * @ingroup tasks
 *
 * @brief Class responsible to bi-weekly update of the DB-IP city lite database used for Geo statistics.
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
    private string $prefix = 'TemporaryRorCache';

    /** @var array|int[] Mappings database vs CSV */
    private array $mapping = [
        'ror' => 0,
        'displayLocale' => 27,
        'isActive' => 29,
        'names' => 25
    ];

    /** @var string No language found in ror registry */
    private string $defaultLanguageIn = 'no_lang_code';

    /** @var string Replace no language found with this language code */
    private string $defaultLanguageOut = 'en';

    /**
     * @copydoc ScheduledTask::getName()
     */
    public function getName(): string
    {
        return 'Update Ror registry dataset cache';
    }

    /**
     * @copydoc ScheduledTask::executeActions()
     */
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
            $responseUrl = $client->request('GET', $this->urlVersions);
            $responseUrlA = json_decode($responseUrl->getBody(), true);
            if ($responseUrl->getStatusCode() !== 200 || empty($responseUrlA) || json_last_error() !== JSON_ERROR_NONE) {
                $this->addExecutionLogEntry(
                    'Update Ror registry dataset > url latest version not found.',
                    ScheduledTaskHelper::SCHEDULED_TASK_MESSAGE_TYPE_ERROR);
                return false;
            }
            if (!empty($responseUrlA['hits']['hits'][0]['files'][0]['links']['self'])) {
                $downloadUrl = $responseUrlA['hits']['hits'][0]['files'][0]['links']['self'];
            }

            // download file
            $responseFile = $client->request('GET', $downloadUrl, ['sink' => $pathZipFile]);
            if ($responseFile->getStatusCode() !== 200) {
                $this->addExecutionLogEntry(
                    'Update Ror registry dataset > error downloading zip file.',
                    ScheduledTaskHelper::SCHEDULED_TASK_MESSAGE_TYPE_ERROR
                );
                return false;
            }

            // extract file
            if (!$this->fileManager->fileExists($pathZipFile)) return false;
            $zip = new ZipArchive();
            if ($zip->open($pathZipFile) === true) {
                $zip->extractTo($pathZipDir);
                $zip->close();
            }

            // check if extracted directory exists, return false if not
            if (!$this->fileManager->fileExists($pathZipDir, 'dir')) {
                $this->addExecutionLogEntry(
                    'Update Ror registry dataset > errors extracting file.',
                    ScheduledTaskHelper::SCHEDULED_TASK_MESSAGE_TYPE_ERROR
                );
                return false;
            }

            // get path of csv file
            $iterator = new DirectoryIterator($pathZipDir);
            foreach ($iterator as $fileinfo) {
                if (!$fileinfo->isDot()) {
                    if (str_contains($fileinfo->getFilename(), $this->csvNameContains)) {
                        $pathCsv = $fileinfo->getPathname();
                        break;
                    }
                }
            }

            // check if csv file exists, return false if not
            if (!$this->fileManager->fileExists($pathCsv)) {
                $this->addExecutionLogEntry(
                    'Update Ror registry dataset > csv file not found.',
                    ScheduledTaskHelper::SCHEDULED_TASK_MESSAGE_TYPE_ERROR
                );
                return false;
            }

            // process csv file
            $i = 1;
            if (($handle = fopen($pathCsv, 'r')) !== false) {
                while (($row = fgetcsv($handle, 0)) !== false) {
                    if ($i > 1) $this->processRow($row);
                    $i++;
                }
                fclose($handle);
            }
        } catch (Exception $e) {
            $this->addExecutionLogEntry(
                $e->getMessage(),
                ScheduledTaskHelper::SCHEDULED_TASK_MESSAGE_TYPE_ERROR
            );
            return false;
        }

        $this->addExecutionLogEntry(
            'Update Ror registry dataset > updated successfully.',
            ScheduledTaskHelper::SCHEDULED_TASK_MESSAGE_TYPE_COMPLETED
        );

        // cleanup
        $this->cleanup([$pathZipFile, $pathZipDir]);

        return true;
    }

    /**
     * Process row and update database
     *
     * @param array $row
     */
    private function processRow(array $row): void
    {
        $params = [];

        // id > ror
        $params['ror'] = $row[$this->mapping['ror']];

        // ror_display_lang
        $params['displayLocale'] =
            str_replace($this->defaultLanguageIn, $this->defaultLanguageOut, $row[$this->mapping['displayLocale']]);

        // is_active
        $params['isActive'] = 0;
        if (strtolower($row[$this->mapping['isActive']]) === strtolower('active')) {
            $params['isActive'] = 1;
        }

        // names.types.label
        // "en: label1; it: label2" => [["name"]["en"] => "label1"],["name"]["it" => "label2"]]
        if (!empty($row[$this->mapping['names']])) {
            $tmp1 = array_map('trim', explode(';', $row[$this->mapping['names']]));
            for ($i = 0; $i < count($tmp1); $i++) {
                $tmp2 = array_map('trim', explode(':', $tmp1[$i]));
                if (count($tmp2) === 2) {
                    $tmp2[0] = str_replace($this->defaultLanguageIn, $this->defaultLanguageOut, $tmp2[0]);
                    $params['name'][$tmp2[0]] = trim($tmp2[1]);
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
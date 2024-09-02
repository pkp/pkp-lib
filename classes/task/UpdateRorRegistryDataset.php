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
 * | index | ror                                     | ojs                                             |
 * |-------|-----------------------------------------|-------------------------------------------------|
 * | 0     | id                                      | rors.ror                                        |
 * | 1     | admin.created.date                      |                                                 |
 * | 2     | admin.created.schema_version            |                                                 |
 * | 3     | admin.last_modified.date                |                                                 |
 * | 4     | admin.last_modified.schema_version      |                                                 |
 * | 5     | domains                                 |                                                 |
 * | 6     | established                             |                                                 |
 * | 7     | external_ids.type.fundref.all           |                                                 |
 * | 8     | external_ids.type.fundref.preferred     |                                                 |
 * | 9     | external_ids.type.grid.all              |                                                 |
 * | 10    | external_ids.type.grid.preferred        |                                                 |
 * | 11    | external_ids.type.isni.all              |                                                 |
 * | 12    | external_ids.type.isni.preferred        |                                                 |
 * | 13    | external_ids.type.wikidata.all          |                                                 |
 * | 14    | external_ids.type.wikidata.preferred    |                                                 |
 * | 15    | links.type.website                      |                                                 |
 * | 16    | links.type.wikipedia                    |                                                 |
 * | 17    | locations.geonames_id                   |                                                 |
 * | 18    | locations.geonames_details.country_code |                                                 |
 * | 19    | locations.geonames_details.country_name |                                                 |
 * | 20    | locations.geonames_details.lat          |                                                 |
 * | 21    | locations.geonames_details.lng          |                                                 |
 * | 22    | locations.geonames_details.name         |                                                 |
 * | 23    | names.types.acronym                     |                                                 |
 * | 24    | names.types.alias                       |                                                 |
 * | 25    | names.types.label                       | ror_settings[locale,setting_name,setting_value] |
 * | 26    | names.types.ror_display                 | rors.name                                       |
 * | 27    | ror_display_lang                        | rors.locale                                     |
 * | 28    | relationships                           |                                                 |
 * | 29    | status                                  | rors.status                                     |
 */

namespace PKP\task;

use APP\core\Application;
use DirectoryIterator;
use Exception;
use PKP\facades\Repo;
use PKP\file\PrivateFileManager;
use PKP\ror\Ror;
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
        'ror_id' => 0,
        'default_name' => 26,
        'default_locale' => 27,
        'alternate_names' => 25,
        'status' => 29
    ];

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

            // get url to the latest version
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
            $response = $client->request('GET', $downloadUrl, ['sink' => $pathZipFile]);
            if ($responseUrl->getStatusCode() !== 200) {
                $this->addExecutionLogEntry(
                    'Update Ror registry dataset > error downloading zip file.',
                    ScheduledTaskHelper::SCHEDULED_TASK_MESSAGE_TYPE_ERROR
                );
                return false;
            }
            // extract file
            $zip = new ZipArchive();
            if ($zip->open($pathZipFile) === true) {
                $zip->extractTo($pathZipDir);
                $zip->close();
            }
            if (!$this->fileManager->fileExists($pathZipDir, 'dir')) {
                $this->addExecutionLogEntry(
                    'Update Ror registry dataset > errors extracting file.',
                    ScheduledTaskHelper::SCHEDULED_TASK_MESSAGE_TYPE_ERROR
                );
                return false;
            }

            // path of csv file
            $iterator = new DirectoryIterator($pathZipDir);
            foreach ($iterator as $fileinfo) {
                if (!$fileinfo->isDot()) {
                    if (str_contains($fileinfo->getFilename(), $this->csvNameContains)) {
                        $pathCsv = $fileinfo->getPathname();
                        break;
                    }
                }
            }

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

        return true;
    }

    /**
     * Process row and update database
     *
     * @param array $row
     * @return Ror
     */
    private function processRow(array $row): void
    {
        $params = [];

        // id > ror
        $params['ror_id'] = $row[$this->mapping['ror_id']];

        // names.types.ror_display > name
        $params['default_name'] = $row[$this->mapping['default_name']];

        // ror_display_lang
        $params['default_locale'] =
            str_replace('no_lang_code', 'en', $row[$this->mapping['default_locale']]);

        // names.types.label
        // "en: label1; it: label2" > [["en" => "label1"],["it" => "label2"]]
        $alternateNames = [];
        if (!empty($row[$this->mapping['alternate_names']])) {
            $tmp1 = array_map('trim', explode(';', $row[$this->mapping['alternate_names']]));
            for ($i = 0; $i < count($tmp1); $i++) {
                $tmp2 = array_map('trim', explode(':', $tmp1[$i]));
                if (count($tmp2) === 2) {
                    $tmp2[0] = str_replace('no_lang_code', 'en', $tmp2[0]);
                    $alternateNames[] = [$tmp2[0] => trim($tmp2[1])];
                }
            }
        }
        $params['alternate_names'] = json_encode($alternateNames);

        // status
        $params['status'] = 0;
        if (strtolower($row[$this->mapping['status']]) === strtolower('active')) $params['status'] = 1;

        Repo::ror()->newDataObject($params);
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
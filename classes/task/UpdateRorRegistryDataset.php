<?php

/**
 * @file classes/task/UpdateRorRegistryDataset.php
 *
 * Copyright (c) 2025 Simon Fraser University
 * Copyright (c) 2025 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class UpdateRorRegistryDataset
 *
 * @brief Class responsible to bi-weekly update of the Ror Registry tables in the database.
 */

/**
 * Mapping Ror data dump and OJS
 *
 *  0 id                      rors.ror
 * 29 names.types.label       ror_settings['locale' => 'en', 'setting_name' => 'name',           'setting_value' => 'name']
 * 30 names.types.ror_display
 * 31 ror_display_lang        ror_settings['locale' =>   '', 'setting_name' => 'default_locale', 'setting_value' => 'en']
 * 33 status                  ror_settings['locale' =>   '', 'setting_name' => 'is_active',      'setting_value' => '1']
 *
 * | index | ror registry                                        | ojs          |
 * |-------|-----------------------------------------------------|--------------|
 * | 0     | id                                                  | rors.ror     |
 * | 1     | admin.created.date                                  |              |
 * | 2     | admin.created.schema_version                        |              |
 * | 3     | admin.last_modified.date                            |              |
 * | 4     | admin.last_modified.schema_version                  |              |
 * | 5     | domains                                             |              |
 * | 6     | established                                         |              |
 * | 7     | external_ids.type.fundref.all                       |              |
 * | 8     | external_ids.type.fundref.preferred                 |              |
 * | 9     | external_ids.type.grid.all                          |              |
 * | 10    | external_ids.type.grid.preferred                    |              |
 * | 11    | external_ids.type.isni.all                          |              |
 * | 12    | external_ids.type.isni.preferred                    |              |
 * | 13    | external_ids.type.wikidata.all                      |              |
 * | 14    | external_ids.type.wikidata.preferred                |              |
 * | 15    | links.type.website                                  |              |
 * | 16    | links.type.wikipedia                                |              |
 * | 17    | locations.geonames_id                               |              |
 * | 18    | locations.geonames_details.continent_code           |              |
 * | 19    | locations.geonames_details.continent_name           |              |
 * | 20    | locations.geonames_details.country_code             |              |
 * | 21    | locations.geonames_details.country_name             |              |
 * | 22    | locations.geonames_details.country_subdivision_code |              |
 * | 23    | locations.geonames_details.country_subdivision_name |              |
 * | 24    | locations.geonames_details.lat                      |              |
 * | 25    | locations.geonames_details.lng                      |              |
 * | 26    | locations.geonames_details.name                     |              |
 * | 27    | names.types.acronym                                 |              |
 * | 28    | names.types.alias                                   |              |
 * | 29    | names.types.label                                   | ror_settings |
 * | 30    | names.types.ror_display                             | ror_settings |
 * | 31    | ror_display_lang                                    | ror_settings |
 * | 32    | relationships                                       |              |
 * | 33    | status                                              | ror_settings |
 * | 34    | types                                               |              |
 */

namespace PKP\task;

use Throwable;
use Exception;
use APP\core\Application;
use DirectoryIterator;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Facades\Bus;
use PKP\file\PrivateFileManager;
use PKP\jobs\ror\DownloadRoRDatasetInSync;
use PKP\jobs\ror\DownloadAndExtractRorDataset;
use PKP\jobs\ror\ImportRorData;
use PKP\scheduledTask\ScheduledTask;
use PKP\scheduledTask\ScheduledTaskHelper;

class UpdateRorRegistryDataset extends ScheduledTask
{
    public const CACHE_KEY_CHUNK_BATCH = 'ror:chunk:batch:processing';

    /** @var string API Url of the data dump versions. */
    private string $urlVersions = 'https://zenodo.org/api/communities/ror-data/records?q=&sort=newest';

    /** @var string The file contains the following text in the name. */
    private string $csvNameContains = 'ror-data_schema_v2.csv';

    /** @var string The prefix used for the temporary zip file and the extracted directory. */
    private string $prefix = 'TemporaryRorRegistryCache';

    /** @var array|string[] Mappings database vs CSV */
    private array $dataMapping = [
        'ror' => 'id',
        'displayLocale' => 'ror_display_lang',
        'displayName' => 'names.types.ror_display',
        'isActive' => 'status',
        'names' => 'names.types.label'
    ];

    /** @var array|int[] Indexes of mappings database vs CSV */
    private array $dataMappingIndex = [];

    /** @var string No language code available key in registry */
    private string $noLocale = 'no_lang_code';

    /** @var string Name of temporary table */
    private string $temporaryTable = 'rors_temporary';

    /**
     * @copydoc ScheduledTask::getName()
     */
    public function getName(): string
    {
        return __('admin.scheduledTask.UpdateRorRegistryDataset');
    }

    /**
     * @copydoc ScheduledTask::executeActions()
     */
    public function executeActions(): bool
    {
        $downloadUrl = $this->getDownloadUrl();
        if (empty($downloadUrl)) {
            static::writeToExecutionLogFile(
                'Failed to get download URL',
                $this->getExecutionLogFile(),
                ScheduledTaskHelper::SCHEDULED_TASK_MESSAGE_TYPE_ERROR
            );
            return false;
        }

        try {
            $fileManager = new PrivateFileManager();
            $pathZipDir = $fileManager->getBasePath() . DIRECTORY_SEPARATOR . $this->prefix;
            $pathZipFile = $pathZipDir . '.zip';
            $logfile = $this->getExecutionLogFile();

            // Following jobs will be dispatched in chain and process is as follows :-
            //  - Download RoR dataset in chunks, build final zip file and extract it
            //  - kick off the sync download approach anyway and let it determine 
            //  - Import RoR dataset in chunks by parsing the CSV file
            Bus::chain([
                new DownloadAndExtractRorDataset(
                    $downloadUrl,
                    $this->csvNameContains,
                    $this->prefix,
                    $logfile
                ),
                new DownloadRoRDatasetInSync(
                    $this->csvNameContains,
                    $downloadUrl, 
                    $pathZipFile, 
                    $pathZipDir, 
                    $logfile
                ),
                new ImportRorData(
                    $this->prefix,
                    $this->csvNameContains,
                    $this->dataMapping,
                    $this->dataMappingIndex,
                    $this->noLocale,
                    $this->temporaryTable,
                    $logfile
                ),
            ])
            ->catch(function (Throwable $e) use ($logfile) {
                static::writeToExecutionLogFile(
                    "Error occurred while processing ROR dataset with message: {$e->getMessage()}",
                    $logfile,
                    ScheduledTaskHelper::SCHEDULED_TASK_MESSAGE_TYPE_ERROR
                );
            })
            ->dispatch();

            return true;
        } catch (Throwable $e) {
            static::writeToExecutionLogFile(
                $e->getMessage(),
                $this->getExecutionLogFile(),
                ScheduledTaskHelper::SCHEDULED_TASK_MESSAGE_TYPE_ERROR
            );
            return false;
        }
    }

    /**
     * generate and get the ROR dataset download url
     */
    protected function getDownloadUrl(): string
    {
        try {
            $client = Application::get()->getHttpClient();
            $response = $client->request('GET', $this->urlVersions, [
                'connect_timeout' => 10
            ]);

            $responseArray = json_decode($response->getBody(), true);

            if ($response->getStatusCode() !== 200
                || json_last_error() !== JSON_ERROR_NONE
                || empty($responseArray)
            ) {
                static::writeToExecutionLogFile(
                    'Invalid response from Zenodo API',
                    $this->getExecutionLogFile(),
                    ScheduledTaskHelper::SCHEDULED_TASK_MESSAGE_TYPE_ERROR
                );
                return '';
            }

            return $responseArray['hits']['hits'][0]['files'][0]['links']['self'] ?? '';
        } catch (GuzzleException|Exception $e) {
            static::writeToExecutionLogFile(
                $e->getMessage(),
                $this->getExecutionLogFile(),
                ScheduledTaskHelper::SCHEDULED_TASK_MESSAGE_TYPE_ERROR
            );
            return '';
        }
    }

    /**
     * Get the path to the CSV file
     */
    public static function getPathCsv(string $pathZipDir, string $csvNameContains): string
    {
        $iterator = new DirectoryIterator($pathZipDir);

        foreach ($iterator as $fileInfo) {
            if (!$fileInfo->isDot() && str_contains($fileInfo->getFilename(), $csvNameContains)) {
                return $fileInfo->getPathname();
            }
        }

        return '';
    }

    /**
     * Clean up the temporary files and directories.
     */
    public static function cleanup(array $paths = []): void
    {
        $fileManager = new PrivateFileManager();
        
        foreach ($paths as $path) {
            if ($fileManager->fileExists($path, 'dir')) {
                foreach (new DirectoryIterator($path) as $fileInfo) {
                    if (!$fileInfo->isDot()) {
                        $fileManager->deleteByPath($fileInfo->getPathname());
                    }
                }
                $fileManager->rmdir($path);
            } elseif ($fileManager->fileExists($path)) {
                $fileManager->deleteByPath($path);
            }
        }
    }
}

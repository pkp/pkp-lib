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
 * @ingroup tasks
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

use APP\core\Application;
use DirectoryIterator;
use Exception;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PKP\file\PrivateFileManager;
use PKP\scheduledTask\ScheduledTask;
use PKP\scheduledTask\ScheduledTaskHelper;
use ZipArchive;

class UpdateRorRegistryDataset extends ScheduledTask
{
    private PrivateFileManager $fileManager;

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

    /** @copydoc ScheduledTask::getName() */
    public function getName(): string
    {
        return __('admin.scheduledTask.UpdateRorRegistryDataset');
    }

    /** @copydoc ScheduledTask::executeActions() */
    public function executeActions(): bool
    {
        $this->fileManager = new PrivateFileManager();
        $pathZipDir = $this->fileManager->getBasePath() . DIRECTORY_SEPARATOR . $this->prefix;
        $pathZipFile = $pathZipDir . '.zip';

        try {
            $this->dropTemporaryTable();
            $this->cleanup([$pathZipFile, $pathZipDir]);
            $this->createTemporaryTable();

            $downloadUrl = $this->getDownloadUrl();
            if (empty($downloadUrl)) {
                return false;
            }

            if (!$this->downloadAndExtract($downloadUrl, $pathZipFile, $pathZipDir)) {
                return false;
            }

            $pathCsv = $this->getPathCsv($pathZipDir);
            if (empty($pathCsv || !$this->fileManager->fileExists($pathCsv))) {
                return false;
            }

            $this->processCsv($pathCsv);

            return true;
        } catch (Exception $e) {
            $this->addExecutionLogEntry(
                $e->getMessage(),
                ScheduledTaskHelper::SCHEDULED_TASK_MESSAGE_TYPE_ERROR
            );

            return false;
        } finally {
            $this->dropTemporaryTable();
            $this->cleanup([$pathZipFile, $pathZipDir]);
        }
    }

    /**
     * Get download url
     */
    private function getDownloadUrl(): string
    {
        try {
            $client = Application::get()->getHttpClient();

            $response = $client->request('GET', $this->urlVersions);
            $responseArray = json_decode($response->getBody(), true);

            if ($response->getStatusCode() !== 200 || json_last_error() !== JSON_ERROR_NONE || empty($responseArray)) {
                return '';
            }

            if (!empty($responseArray['hits']['hits'][0]['files'][0]['links']['self'])) {
                return $responseArray['hits']['hits'][0]['files'][0]['links']['self'];
            }

            return '';
        } catch (GuzzleException|Exception $e) {
            $this->addExecutionLogEntry(
                $e->getMessage(),
                ScheduledTaskHelper::SCHEDULED_TASK_MESSAGE_TYPE_ERROR
            );

            return '';
        }
    }

    /**
     * Download and extract zip file
     */
    private function downloadAndExtract(string $downloadUrl, string $pathZipFile, string $pathZipDir): bool
    {
        try {
            $client = Application::get()->getHttpClient();

            // download file
            $client->request('GET', $downloadUrl, ['sink' => $pathZipFile]);
            if (!$this->fileManager->fileExists($pathZipFile)) {
                return false;
            }

            // extract file
            $zip = new ZipArchive();
            if ($zip->open($pathZipFile) === true) {
                $zip->extractTo($pathZipDir);
                $zip->close();
            }
            if (!$this->fileManager->fileExists($pathZipDir, 'dir')) {
                return false;
            }

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
     * Get path to csv file
     */
    private function getPathCsv(string $pathZipDir): string
    {
        $iterator = new DirectoryIterator($pathZipDir);
        foreach ($iterator as $fileInfo) {
            if (!$fileInfo->isDot()) {
                if (str_contains($fileInfo->getFilename(), $this->csvNameContains)) {
                    return $fileInfo->getPathname();
                }
            }
        }

        return '';
    }

    /**
     * Process Csv file
     */
    private function processCsv(string $pathCsv): void
    {
        $i = 0;
        $isHeader = true;
        $batchCounter = 0;
        $batchSize = 5000;
        $batchRows = [];
        if (($handle = fopen($pathCsv, 'r')) !== false) {
            while (($rowCsv = fgetcsv($handle, null, ',', '"', '\\')) !== false) {
                if ($isHeader) {
                    foreach ($this->dataMapping as $keyDB => $keyCsv) {
                        $this->dataMappingIndex[$keyDB] = array_search($keyCsv, $rowCsv, true);
                    }
                    $isHeader = false;
                } else {
                    $batchRows[] = $this->processRow($rowCsv);

                    if ($batchCounter === $batchSize) {
                        $this->processBatch($batchRows);
                        $batchCounter = 0;
                        $batchRows = [];
                    }
                }
                $i++;
                $batchCounter++;
            }
            fclose($handle);

            // insert / update last batch
            if ($batchCounter > 0) {
                $this->processBatch($batchRows);
            }
        }
    }

    /**
     * Process row
     */
    private function processRow(array $row): array
    {
        // ror < id
        $ror = $row[$this->dataMappingIndex['ror']];

        // display_locale : ror_display_lang
        $displayLocale = (!empty($row[$this->dataMappingIndex['displayLocale']]))
            ? $row[$this->dataMappingIndex['displayLocale']]
            : $this->noLocale;

        // is_active < status
        $isActive = (strtolower($row[$this->dataMappingIndex['isActive']]) === 'active') ? 1 : 0;

        // search_phrase
        $searchPhrase = $ror;

        // locale, name < names.types.label
        // [["name"]["en"] => "label1"],["name"]["it"] => "label2"]] < "en: label1; it: label2"
        $namesIn = $row[$this->dataMappingIndex['names']];
        $namesOut = [];
        if (!empty($namesIn)) {
            $names = array_map('trim', explode(';', $namesIn));
            for ($i = 0; $i < count($names); $i++) {
                $name = array_map('trim', explode(':', $names[$i]));
                if (count($name) === 2) {
                    $namesOut[$name[0]] = trim($name[1]);
                    $searchPhrase .= ' ' . $namesOut[$name[0]];
                }
            }
        }

        if (empty($namesOut[$displayLocale])) {
            $namesOut[$displayLocale] = $row[$this->dataMappingIndex['displayName']];
        }

        return [
            'ror' => $ror,
            'displayLocale' => $displayLocale,
            'isActive' => $isActive,
            'searchPhrase' => trim($searchPhrase),
            'name' => $namesOut,
        ];
    }

    /**
     * Cleanup temporary files and directories.
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

    /**
     * Create temporary table
     */
    private function createTemporaryTable(): void
    {
        Schema::create($this->temporaryTable, function (Blueprint $table) {
            $table->comment('Rors temporary table');
            $table->string('ror')->nullable(false);
            $table->string('display_locale', 28)->nullable(false);
            $table->smallInteger('is_active')->nullable(false)->default(1);
            $table->mediumText('search_phrase')->nullable();
            $table->string('locale', 28)->default('');
            $table->string('setting_name', 255);
            $table->mediumText('setting_value')->nullable();
            $table->unique(['ror', 'locale', 'setting_name'], $this->temporaryTable . '_unique');
        });
    }

    /**
     * Drop temporary table
     */
    private function dropTemporaryTable(): void
    {
        Schema::dropIfExists($this->temporaryTable);
    }

    /**
     * Execute query
     */
    private function processBatch(array $rows): void
    {
        $values = [];

        // insert into temporary table
        foreach ($rows as $row) {
            foreach ($row['name'] as $locale => $name) {
                $values[] = [
                    'ror' => $row['ror'],
                    'display_locale' => $row['displayLocale'],
                    'is_active' => $row['isActive'],
                    'search_phrase' => $row['searchPhrase'],
                    'locale' => $locale,
                    'setting_name' => 'name',
                    'setting_value' => $name
                ];
            }
        }

        DB::table($this->temporaryTable)->insert($values);

        // table rors
        $values = DB::table($this->temporaryTable . ' as tmp')
            ->select('tmp.ror', 'tmp.display_locale', 'tmp.is_active', 'tmp.search_phrase')
            ->distinct()
            ->leftJoin('rors as r', 'tmp.ror', '=', 'r.ror')
            ->get()
            ->map(function ($item) {
                return (array)$item;
            })
            ->all();

        DB::table('rors')->upsert($values, ['ror'], ['display_locale', 'is_active', 'search_phrase']);

        // remove settings/names that do not exist any more
        $orphanedSettings = DB::table('ror_settings AS rs')
            ->select('rs.ror_setting_id')
            ->join('rors as r', 'rs.ror_id', '=', 'r.ror_id')
            ->join($this->temporaryTable . ' as tmp1', 'tmp1.ror', '=', 'r.ror')
            ->leftJoin($this->temporaryTable . ' AS tmp2', function ($join) {
                $join
                    ->on('tmp2.ror', '=', 'tmp1.ror')
                    ->on('tmp2.locale', '=', 'rs.locale')
                    ->on('tmp2.setting_name', '=', 'rs.setting_name');
            })
            ->whereNull('tmp2.locale')
            ->distinct()
            ->pluck('rs.ror_setting_id');
        DB::table('ror_settings')->whereIn('ror_setting_id', $orphanedSettings)->delete();

        // table ror_settings
        $values = DB::table($this->temporaryTable . ' as tmp')
            ->select('r.ror_id', 'tmp.locale', 'tmp.setting_name', 'tmp.setting_value')
            ->join('rors as r', 'tmp.ror', '=', 'r.ror')
            ->leftJoin('ror_settings as rs', function ($join) {
                $join
                    ->on('r.ror_id', '=', 'rs.ror_id')
                    ->on('rs.locale', '=', 'tmp.locale')
                    ->on('rs.setting_name', '=', 'tmp.setting_name');
            })
            ->distinct()
            ->get()
            ->map(function ($item) {
                return (array)$item;
            })
            ->all();

        DB::table('ror_settings')->upsert($values, ['ror_id', 'locale', 'setting_name'], ['setting_value']);

        // truncate temporary table
        DB::table($this->temporaryTable)->truncate();
    }
}

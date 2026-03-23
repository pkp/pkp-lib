<?php

/**
 * @file classes/task/UpdateRorRegistryDataset.php
 *
 * Copyright (c) 2025-2026 Simon Fraser University
 * Copyright (c) 2025-2026 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class UpdateRorRegistryDataset
 *
 * @ingroup tasks
 *
 * @brief Class responsible for monthly update of the ROR Registry tables in the database.
 */

/**
 * Mapping Ror JSON schema v2 data dump and OJS
 *
 * | json path                                  | ojs                                                                               |
 * |--------------------------------------------|-----------------------------------------------------------------------------------|
 * | id                                         | rors.ror                                                                          |
 * | status                                     | rors.is_active                                                                    |
 * | names[].value where types[] = ror_display  | ror_settings['locale' => lang, 'setting_name' => 'name', 'setting_value' => '…'] |
 * | names[].value where types[] = label        | ror_settings['locale' => lang, 'setting_name' => 'name', 'setting_value' => '…'] |
 * | names[].lang  where types[] = ror_display  | rors.display_locale                                                               |
 */

namespace PKP\task;

use APP\core\Application;
use DirectoryIterator;
use Exception;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Facades\DB;
use PKP\core\Core;
use PKP\file\PrivateFileManager;
use PKP\scheduledTask\ScheduledTask;
use PKP\scheduledTask\ScheduledTaskHelper;
use Symfony\Component\JsonStreamer\JsonStreamReader;
use Symfony\Component\TypeInfo\Type;
use Throwable;
use ZipArchive;

class UpdateRorRegistryDataset extends ScheduledTask
{
    private PrivateFileManager $fileManager;

    /** Zenodo API url for the ROR Registry dataset — always returns the latest version. */
    private string $rorDatasetUrl = 'https://zenodo.org/api/records/6347574';

    /** The target schema version of the JSON data dump. */
    private int $targetSchemaVersion = 2;

    /** The prefix used for the temporary zip file and the extracted directory. */
    private string $prefix = 'TemporaryRorRegistryCache';

    /** No language code available key in registry */
    private string $noLocale = 'no_lang_code';

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
            $this->cleanup([$pathZipFile, $pathZipDir]);
            $downloadData = $this->getDownloadData();
            if (empty($downloadData)) {
                return false;
            }

            $start = microtime(true);
            if (!$this->downloadAndExtract($downloadData['url'], $pathZipFile, $pathZipDir, $downloadData['checksum'])) {
                return false;
            }
            $this->addExecutionLogEntry('Download+extract: ' . round(microtime(true) - $start, 2) . 's', ScheduledTaskHelper::SCHEDULED_TASK_MESSAGE_TYPE_NOTICE);

            $pathJson = $this->getPathJson($pathZipDir);
            if (empty($pathJson) || !$this->fileManager->fileExists($pathJson)) {
                $this->addExecutionLogEntry(
                    __('admin.scheduledTask.UpdateRorRegistryDataset.error.jsonNotFound', [
                        'version' => $this->targetSchemaVersion
                    ]),
                    ScheduledTaskHelper::SCHEDULED_TASK_MESSAGE_TYPE_ERROR
                );
                return false;
            }

            $start = microtime(true);
            $this->processJson($pathJson);
            $this->addExecutionLogEntry('processJson total: ' . round(microtime(true) - $start, 2) . 's', ScheduledTaskHelper::SCHEDULED_TASK_MESSAGE_TYPE_NOTICE);

            return true;
        } catch (Throwable $e) {
            $this->addExecutionLogEntry(
                $e->getMessage(),
                ScheduledTaskHelper::SCHEDULED_TASK_MESSAGE_TYPE_ERROR
            );

            return false;
        } finally {
            try {
                $this->cleanup([$pathZipFile, $pathZipDir]);
            } catch (Throwable $e) {
                $this->addExecutionLogEntry(
                    $e->getMessage(),
                    ScheduledTaskHelper::SCHEDULED_TASK_MESSAGE_TYPE_ERROR
                );
            }
        }
    }

    /**
     * Get download url and checksum from the Zenodo API.
     */
    private function getDownloadData(): array
    {
        try {
            $client = Application::get()->getHttpClient();

            $response = $client->request('GET', $this->rorDatasetUrl);

            if ($response->getStatusCode() !== 200) {
                $this->addExecutionLogEntry(
                    __('admin.scheduledTask.UpdateRorRegistryDataset.error.statusCode', [
                        'statusCode' => $response->getStatusCode()
                    ]),
                    ScheduledTaskHelper::SCHEDULED_TASK_MESSAGE_TYPE_ERROR
                );
                return [];
            }

            $responseArray = json_decode($response->getBody(), true, 512, JSON_THROW_ON_ERROR);

            $file = $responseArray['files'][0] ?? null;
            if (empty($file['links']['self']) || empty($file['checksum'])) {
                $this->addExecutionLogEntry(
                    __('admin.scheduledTask.UpdateRorRegistryDataset.error.noDownloadFile'),
                    ScheduledTaskHelper::SCHEDULED_TASK_MESSAGE_TYPE_ERROR
                );
                return [];
            }

            return [
                'url' => $file['links']['self'],
                'checksum' => str_replace('md5:', '', $file['checksum'])
            ];
        } catch (GuzzleException | Exception $e) {
            $this->addExecutionLogEntry(
                $e->getMessage(),
                ScheduledTaskHelper::SCHEDULED_TASK_MESSAGE_TYPE_ERROR
            );

            return [];
        }
    }

    /**
     * Download and extract zip file
     */
    private function downloadAndExtract(string $downloadUrl, string $pathZipFile, string $pathZipDir, string $checksum): bool
    {
        try {
            $client = Application::get()->getHttpClient();

            // Download the file
            $client->request('GET', $downloadUrl, ['sink' => $pathZipFile]);
            if (!$this->fileManager->fileExists($pathZipFile)) {
                $this->addExecutionLogEntry(
                    __('admin.scheduledTask.UpdateRorRegistryDataset.error.download'),
                    ScheduledTaskHelper::SCHEDULED_TASK_MESSAGE_TYPE_ERROR
                );
                return false;
            }

            // Verify the checksum of the downloaded file
            $fileChecksum = md5_file($pathZipFile);
            if ($fileChecksum !== $checksum) {
                $this->addExecutionLogEntry(
                    __('admin.scheduledTask.UpdateRorRegistryDataset.error.checksum', [
                        'expected' => $checksum, 'actual' => $fileChecksum
                    ]),
                    ScheduledTaskHelper::SCHEDULED_TASK_MESSAGE_TYPE_ERROR
                );
                return false;
            }

            // Extract the file
            $zip = new ZipArchive();
            if ($zip->open($pathZipFile) === true) {
                $zip->extractTo($pathZipDir);
                $zip->close();
            }
            if (!$this->fileManager->fileExists($pathZipDir, 'dir')) {
                $this->addExecutionLogEntry(
                    __('admin.scheduledTask.UpdateRorRegistryDataset.error.extract'),
                    ScheduledTaskHelper::SCHEDULED_TASK_MESSAGE_TYPE_ERROR
                );
                return false;
            }

            return true;
        } catch (GuzzleException | Exception $e) {
            $this->addExecutionLogEntry(
                $e->getMessage(),
                ScheduledTaskHelper::SCHEDULED_TASK_MESSAGE_TYPE_ERROR
            );

            return false;
        }
    }

    /**
     * Get path to json file
     */
    private function getPathJson(string $pathZipDir): string
    {
        $iterator = new DirectoryIterator($pathZipDir);
        foreach ($iterator as $fileInfo) {
            if (!$fileInfo->isDot()) {
                if (
                    $fileInfo->getExtension() === 'json' &&
                    $this->hasSchemaVersion($fileInfo->getPathname(), $this->targetSchemaVersion)
                ) {
                    return $fileInfo->getPathname();
                }
            }
        }

        return '';
    }

    /**
     * Check if a JSON file's first record matches the target schema version,
     * by reading only the first few KB to avoid loading the full file.
     * Uses admin.last_modified.schema_version, which reflects the file's schema format
     * (admin.created.schema_version may still reference an older version).
     *
     * The admin block is typically within the first 500 bytes of the file.
     * Reading up to 16 chunks of 4KB (64KB total) is a conservative safety margin
     * in case ROR changes the field ordering within records.
     */
    private function hasSchemaVersion(string $pathJson, int $version): bool
    {
        $handle = fopen($pathJson, 'r');
        if ($handle === false) {
            return false;
        }

        $maxChunks = 16;
        $buffer = '';
        for ($i = 0; $i < $maxChunks; $i++) {
            $chunk = fread($handle, 4096);
            if ($chunk === false || $chunk === '') {
                break;
            }
            $buffer .= $chunk;
            if (preg_match('/"admin"\s*:\s*\{.*?"last_modified"\s*:\s*\{[^}]*"schema_version"\s*:\s*"(\d+)/s', $buffer, $matches)) {
                fclose($handle);
                return (int)$matches[1] === $version;
            }
        }

        fclose($handle);
        return false;
    }

    /**
     * Process Json file
     */
    private function processJson(string $pathJson): void
    {
        $cacheDir = $this->getCacheDir();
        $this->fileManager->mkdirtree($cacheDir . '/readers');
        $this->fileManager->mkdirtree($cacheDir . '/ghosts');

        $reader = JsonStreamReader::create(
            streamReadersDir: $cacheDir . '/readers',
            lazyGhostsDir: $cacheDir . '/ghosts',
        );

        $resource = fopen($pathJson, 'r');
        if ($resource === false) {
            throw new Exception("Failed to read ROR JSON file: {$pathJson}");
        }

        try {
            $batchSize = 5000;
            $batchRows = [];
            $timeStreaming = 0.0;
            $timeDb = 0.0;

            $streamStart = microtime(true);
            foreach ($reader->read($resource, Type::list(Type::array())) as $record) {
                $timeStreaming += microtime(true) - $streamStart;

                if (empty($record['id']) || empty($record['names']) || empty($record['status'])) {
                    $this->addExecutionLogEntry(
                        __('admin.scheduledTask.UpdateRorRegistryDataset.error.missingFields', [
                            'id' => $record['id'] ?? ''
                        ]),
                        ScheduledTaskHelper::SCHEDULED_TASK_MESSAGE_TYPE_WARNING
                    );
                    $streamStart = microtime(true);
                    continue;
                }

                $batchRows[] = $this->processRow($record);

                if (count($batchRows) >= $batchSize) {
                    $dbStart = microtime(true);
                    $this->processBatch($batchRows);
                    $timeDb += microtime(true) - $dbStart;
                    $batchRows = [];
                }

                $streamStart = microtime(true);
            }

            if (!empty($batchRows)) {
                $dbStart = microtime(true);
                $this->processBatch($batchRows);
                $timeDb += microtime(true) - $dbStart;
            }

            $this->addExecutionLogEntry('JSON streaming: ' . round($timeStreaming, 2) . 's, DB operations: ' . round($timeDb, 2) . 's', ScheduledTaskHelper::SCHEDULED_TASK_MESSAGE_TYPE_NOTICE);
        } finally {
            fclose($resource);
        }
    }

    /**
     * Get the cache directory for JsonStreamReader generated files.
     */
    private function getCacheDir(): string
    {
        return Core::getBaseDir() . '/cache/json_streamer';
    }

    /**
     * Process record from JSON
     */
    private function processRow(array $record): array
    {
        // ror from id
        $ror = $record['id'];

        // is_active from status
        $isActive = (strtolower($record['status']) === 'active') ? 1 : 0;

        // search_phrase
        $searchPhrase = $ror;

        // Process names array to extract display name, locale, and all name variants
        $displayLocale = $this->noLocale;
        $displayName = '';
        $namesByLocale = [];

        foreach ($record['names'] as $nameEntry) {
            $locale = $nameEntry['lang'] ?? $this->noLocale;
            $name = $nameEntry['value'] ?? '';
            $types = $nameEntry['types'] ?? [];

            // Check if this is the ror_display name
            if (in_array('ror_display', $types)) {
                $displayLocale = $locale;
                $displayName = $name;
            }

            // Collect all labels and ror_display names
            if (in_array('label', $types) || in_array('ror_display', $types)) {
                $namesByLocale[$locale] = $name;
                $searchPhrase .= ' ' . $name;
            }
        }

        // Ensure display name is in the names array
        if (!empty($displayName) && empty($namesByLocale[$displayLocale])) {
            $namesByLocale[$displayLocale] = $displayName;
        }

        return [
            'rors' => [
                'ror' => $ror,
                'display_locale' => $displayLocale,
                'is_active' => $isActive,
                'search_phrase' => trim($searchPhrase),
            ],
            'names' => $namesByLocale,
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
     * Upsert rors and ror_settings for a batch of rows.
     */
    private function processBatch(array $rows): void
    {
        // upsert into rors
        $rorsValues = array_column($rows, 'rors');

        DB::table('rors')->upsert($rorsValues, ['ror'], ['display_locale', 'is_active', 'search_phrase']);

        // fetch ror_id for each ror in the batch
        $rorStrings = array_column($rorsValues, 'ror');
        $rorIdMap = DB::table('rors')
            ->whereIn('ror', $rorStrings)
            ->pluck('ror_id', 'ror');

        // delete existing ror_settings for these rors and re-insert fresh, atomically
        $settingsValues = [];
        foreach ($rows as $row) {
            $rorId = $rorIdMap[$row['rors']['ror']] ?? null;
            if ($rorId === null) {
                continue;
            }
            foreach ($row['names'] as $locale => $name) {
                $settingsValues[] = [
                    'ror_id' => $rorId,
                    'locale' => $locale,
                    'setting_name' => 'name',
                    'setting_value' => $name,
                ];
            }
        }

        DB::transaction(function () use ($rorIdMap, $settingsValues) {
            DB::table('ror_settings')->whereIn('ror_id', $rorIdMap->values())->delete();
            if (!empty($settingsValues)) {
                DB::table('ror_settings')->insert($settingsValues);
            }
        });
    }
}

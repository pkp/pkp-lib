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
use APP\facades\Repo;
use DirectoryIterator;
use Exception;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Facades\DB;
use PKP\file\PrivateFileManager;
use PKP\scheduledTask\ScheduledTask;
use PKP\scheduledTask\ScheduledTaskHelper;
use Throwable;
use ZipArchive;

class UpdateRorRegistryDataset extends ScheduledTask
{
    private PrivateFileManager $fileManager;

    /** Zenodo API URL for the ROR Registry dataset — always returns the latest version. */
    private string $rorDatasetUrl = 'https://zenodo.org/api/records/6347574';

    /** The target schema version of the JSON data dump. */
    private int $targetSchemaVersion = 2;

    /** The prefix used for the temporary zip file and the extracted directory. */
    private string $prefix = 'TemporaryRorRegistryCache';

    /** Fallback locale key for ROR names with no language code, ensuring they are always stored and retrievable. */
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

            if (!$this->downloadAndExtract($downloadData['url'], $pathZipFile, $pathZipDir, $downloadData['checksumAlgorithm'], $downloadData['checksum'])) {
                return false;
            }

            $pathJson = $this->getPathJson($pathZipDir);
            if (empty($pathJson)) {
                $this->addExecutionLogEntry(
                    __('admin.scheduledTask.UpdateRorRegistryDataset.error.jsonNotFound', [
                        'version' => $this->targetSchemaVersion
                    ]),
                    ScheduledTaskHelper::SCHEDULED_TASK_MESSAGE_TYPE_ERROR
                );
                return false;
            }

            $processedCount = $this->processJson($pathJson);
            if ($processedCount === 0) {
                $this->addExecutionLogEntry(
                    __('admin.scheduledTask.UpdateRorRegistryDataset.error.noRecordsProcessed'),
                    ScheduledTaskHelper::SCHEDULED_TASK_MESSAGE_TYPE_ERROR
                );
                return false;
            }

            Repo::funder()->forgetAllFunderFacetCaches();

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
     * Get download URL and checksum from the Zenodo API.
     */
    private function getDownloadData(): array
    {
        try {
            $client = Application::get()->getHttpClient();

            $response = $client->request('GET', $this->rorDatasetUrl, ['timeout' => 30]);

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
            if ($file === null || empty($file['links']['self']) || empty($file['checksum'])) {
                $this->addExecutionLogEntry(
                    __('admin.scheduledTask.UpdateRorRegistryDataset.error.noDownloadFile'),
                    ScheduledTaskHelper::SCHEDULED_TASK_MESSAGE_TYPE_ERROR
                );
                return [];
            }

            $checksumParts = explode(':', $file['checksum'], 2);
            if (count($checksumParts) !== 2 || !in_array($checksumParts[0], ['md5', 'sha256'])) {
                $this->addExecutionLogEntry(
                    __('admin.scheduledTask.UpdateRorRegistryDataset.error.checksumAlgorithm'),
                    ScheduledTaskHelper::SCHEDULED_TASK_MESSAGE_TYPE_ERROR
                );
                return [];
            }

            return [
                'url' => $file['links']['self'],
                'checksumAlgorithm' => $checksumParts[0],
                'checksum' => $checksumParts[1],
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
     * Download and extract zip file.
     */
    private function downloadAndExtract(string $downloadUrl, string $pathZipFile, string $pathZipDir, string $checksumAlgorithm, string $checksum): bool
    {
        try {
            $client = Application::get()->getHttpClient();

            // Download the file
            $client->request('GET', $downloadUrl, ['sink' => $pathZipFile, 'connect_timeout' => 30, 'read_timeout' => 300, 'timeout' => 600]);
            if (!$this->fileManager->fileExists($pathZipFile)) {
                $this->addExecutionLogEntry(
                    __('admin.scheduledTask.UpdateRorRegistryDataset.error.download'),
                    ScheduledTaskHelper::SCHEDULED_TASK_MESSAGE_TYPE_ERROR
                );
                return false;
            }

            // Verify the checksum of the downloaded file
            $fileChecksum = match($checksumAlgorithm) {
                'md5' => md5_file($pathZipFile),
                'sha256' => hash_file('sha256', $pathZipFile),
                default => throw new Exception("Unsupported checksum algorithm: {$checksumAlgorithm}"),
            };
            if ($fileChecksum === false) {
                $this->addExecutionLogEntry(
                    __('admin.scheduledTask.UpdateRorRegistryDataset.error.checksumVerify'),
                    ScheduledTaskHelper::SCHEDULED_TASK_MESSAGE_TYPE_ERROR
                );
                return false;
            }
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
            $zipResult = $zip->open($pathZipFile);
            if ($zipResult !== true) {
                $this->addExecutionLogEntry(
                    __('admin.scheduledTask.UpdateRorRegistryDataset.error.zipOpen', [
                        'code' => $zipResult
                    ]),
                    ScheduledTaskHelper::SCHEDULED_TASK_MESSAGE_TYPE_ERROR
                );
                return false;
            }
            $extractResult = $zip->extractTo($pathZipDir);
            $zip->close();
            if (!$extractResult || !$this->fileManager->fileExists($pathZipDir, 'dir')) {
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
     * Get path to JSON file.
     */
    private function getPathJson(string $pathZipDir): string
    {
        $iterator = new DirectoryIterator($pathZipDir);
        foreach ($iterator as $fileInfo) {
            if (
                $fileInfo->getExtension() === 'json' &&
                $this->hasSchemaVersion($fileInfo->getPathname(), $this->targetSchemaVersion)
            ) {
                return $fileInfo->getPathname();
            }
        }

        return '';
    }

    /**
     * Check if a JSON file's first record matches the target schema version.
     * Uses admin.last_modified.schema_version, which reflects the file's schema format
     * (admin.created.schema_version may still reference an older version).
     *
     * This reads only the first top-level item from the dump via readJsonRecords(),
     * so it stays lightweight while avoiding brittle checks against raw JSON text.
     */
    private function hasSchemaVersion(string $pathJson, int $version): bool
    {
        try {
            foreach ($this->readJsonRecords($pathJson) as $record) {
                $schemaVersion = $record['admin']['last_modified']['schema_version'] ?? null;
                return (int)$schemaVersion === $version;
            }

            return false;
        } catch (Throwable $e) {
            $this->addExecutionLogEntry(
                __('admin.scheduledTask.UpdateRorRegistryDataset.error.schemaVersionRead', [
                    'file' => basename($pathJson),
                    'error' => $e->getMessage(),
                ]),
                ScheduledTaskHelper::SCHEDULED_TASK_MESSAGE_TYPE_WARNING
            );
            return false;
        }
    }

    /**
     * Process JSON file.
     *
     * @return int Number of records successfully processed.
     */
    private function processJson(string $pathJson): int
    {
        $batchSize = 3000;
        $batchRows = [];
        $processedCount = 0;

        foreach ($this->readJsonRecords($pathJson) as $record) {
            if (empty($record['id']) || empty($record['names']) || empty($record['status'])) {
                $this->addExecutionLogEntry(
                    __('admin.scheduledTask.UpdateRorRegistryDataset.error.missingFields', [
                        'id' => $record['id'] ?? ''
                    ]),
                    ScheduledTaskHelper::SCHEDULED_TASK_MESSAGE_TYPE_WARNING
                );
                continue;
            }

            $row = $this->processRow($record);
            if ($row === null) {
                continue;
            }
            $batchRows[] = $row;
            $processedCount++;

            if (count($batchRows) >= $batchSize) {
                $this->processBatch($batchRows);
                $batchRows = [];
            }
        }

        if (!empty($batchRows)) {
            $this->processBatch($batchRows);
        }

        return $processedCount;
    }

    /**
     * Upsert rors and ror_settings for a batch of rows.
     *
     * rors rows are upserted (insert or update). ror_settings name rows are also
     * upserted, and any locale entries that are no longer present in the dump are
     * deleted afterwards. This avoids rewriting rows that have not changed, which
     * matters for monthly updates where most records stay the same.
     *
     * This is equivalent in intent to the previous temporary-table-based implementation,
     * which likewise upserted ror_settings and deleted orphaned locale rows via JOIN —
     * just without the temporary table.
     *
     * A simpler alternative would be to delete all ror_settings name rows for the
     * batch and reinsert them from scratch. That would be easier to reason about
     * at the cost of more write I/O on every run.
     *
     * This importer intentionally does not delete rors rows that are absent from a
     * newly downloaded dump. It relies on the ROR dataset keeping identifiers
     * stable over time and marking deprecated records through status changes
     * instead of removing them outright. If that upstream contract changes,
     * full-dataset cleanup will need to be reintroduced here.
     */
    private function processBatch(array $rows): void
    {
        $rorsValues = array_column($rows, 'rors');

        DB::transaction(function () use ($rorsValues, $rows) {
            // upsert into rors
            DB::table('rors')->upsert($rorsValues, ['ror'], ['display_locale', 'is_active', 'search_phrase']);

            // fetch ror_id for each ror in the batch
            $rorIdMap = DB::table('rors')
                ->whereIn('ror', array_column($rorsValues, 'ror'))
                ->pluck('ror_id', 'ror');

            // upsert current name settings for these rors
            $settingsValues = [];
            $localesByRorId = [];
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
                    $localesByRorId[$rorId][$locale] = true;
                }
            }

            if (!empty($settingsValues)) {
                DB::table('ror_settings')->upsert(
                    $settingsValues,
                    ['ror_id', 'locale', 'setting_name'],
                    ['setting_value']
                );
            }

            // delete stale name rows for rors whose locale set has shrunk since the last import;
            // first fetch all existing (ror_id, locale) pairs so we can skip rors with no stale rows
            $existing = DB::table('ror_settings')
                ->whereIn('ror_id', $rorIdMap->values())
                ->where('setting_name', 'name')
                ->select('ror_id', 'locale')
                ->get();

            // collect all stale (ror_id, locale) pairs and delete in a single query
            $stalePairs = [];
            $bindings = ['name'];
            foreach ($existing as $row) {
                if (!isset($localesByRorId[$row->ror_id][$row->locale])) {
                    $stalePairs[] = '(?, ?)';
                    $bindings[] = $row->ror_id;
                    $bindings[] = $row->locale;
                }
            }

            if (!empty($stalePairs)) {
                $placeholders = implode(', ', $stalePairs);
                DB::statement("DELETE FROM ror_settings WHERE setting_name = ? AND (ror_id, locale) IN ({$placeholders})", $bindings);
            }
        });
    }

    /**
     * Read top-level JSON objects from a JSON array file using chunked I/O.
     *
     * The ROR data dump is a single JSON array: [{...}, {...}, ...].
     * Instead of using a pure-PHP streaming tokenizer, this reads large
     * chunks with fread() and finds object boundaries by tracking brace
     * depth — both fread() and the final json_decode() per record are
     * native C, making this significantly faster.
     *
     * @return \Generator<int, array> Yields decoded associative arrays.
     */
    private function readJsonRecords(string $pathJson): \Generator
    {
        $handle = fopen($pathJson, 'r');
        if ($handle === false) {
            return;
        }

        try {
            $buffer = '';
            $depth = 0;
            $inString = false;
            $escape = false;

            while (($chunk = fread($handle, 8 * 1024 * 1024)) !== false && $chunk !== '') {
                $len = strlen($chunk);
                $pos = 0;

                while ($pos < $len) {
                    if ($escape) {
                        if ($depth > 0) {
                            $buffer .= $chunk[$pos];
                        }
                        $escape = false;
                        $pos++;
                        continue;
                    }

                    if ($inString) {
                        $skip = strcspn($chunk, '"\\', $pos);
                        if ($depth > 0) {
                            $buffer .= substr($chunk, $pos, $skip);
                        }
                        $pos += $skip;

                        if ($pos >= $len) {
                            break;
                        }

                        $ch = $chunk[$pos];
                        if ($ch === '\\') {
                            $escape = true;
                            if ($depth > 0) {
                                $buffer .= '\\';
                            }
                        } elseif ($ch === '"') {
                            $inString = false;
                            if ($depth > 0) {
                                $buffer .= '"';
                            }
                        }
                        $pos++;
                        continue;
                    }

                    // Outside a string — skip to next structural character
                    $skip = strcspn($chunk, '"{}[]', $pos);
                    if ($depth > 0) {
                        $buffer .= substr($chunk, $pos, $skip);
                    }
                    $pos += $skip;

                    if ($pos >= $len) {
                        break;
                    }

                    $ch = $chunk[$pos];
                    switch ($ch) {
                        case '"':
                            $inString = true;
                            if ($depth > 0) {
                                $buffer .= '"';
                            }
                            break;

                        case '{':
                            $depth++;
                            $buffer .= '{';
                            break;

                        case '}':
                            $depth--;
                            $buffer .= '}';
                            if ($depth === 0) {
                                $decoded = json_decode($buffer, true);
                                $buffer = '';
                                if ($decoded !== null) {
                                    yield $decoded;
                                }
                            }
                            break;

                        case '[':
                        case ']':
                            if ($depth > 0) {
                                $buffer .= $ch;
                            }
                            break;
                    }
                    $pos++;
                }
            }
        } finally {
            fclose($handle);
        }
    }

    /**
     * Process record from JSON.
     *
     * @return array|null Processed row data, or null if no valid name could be found.
     */
    private function processRow(array $record): ?array
    {
        $ror = $record['id'];
        $isActive = (strtolower($record['status']) === 'active') ? 1 : 0;
        $searchPhrase = $ror;

        // Process names array to extract display name, locale, and all name variants
        $displayLocale = $this->noLocale;
        $namesByLocale = [];
        $firstLabelName = null;
        $firstValidName = null;

        foreach ($record['names'] as $nameEntry) {
            // according to the ROR schema, 'value' is required for each name entry
            if (!isset($nameEntry['value'])) {
                $this->addExecutionLogEntry(
                    __('admin.scheduledTask.UpdateRorRegistryDataset.warning.missingNameValue', [
                        'id' => $ror,
                        'types' => implode(', ', $nameEntry['types'] ?? []),
                    ]),
                    ScheduledTaskHelper::SCHEDULED_TASK_MESSAGE_TYPE_WARNING
                );
                continue;
            }
            $name = $nameEntry['value'];
            $firstValidName ??= $name;
            $locale = $nameEntry['lang'] ?? $this->noLocale;
            $types = $nameEntry['types'] ?? [];

            if (in_array('ror_display', $types)) {
                $displayLocale = $locale;
                $namesByLocale[$locale] = $name;
                $searchPhrase .= ' ' . $name;
            } elseif (in_array('label', $types)) {
                $namesByLocale[$locale] ??= $name;
                $searchPhrase .= ' ' . $name;
                $firstLabelName ??= $name;
            }
        }

        // Fallback in case no ror_display name was found; prefer first label, then first valid name found.
        if (empty($namesByLocale[$displayLocale])) {
            if ($firstLabelName === null && $firstValidName === null) {
                $this->addExecutionLogEntry(
                    __('admin.scheduledTask.UpdateRorRegistryDataset.warning.noValidName', [
                        'id' => $ror,
                    ]),
                    ScheduledTaskHelper::SCHEDULED_TASK_MESSAGE_TYPE_WARNING
                );
                return null;
            }
            $namesByLocale[$displayLocale] = $firstLabelName ?? $firstValidName;
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
            $this->fileManager->rmtree($path);
        }
    }

}

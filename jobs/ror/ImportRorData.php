<?php

/**
 * @file jobs/ror/ImportRorData.php
 *
 * Copyright (c) 2014-2025 Simon Fraser University
 * Copyright (c) 2000-2025 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class ImportRorData
 *
 * @brief Job to read ROR CSV dataset and run batch import process
 */

namespace PKP\jobs\ror;

use Exception;
use Throwable;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use PKP\jobs\BaseJob;
use Illuminate\Bus\Batch;
use PKP\file\PrivateFileManager;
use Illuminate\Support\Facades\Bus;
use PKP\scheduledTask\ScheduledTaskHelper;
use PKP\task\UpdateRorRegistryDataset;

class ImportRorData extends BaseJob
{
    /**
     * Indicate if the job should be marked as failed on timeout.
     */
    public bool $failOnTimeout = false;

    protected PrivateFileManager $fileManager;

    public function __construct(
        protected string $prefix,
        protected string $csvNameContains,
        protected array $dataMapping,
        protected array $dataMappingIndex,
        protected string $noLocale,
        protected string $temporaryTable,
        protected ?string $scheduledTaskLogFilesPath = null
    )
    {
        parent::__construct();
        $this->fileManager = new PrivateFileManager();
    }

    /**
     * Define middleware for preventing overlaps
     */
    public function middleware()
    {
        return [
            // (new WithoutOverlapping($this->prefix))->expireAfter(180), // 3 mins lock
        ];
    }

    public function handle()
    {
        try {
            $pathZipDir = $this->fileManager->getBasePath() . DIRECTORY_SEPARATOR . $this->prefix;

            if (!$this->fileManager->fileExists($pathZipDir, 'dir')) {
                throw new Exception("Ror dataset extracted directory at {$pathZipDir} not found");
            }

            $pathCsv = UpdateRorRegistryDataset::getPathCsv($pathZipDir, $this->csvNameContains);
            if (empty($pathCsv) || !$this->fileManager->fileExists($pathCsv)) {
                throw new Exception('CSV file not found');
            }

            // Count total rows
            $totalRows = $this->countCsvRows($pathCsv);
            if ($totalRows === 0) {
                throw new Exception('No rows found in CSV');
            }

            // Create batch jobs
            $batchSize = 5000;
            $jobs = [];
            for ($startRow = 1; $startRow <= $totalRows; $startRow += $batchSize) {
                $endRow = min($startRow + $batchSize - 1, $totalRows);
                $jobs[] = new ProcessRorCsv(
                    $pathCsv,
                    $startRow,
                    $endRow,
                    $this->dataMapping,
                    $this->dataMappingIndex,
                    $this->noLocale,
                    $this->temporaryTable,
                    $this->scheduledTaskLogFilesPath
                );
            }

            // we need to get thses in local variables to pass into the batch closures(e.g. then, catch, etc)
            // as the serialization of $this in closure is buggy and can lead to issues
            $logFilePath = $this->scheduledTaskLogFilesPath;

        
            UpdateRorRegistryDataset::log(
                'RoR dataset importing batch process starting.', 
                $logFilePath,
                ScheduledTaskHelper::SCHEDULED_TASK_MESSAGE_TYPE_NOTICE
            );

            Bus::batch($jobs)
                ->then(function (Batch $batch) use ($pathCsv, $pathZipDir, $logFilePath) {
                    UpdateRorRegistryDataset::cleanup([$pathCsv, $pathZipDir]);
                    UpdateRorRegistryDataset::log(
                        'RoR dataset importing completed.',
                        $logFilePath,
                        ScheduledTaskHelper::SCHEDULED_TASK_MESSAGE_TYPE_COMPLETED
                    );
                })
                ->catch(function (Batch $batch, Throwable $e) use ($logFilePath) {
                    UpdateRorRegistryDataset::log(
                        "RoR dataset importing batch at progress of {$batch->progress()}% have failed: {$e->getMessage()}",
                        $logFilePath,
                        ScheduledTaskHelper::SCHEDULED_TASK_MESSAGE_TYPE_ERROR
                    );
                })
                ->finally(function (Batch $batch) use ($pathCsv, $pathZipDir, $logFilePath) {
                    UpdateRorRegistryDataset::cleanup([$pathCsv, $pathZipDir]);
                    UpdateRorRegistryDataset::log(
                        'RoR dataset importing batch jobs have finished.', 
                        $logFilePath,
                        ScheduledTaskHelper::SCHEDULED_TASK_MESSAGE_TYPE_NOTICE
                    );
                })
                ->name('import.ror.dataset.chunks')
                ->dispatch();
        } catch (Throwable $e) {
            UpdateRorRegistryDataset::log(
                "RoR dataset importing failed: {$e->getMessage()}",
                $this->scheduledTaskLogFilesPath,
                ScheduledTaskHelper::SCHEDULED_TASK_MESSAGE_TYPE_ERROR
            );

            throw $e;
        }
    }

    protected function countCsvRows(string $pathCsv): int
    {
        $rowCount = 0;
        $handle = fopen($pathCsv, 'r');
        while (!feof($handle)) {
            $buffer = fread($handle, 8192);
            $rowCount += substr_count($buffer, "\n");
        }
        fclose($handle);
        return $rowCount - 1; // Subtract header
    }
}

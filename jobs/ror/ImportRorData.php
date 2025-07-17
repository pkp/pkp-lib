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

use Throwable;
use PKP\jobs\BaseJob;
use Illuminate\Bus\Batch;
use PKP\file\PrivateFileManager;
use Illuminate\Support\Facades\Bus;
use PKP\scheduledTask\ScheduledTaskHelper;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use PKP\task\UpdateRorRegistryDataset;

class ImportRorData extends BaseJob implements ShouldBeUnique
{
    /**
     * The maximum number of SECONDS a job should get processed before consider failed
     */
    public int $timeout = 300;

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

    public function handle()
    {
        $pathZipDir = $this->fileManager->getBasePath() . DIRECTORY_SEPARATOR . $this->prefix;

        if (!$this->fileManager->fileExists($pathZipDir, 'dir')) {
            UpdateRorRegistryDataset::log(
                "Ror dataset extracted directory at {$pathZipDir} not found",
                $this->scheduledTaskLogFilesPath,
                ScheduledTaskHelper::SCHEDULED_TASK_MESSAGE_TYPE_ERROR
            );
            $this->fail("Ror dataset extracted directory at {$pathZipDir} not found");
            return;
        }

        $pathCsv = UpdateRorRegistryDataset::getPathCsv($pathZipDir, $this->csvNameContains);
        if (empty($pathCsv) || !$this->fileManager->fileExists($pathCsv)) {
            UpdateRorRegistryDataset::log(
                'CSV file not found',
                $this->scheduledTaskLogFilesPath,
                ScheduledTaskHelper::SCHEDULED_TASK_MESSAGE_TYPE_ERROR
            );
            $this->fail('CSV file not found');
            return;
        }

        // Count total rows
        $totalRows = $this->countCsvRows($pathCsv);
        if ($totalRows === 0) {
            UpdateRorRegistryDataset::log(
                'No rows found in CSV',
                $this->scheduledTaskLogFilesPath,
                ScheduledTaskHelper::SCHEDULED_TASK_MESSAGE_TYPE_ERROR
            );
            $this->fail('No rows found in CSV');
            return;
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

        // Dispatch batch
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
                    'RoR dataset importing batch jobs have failed.',
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
            ->dispatch();
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

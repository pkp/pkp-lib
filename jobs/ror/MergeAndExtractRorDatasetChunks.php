<?php

/**
 * @file jobs/ror/MergeAndExtractRorDatasetChunks.php
 *
 * Copyright (c) 2014-2025 Simon Fraser University
 * Copyright (c) 2000-2025 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class MergeAndExtractRorDatasetChunks
 *
 * @brief Job to combine chunks and extract ROR dataset
 */

namespace PKP\jobs\ror;

use Exception;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Throwable;
use ZipArchive;
use PKP\jobs\BaseJob;
use PKP\file\PrivateFileManager;
use PKP\task\UpdateRorRegistryDataset;
use PKP\scheduledTask\ScheduledTaskHelper;

class MergeAndExtractRorDatasetChunks extends BaseJob
{
    protected PrivateFileManager $fileManager;

    public function __construct(
        protected string $prefix,
        protected string $csvNameContains,
        protected string $pathZipDir,
        protected string $pathZipFile,
        protected string $chunkDir,
        protected ?string $scheduledTaskLogFilesPath = null
    ) {
        parent::__construct();
        $this->fileManager = new PrivateFileManager();
    }

    public function middleware()
    {
        return [
            // (new WithoutOverlapping($this->pathZipFile))->expireAfter(60) // 1-minute lock
        ];
    }

    public function handle()
    {
        try {
            UpdateRorRegistryDataset::log(
                'Merging chunks into ' . $this->pathZipFile,
                $this->scheduledTaskLogFilesPath,
                ScheduledTaskHelper::SCHEDULED_TASK_MESSAGE_TYPE_NOTICE
            );

            $zipHandle = fopen($this->pathZipFile, 'wb');
            if ($zipHandle === false) {
                throw new Exception('Failed to open ZIP file for writing');
            }

            $chunks = glob($this->chunkDir . DIRECTORY_SEPARATOR . 'chunk_*');
            natsort($chunks); // Ensure chunks are in order
            foreach ($chunks as $chunkFile) {
                $chunkHandle = fopen($chunkFile, 'rb');
                if ($chunkHandle === false) {
                    fclose($zipHandle);
                    throw new Exception("Failed to read chunk: $chunkFile");
                }
                stream_copy_to_stream($chunkHandle, $zipHandle);
                fclose($chunkHandle);
            }

            fclose($zipHandle);

            UpdateRorRegistryDataset::log(
                'Extracting ZIP file',
                $this->scheduledTaskLogFilesPath,
                ScheduledTaskHelper::SCHEDULED_TASK_MESSAGE_TYPE_NOTICE
            );
            $zip = new ZipArchive();
            if ($zip->open($this->pathZipFile) !== true) {
                throw new Exception('Failed to open ZIP file');
            }
            $zip->extractTo($this->pathZipDir);
            $zip->close();

            if (!$this->fileManager->fileExists($this->pathZipDir, 'dir')) {
                throw new Exception('Extraction failed');
            }

            // Get CSV path
            $pathCsv = UpdateRorRegistryDataset::getPathCsv($this->pathZipDir, $this->csvNameContains);
            if (empty($pathCsv) || !$this->fileManager->fileExists($pathCsv)) {
                throw new Exception('CSV file not found');
            }

            // Clean up chunk directory
            UpdateRorRegistryDataset::cleanup([$this->chunkDir]);
        } catch (Throwable $e) {
            UpdateRorRegistryDataset::cleanup([$this->chunkDir, $this->pathZipFile, $this->pathZipDir]);
            UpdateRorRegistryDataset::log(
                $e->getMessage(),
                $this->scheduledTaskLogFilesPath,
                ScheduledTaskHelper::SCHEDULED_TASK_MESSAGE_TYPE_ERROR
            );

            throw $e;
        }
    }
}

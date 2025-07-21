<?php

/**
 * @file jobs/ror/DownloadRoRDatasetInChunks.php
 *
 * Copyright (c) 2014-2025 Simon Fraser University
 * Copyright (c) 2000-2025 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class DownloadRoRDatasetInChunks
 *
 * @brief Job to download ROR dataset in chunks
 */

namespace PKP\jobs\ror;

use Exception;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use PKP\jobs\BaseJob;
use APP\core\Application;
use Illuminate\Bus\Batchable;
use PKP\file\PrivateFileManager;
use PKP\task\UpdateRorRegistryDataset;
use GuzzleHttp\Exception\GuzzleException;
use PKP\scheduledTask\ScheduledTaskHelper;
use Illuminate\Queue\Middleware\SkipIfBatchCancelled;

class DownloadRoRDatasetInChunks extends BaseJob
{
    use Batchable;

    /**
     * Indicate if the job should be marked as failed on timeout.
     */
    public bool $failOnTimeout = false;
    
    protected PrivateFileManager $fileManager;

    public function __construct(
        protected string $downloadUrl,
        protected int $startByte,
        protected int $endByte,
        protected string $chunkFile,
        protected string $pathZipDir,
        protected string $csvNameContains,
        protected ?string $scheduledTaskLogFilesPath = null
    ) {
        parent::__construct();
        $this->fileManager = new PrivateFileManager();
    }

    public function middleware()
    {
        return [
            // (new WithoutOverlapping($this->chunkFile))->expireAfter(60), // 1-minute lock
            new SkipIfBatchCancelled(),
        ];
    }

    public function handle()
    {
        try {

            $pathCsv = $this->fileManager->fileExists($this->pathZipDir, 'dir')
                ? UpdateRorRegistryDataset::getPathCsv($this->pathZipDir, $this->csvNameContains)
                : '';
            
            if (!empty($pathCsv) && $this->fileManager->fileExists($pathCsv)) {
                $this->batch()->cancel();
                return; // No need to download the CSV file, job can skip and cancel the batch
            }

            $client = Application::get()->getHttpClient();

            UpdateRorRegistryDataset::log(
                "Downloading chunk {$this->startByte}-{$this->endByte} to {$this->chunkFile}",
                $this->scheduledTaskLogFilesPath,
                ScheduledTaskHelper::SCHEDULED_TASK_MESSAGE_TYPE_NOTICE
            );

            $response = $client->request('GET', $this->downloadUrl, [
                'headers' => ['Range' => "bytes={$this->startByte}-{$this->endByte}"],
                'sink' => fopen($this->chunkFile, 'wb'),
                'connect_timeout' => 10,
                'timeout' => $this->timeout - 1, // set the download time 1 second less than the job timeout
            ]);

            if ($response->getStatusCode() !== 206 || !$this->fileManager->fileExists($this->chunkFile)) {
                throw new Exception('Failed to download chunk');
            }

            UpdateRorRegistryDataset::log(
                "Downloading chunk {$this->startByte}-{$this->endByte} to {$this->chunkFile} completed successfully",
                $this->scheduledTaskLogFilesPath,
                ScheduledTaskHelper::SCHEDULED_TASK_MESSAGE_TYPE_NOTICE
            );

        } catch (GuzzleException|Exception $e) {
            UpdateRorRegistryDataset::log(
                "Downloading chunk {$this->startByte}-{$this->endByte} to {$this->chunkFile} failed: {$e->getMessage()}",
                $this->scheduledTaskLogFilesPath,
                ScheduledTaskHelper::SCHEDULED_TASK_MESSAGE_TYPE_ERROR
            );
            
            throw $e;
        }
    }
}

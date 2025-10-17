<?php

/**
 * @file jobs/ror/DownloadRoRDatasetInSync.php
 *
 * Copyright (c) 2014-2025 Simon Fraser University
 * Copyright (c) 2000-2025 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class DownloadRoRDatasetInSync
 *
 * @brief Job to download and extract ROR dataset in sync as fallback of chunk download
 */

namespace PKP\jobs\ror;

use Exception;
use ZipArchive;
use PKP\jobs\BaseJob;
use APP\core\Application;
use PKP\file\PrivateFileManager;
use PKP\task\UpdateRorRegistryDataset;
use GuzzleHttp\Exception\GuzzleException;
use PKP\scheduledTask\ScheduledTaskHelper;
use Illuminate\Queue\Middleware\Skip;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\Cache;

class DownloadRoRDatasetInSync extends BaseJob
{
    /**
     * The maximum number of SECONDS a job should get processed before consider failed
     */
    public int $timeout = 300;

    /**
     * Indicate if the job should be marked as failed on timeout.
     */
    public bool $failOnTimeout = false;

    /**
     * The number of SECONDS to wait before retrying the job.
     */
    public int $backoff = 60;

    protected PrivateFileManager $fileManager;

    public function __construct(
        protected string $csvNameContains,
        protected string $downloadUrl, 
        protected string $pathZipFile, 
        protected string $pathZipDir,
        protected string $scheduledTaskLogFilePath
    )
    {
        parent::__construct();
        $this->fileManager = new PrivateFileManager();
    }

    /**
     * Get the middleware the job should pass through.
     */
    public function middleware(): array
    {
        return [
            Skip::when(function (): bool {
                $pathCsv = $this->fileManager->fileExists($this->pathZipDir, 'dir')
                    ? UpdateRorRegistryDataset::getPathCsv($this->pathZipDir, $this->csvNameContains)
                    : '';
                
                if (!empty($pathCsv) && $this->fileManager->fileExists($pathCsv)) {
                    UpdateRorRegistryDataset::writeToExecutionLogFile(
                        'Cancelling sync download as the CSV already exists',
                        $this->scheduledTaskLogFilePath, 
                        ScheduledTaskHelper::SCHEDULED_TASK_MESSAGE_TYPE_NOTICE
                    );
                    return true; // No need to download the CSV file, job can skipped
                }

                UpdateRorRegistryDataset::writeToExecutionLogFile(
                    'Proceeding with sync download as the CSV file not found',
                    $this->scheduledTaskLogFilePath, 
                    ScheduledTaskHelper::SCHEDULED_TASK_MESSAGE_TYPE_ERROR
                );
                
                return false; // Need to download the CSV file
            }),
            (new WithoutOverlapping($this->pathZipFile . ':sync'))->expireAfter(600), // 10-minute lock
        ];
    }

    public function handle()
    {
        try {

            // if the chunk process still ongoing,
            // we will release back the job in the queue for next attempt
            if (Cache::has(UpdateRorRegistryDataset::CACHE_KEY_CHUNK_BATCH) 
                && $this->attempts() <= $this->tries
            ) {
                UpdateRorRegistryDataset::writeToExecutionLogFile(
                    "Chunk batch not complete, redispatching DownloadRoRDatasetInSync with attempt {$this->attempts()}",
                    $this->scheduledTaskLogFilePath,
                    ScheduledTaskHelper::SCHEDULED_TASK_MESSAGE_TYPE_NOTICE
                );
                $this->release($this->backoff);
            }

            UpdateRorRegistryDataset::cleanup([$this->pathZipFile, $this->pathZipDir]);

            UpdateRorRegistryDataset::writeToExecutionLogFile(
                'Downloading ROR dataset synchronously',
                $this->scheduledTaskLogFilePath,
                ScheduledTaskHelper::SCHEDULED_TASK_MESSAGE_TYPE_NOTICE
            );

            $client = Application::get()->getHttpClient();
            $response = $client->request('GET', $this->downloadUrl, [
                'sink' => fopen($this->pathZipFile, 'wb'),
                'connect_timeout' => 10,
                'timeout' => $this->timeout - 10, // set the download time 10 second less than the job timeout
            ]);

            if ($response->getStatusCode() !== 200
                || !$this->fileManager->fileExists($this->pathZipFile)
            ) {
                throw new Exception('Failed to download ROR dataset');
            }

            $zip = new ZipArchive();
            if ($zip->open($this->pathZipFile) !== true) {
                throw new Exception('Failed to open ZIP file');
            }
            $zip->extractTo($this->pathZipDir);
            $zip->close();

            if (!$this->fileManager->fileExists($this->pathZipDir, 'dir')) {
                throw new Exception('Extraction failed');
            }

            $pathCsv = UpdateRorRegistryDataset::getPathCsv($this->pathZipDir, $this->csvNameContains);
            if (empty($pathCsv) || !$this->fileManager->fileExists($pathCsv)) {
                throw new Exception('CSV file not found');
            }

            // If all ok, we can remove the .zip file as we only need the extracted directory now
            UpdateRorRegistryDataset::cleanup([$this->pathZipFile]);

        } catch (GuzzleException|Exception $e) {
            UpdateRorRegistryDataset::cleanup([$this->pathZipFile, $this->pathZipDir]);
            UpdateRorRegistryDataset::writeToExecutionLogFile(
                "Synchronous download failed: {$e->getMessage()}",
                $this->scheduledTaskLogFilePath,
                ScheduledTaskHelper::SCHEDULED_TASK_MESSAGE_TYPE_ERROR
            );
            throw $e;
        }
    }
}

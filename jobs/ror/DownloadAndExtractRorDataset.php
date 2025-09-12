<?php

/**
 * @file jobs/ror/DownloadAndExtractRorDataset.php
 *
 * Copyright (c) 2014-2025 Simon Fraser University
 * Copyright (c) 2000-2025 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class DownloadAndExtractRorDataset
 *
 * @brief Job to download and extract ROR dataset.
 */

namespace PKP\jobs\ror;

use Exception;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Throwable;
use PKP\jobs\BaseJob;
use APP\core\Application;
use Illuminate\Bus\Batch;
use PKP\file\PrivateFileManager;
use Illuminate\Support\Facades\Bus;
use PKP\task\UpdateRorRegistryDataset;
use GuzzleHttp\Exception\GuzzleException;
use PKP\scheduledTask\ScheduledTaskHelper;
use PKP\jobs\ror\DownloadRoRDatasetInChunks;
use Illuminate\Support\Facades\Cache;

class DownloadAndExtractRorDataset extends BaseJob
{
    /**
     * The maximum number of SECONDS a job should get processed before consider failed
     */
    public int $timeout = 300;

    protected PrivateFileManager $fileManager;

    public function __construct(
        protected string $downloadUrl, 
        protected string $csvNameContains, 
        protected string $prefix,
        protected string $scheduledTaskLogFilePath
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
            (new WithoutOverlapping($this->prefix . ':download'))->expireAfter(300), // 5 mins lock
        ];
    }

    public function handle()
    {
        $pathZipDir = $this->fileManager->getBasePath() . DIRECTORY_SEPARATOR . $this->prefix;
        $pathZipFile = $pathZipDir . '.zip';
        $chunkDir = $this->fileManager->getBasePath() . DIRECTORY_SEPARATOR . 'rorChunks';

        try {
            // Clean up any existing files
            UpdateRorRegistryDataset::cleanup([$pathZipFile, $pathZipDir, $chunkDir]);
            Cache::forget(UpdateRorRegistryDataset::CACHE_KEY_CHUNK_BATCH);

            [$fileSize, $supportsRange] = $this->getFileSizeAndVerifyRangeDownload($this->downloadUrl);

            // if can not determine file size or range download support, 
            // we will not kick off the chunk download process and exit this job process
            if ($fileSize === 0 || !$supportsRange) {
                UpdateRorRegistryDataset::writeToExecutionLogFile(
                    "Unable to determine file size or range download support, cancelling the chunk download process",
                    $this->scheduledTaskLogFilePath,
                    ScheduledTaskHelper::SCHEDULED_TASK_MESSAGE_TYPE_NOTICE
                );
                return;
            }

            $this->performChunkDownload(
                $pathZipDir,
                $pathZipFile,
                $this->downloadUrl,
                $chunkDir,
                $fileSize
            );

        } catch (Throwable $e) {
            Cache::forget(UpdateRorRegistryDataset::CACHE_KEY_CHUNK_BATCH);
            UpdateRorRegistryDataset::cleanup([$pathZipFile, $pathZipDir]);
            UpdateRorRegistryDataset::writeToExecutionLogFile(
                "RoR dataset downloading in chunk and extract main job have failed: {$e->getMessage()}",
                $this->scheduledTaskLogFilePath,
                ScheduledTaskHelper::SCHEDULED_TASK_MESSAGE_TYPE_ERROR
            );
            throw $e;
        }
    }

    /**
     * Dispatch the chunk download batch jobs and chunks merge job.
     */
    protected function performChunkDownload(
        string $pathZipDir,
        string $pathZipFile,
        string $downloadUrl,
        string $chunkDir,
        int $fileSize
    )
    {
        try {

            if (!$this->fileManager->mkdir($chunkDir)) {
                throw new Exception('Failed to create chunk directory');
            }

            $chunkSize = 1024 * 1024 * 3 ; // 3 MB
            $numChunks = ceil($fileSize / $chunkSize);
            $jobs = [];

            for ($i = 0; $i < $numChunks; $i++) {
                $startByte = $i * $chunkSize;
                $endByte = min(($i + 1) * $chunkSize - 1, $fileSize - 1);
                $chunkFile = $chunkDir . "/chunk_{$i}";
                $jobs[] = new DownloadRoRDatasetInChunks(
                    $downloadUrl,
                    $startByte,
                    $endByte,
                    $chunkFile,
                    $pathZipDir,
                    $this->csvNameContains,
                    $this->scheduledTaskLogFilePath
                );
            }

            // we need to get these in local variables to pass into the batch closures(e.g. then, catch, etc)
            // as the serialization of $this in closure is buggy and can lead to issues
            $prefix = $this->prefix;
            $csvNameContains = $this->csvNameContains;
            $scheduledTaskLogFilePath = $this->scheduledTaskLogFilePath;

            // Dispatch batch
            Bus::batch($jobs)
                ->then(function (Batch $batch) use ($pathZipDir, $pathZipFile, $chunkDir, $fileSize, $chunkSize, $prefix, $csvNameContains, $scheduledTaskLogFilePath) {
                    // after successfully completing all the batch jobs, 
                    // first we need to verify if all chunks downloaded
                    $chunks = glob($chunkDir . '/chunk_*');

                    if (count($chunks) !== (int) ceil($fileSize / $chunkSize)) {
                        UpdateRorRegistryDataset::writeToExecutionLogFile(
                            'Not all chunks downloaded, cancelling batch chunk downloading',
                            $scheduledTaskLogFilePath,
                            ScheduledTaskHelper::SCHEDULED_TASK_MESSAGE_TYPE_ERROR
                        );

                        return; // Skip merging, rely on finally block
                    }

                    UpdateRorRegistryDataset::writeToExecutionLogFile(
                        'All chunks downloaded successfully, proceeding with merging and extraction',
                        $scheduledTaskLogFilePath,
                        ScheduledTaskHelper::SCHEDULED_TASK_MESSAGE_TYPE_COMPLETED
                    );

                    // chunks downloaded successfully
                    // Now merge those to get the final zip and extract to get the proper directory.
                    // This is dispatch as sync so that we can ensure the order of operations as
                    // just dispatch a new job will be outside of main calling of chain which can lead
                    // to case where next job in chain starts before this one finish
                    MergeAndExtractRorDatasetChunks::dispatchSync(
                        $prefix,
                        $csvNameContains,
                        $pathZipDir,
                        $pathZipFile,
                        $chunkDir,
                        $scheduledTaskLogFilePath
                    );

                    Cache::forget(UpdateRorRegistryDataset::CACHE_KEY_CHUNK_BATCH);
                })
                ->catch(function (Batch $batch, Throwable $e) use ($scheduledTaskLogFilePath) {
                    UpdateRorRegistryDataset::writeToExecutionLogFile(
                        "RoR dataset chunk download batch at progress of {$batch->progress()}% have failed: {$e->getMessage()}",
                        $scheduledTaskLogFilePath,
                        ScheduledTaskHelper::SCHEDULED_TASK_MESSAGE_TYPE_ERROR
                    );
                })
                ->finally(function (Batch $batch) use ($prefix, $downloadUrl, $pathZipFile, $pathZipDir, $chunkDir, $csvNameContains, $scheduledTaskLogFilePath) {
                    UpdateRorRegistryDataset::cleanup([$pathZipFile, $chunkDir]);

                    Cache::put(UpdateRorRegistryDataset::CACHE_KEY_CHUNK_BATCH, true, 600);

                    UpdateRorRegistryDataset::writeToExecutionLogFile(
                        'RoR dataset downloading in chunk batch jobs have finished.', 
                        $scheduledTaskLogFilePath,
                        ScheduledTaskHelper::SCHEDULED_TASK_MESSAGE_TYPE_NOTICE
                    );
                })
                ->name('download.ror.dataset.chunks')
                ->dispatch();
        } catch (Throwable $e) {
            Cache::forget(UpdateRorRegistryDataset::CACHE_KEY_CHUNK_BATCH);
            UpdateRorRegistryDataset::cleanup([$pathZipDir, $pathZipFile, $chunkDir]);
            UpdateRorRegistryDataset::writeToExecutionLogFile(
                "RoR dataset downloading in chunk batch jobs have failed: {$e->getMessage()}",
                $this->scheduledTaskLogFilePath,
                ScheduledTaskHelper::SCHEDULED_TASK_MESSAGE_TYPE_ERROR
            );
            throw $e;
        }
    }

    /**
     * Get the target file size and verify range download support.
     */
    protected function getFileSizeAndVerifyRangeDownload(string $downloadUrl): array
    {
        try {
            $client = Application::get()->getHttpClient();
            $response = $client->request('HEAD', $downloadUrl, [
                'connect_timeout' => 10,
                'headers' => ['Range' => 'bytes=0-1023'],
            ]);

            $contentLength = $response->getHeaderLine('Content-Length');
            $supportsRange = $response->getStatusCode() === 206 || $response->hasHeader('Accept-Ranges');

            return [$contentLength ? (int) $contentLength : 0, $supportsRange];
        } catch (GuzzleException|Exception $e) {
            UpdateRorRegistryDataset::writeToExecutionLogFile(
                "Failed to get file size: {$e->getMessage()}",
                $this->scheduledTaskLogFilePath,
                ScheduledTaskHelper::SCHEDULED_TASK_MESSAGE_TYPE_ERROR
            );

            return [0, false];
        }
    }
}

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

class DownloadAndExtractRorDataset extends BaseJob
{
    /**
     * The maximum number of SECONDS a job should get processed before consider failed
     */
    public int $timeout = 600;

    protected PrivateFileManager $fileManager;

    public function __construct(
        protected string $downloadUrl, 
        protected string $csvNameContains, 
        protected string $prefix,
        protected ?string $scheduledTaskLogFilesPath = null
    )
    {
        parent::__construct();
        $this->fileManager = new PrivateFileManager();
    }

    public function middleware()
    {
        return [(new WithoutOverlapping($this->prefix . ':download'))->expireAfter(600)]; // Unique key
    }

    public function handle()
    {
        $pathZipDir = $this->fileManager->getBasePath() . DIRECTORY_SEPARATOR . $this->prefix;
        $pathZipFile = $pathZipDir . '.zip';
        $chunkDir = $this->fileManager->getBasePath() . DIRECTORY_SEPARATOR . 'rorChunks';

        try {
            // Clean up any existing files
            UpdateRorRegistryDataset::cleanup([$pathZipFile, $pathZipDir, $chunkDir]);

            $fileSize = $this->getFileSize($this->downloadUrl);

            if ($fileSize === 0) {
                throw new Exception('Failed to determine file size');
            }

            $this->performChunkDownload(
                $pathZipDir,
                $pathZipFile,
                $this->downloadUrl,
                $chunkDir,
                $fileSize
            );

        } catch (Throwable $e) {
            UpdateRorRegistryDataset::cleanup([$pathZipFile, $pathZipDir]);
            UpdateRorRegistryDataset::log(
                "RoR dataset downloading in chunk and extract main job have failed: {$e->getMessage()}",
                $this->scheduledTaskLogFilesPath,
                ScheduledTaskHelper::SCHEDULED_TASK_MESSAGE_TYPE_ERROR
            );
            throw $e;
        }
    }

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
                    $this->scheduledTaskLogFilesPath
                );
            }

            // we need to get thses in local variables to pass into the batch closures(e.g. then, catch, etc)
            // as the serialization of $this in closure is buggy and can lead to issues
            $prefix = $this->prefix;
            $csvNameContains = $this->csvNameContains;
            $scheduledTaskLogFilesPath = $this->scheduledTaskLogFilesPath;

            // Dispatch batch
            Bus::batch($jobs)
                ->then(function (Batch $batch) use ($pathZipDir, $pathZipFile, $chunkDir, $fileSize, $chunkSize, $prefix, $csvNameContains, $scheduledTaskLogFilesPath) {
                    // after successfully completing all the batch jobs, 
                    // first we need to verify if all chunks downloaded
                    $chunks = glob($chunkDir . '/chunk_*');

                    if (count($chunks) !== (int) ceil($fileSize / $chunkSize)) {
                        // not all chunks downloaded
                        // we will not attempt any more chunk downloads and cancel batch process
                        // and fall back to sync download
                        $batch->cancel();

                        UpdateRorRegistryDataset::log(
                            'Not all chunks downloaded, cancelling batch chunk downloading',
                            $scheduledTaskLogFilesPath,
                            ScheduledTaskHelper::SCHEDULED_TASK_MESSAGE_TYPE_ERROR
                        );

                        return;
                    }

                    // chunk downloaded successfully
                    // Now merge those to get the final zip and extract to get the proper directory
                    MergeAndExtractRorDatasetChunks::dispatchSync(
                        $prefix,
                        $csvNameContains,
                        $pathZipDir,
                        $pathZipFile,
                        $chunkDir,
                        $scheduledTaskLogFilesPath
                    );
                })
                ->catch(function (Batch $batch, Throwable $e) use ($scheduledTaskLogFilesPath) {
                    UpdateRorRegistryDataset::log(
                        "RoR dataset chunk download batch at progress of {$batch->progress()}% have failed: {$e->getMessage()}",
                        $scheduledTaskLogFilesPath,
                        ScheduledTaskHelper::SCHEDULED_TASK_MESSAGE_TYPE_ERROR
                    );
                })
                ->finally(function (Batch $batch) use ($downloadUrl, $pathZipFile, $pathZipDir, $chunkDir, $csvNameContains, $scheduledTaskLogFilesPath) {
                    UpdateRorRegistryDataset::cleanup([$pathZipFile, $chunkDir]);
                    UpdateRorRegistryDataset::log(
                        'RoR dataset downloading in chunk batch jobs have finished.', 
                        $scheduledTaskLogFilesPath,
                        ScheduledTaskHelper::SCHEDULED_TASK_MESSAGE_TYPE_NOTICE
                    );
                })
                ->dispatch();
        } catch (Throwable $e) {
            UpdateRorRegistryDataset::cleanup([$pathZipDir, $pathZipFile, $chunkDir]);
            UpdateRorRegistryDataset::log(
                "RoR dataset downloading in chunk batch jobs have failed: {$e->getMessage()}",
                $this->scheduledTaskLogFilesPath,
                ScheduledTaskHelper::SCHEDULED_TASK_MESSAGE_TYPE_ERROR
            );
        }
    }

    protected function getFileSize(string $downloadUrl): int
    {
        try {
            $client = Application::get()->getHttpClient();
            $response = $client->request('HEAD', $downloadUrl, [
                'connect_timeout' => 10
            ]);
            $contentLength = $response->getHeaderLine('Content-Length');
            return $contentLength ? (int) $contentLength : 0;
        } catch (GuzzleException|Exception $e) {
            UpdateRorRegistryDataset::log(
                "Failed to get file size: {$e->getMessage()}",
                $this->scheduledTaskLogFilesPath,
                ScheduledTaskHelper::SCHEDULED_TASK_MESSAGE_TYPE_ERROR
            );
            return 0;
        }
    }
}

<?php

/**
 * @file jobs/statistics/ArchiveUsageStatsLogFile.php
 *
 * Copyright (c) 2024 Simon Fraser University
 * Copyright (c) 2024 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class ArchiveUsageStatsLogFile
 *
 * @ingroup jobs
 *
 * @brief Archive usage stats log file.
 */

namespace PKP\jobs\statistics;

use APP\statistics\StatisticsHelper;
use Exception;
use PKP\file\FileManager;
use PKP\job\exceptions\JobException;
use PKP\jobs\BaseJob;
use PKP\site\Site;
use PKP\task\FileLoader;

class ArchiveUsageStatsLogFile extends BaseJob
{
    /**
     * The load ID = usage stats log file name
     */
    protected string $loadId;

    protected Site $site;

    /**
     * Create a new job instance.
     */
    public function __construct(string $loadId, Site $site)
    {
        parent::__construct();
        $this->loadId = $loadId;
        $this->site = $site;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        // Move the archived file back to staging
        $filename = $this->loadId;
        $archiveFilePath = StatisticsHelper::getUsageStatsDirPath() . '/' . FileLoader::FILE_LOADER_PATH_ARCHIVE . '/' . $filename;
        $dispatchFilePath = StatisticsHelper::getUsageStatsDirPath() . '/' . FileLoader::FILE_LOADER_PATH_DISPATCH . '/' . $filename;
        if (!rename($dispatchFilePath, $archiveFilePath)) {
            $message = __(
                'admin.job.archiveLogFile.error',
                [
                    'file' => $filename,
                    'dispatchFilePath' => $dispatchFilePath,
                    'archivedFilePath' => $archiveFilePath
                ]
            );
            throw new JobException($message);
        }
        if ($this->site->getData('compressStatsLogs')) {
            try {
                $fileMgr = new FileManager();
                $fileMgr->gzCompressFile($archiveFilePath);
            } catch (Exception $e) {
                error_log($e);
            }
        }
    }
}

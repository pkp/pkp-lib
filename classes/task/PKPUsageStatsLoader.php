<?php

/**
 * @file classes/tasks/PKPUsageStatsLoader.php
 *
 * Copyright (c) 2022 Simon Fraser University
 * Copyright (c) 2022 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PKPUsageStatsLoader
 *
 * @ingroup tasks
 *
 * @brief Scheduled task to extract transform and load usage statistics data into database.
 */

namespace PKP\task;

use APP\core\Application;
use APP\statistics\StatisticsHelper;
use Illuminate\Support\Facades\Bus;
use PKP\file\FileManager;
use PKP\jobs\statistics\CompileMonthlyMetrics;
use PKP\scheduledTask\ScheduledTaskHelper;
use PKP\site\Site;
use Throwable;

abstract class PKPUsageStatsLoader extends FileLoader
{
    /**
     * If the log files should be automatically moved to te stage folder.
     * This is the case for daily log file processing.
     * This is not the case if the whole month is reprocessed - all log files for the given month should be manually placed in the stage folder.
     */
    private bool $autoStage;

    /** List of months the processed daily log files are from, to consider for monthly aggregation */
    private array $months = [];

    /** List of log files that needs to be processed within this scheduled task, and the jobs needs to be chained for. */
    private array $logFiles = [];

    /**
     * Constructor.
     */
    public function __construct(array $args)
    {
        $this->autoStage = true;

        // if log files for a whole month should be reprocessed,
        // the month is given as parameter
        if (!empty($args)) {
            $reprocessMonth = current($args);
            $reprocessFiles = $this->getStagedFilesByMonth($reprocessMonth);
            $this->setOnlyConsiderFiles($reprocessFiles);
            $this->autoStage = false;
        }

        // shall the archived log files be compressed
        $site = Application::get()->getRequest()->getSite();
        if ($site->getData('compressStatsLogs')) {
            $this->setCompressArchives(true);
        }

        // Define the base filesystem path.
        $basePath = StatisticsHelper::getUsageStatsDirPath();
        $args[0] = $basePath;
        parent::__construct($args);

        $this->checkFolderStructure(true);
    }

    /**
     * @copydoc FileLoader::getName()
     */
    public function getName(): string
    {
        return __('admin.scheduledTask.usageStatsLoader');
    }

    /**
     * Get the jobs needed to process a usage stats log file and compile the stats.
     * The jobs have to be in the right execution order.
     *
     * @return BaseJob[]
     */
    abstract protected function getFileJobs(string $filePath, Site $site): array;

    /**
     * @copydoc FileLoader::executeActions()
     */
    protected function executeActions(): bool
    {
        // It's possible that the processing directory has files that
        // were being processed but the php process was stopped before
        // finishing the processing, or there may be a concurrent process running.
        // Warn the user if this is the case.
        $processingDirFiles = glob($this->getProcessingPath() . '/' . '*');
        $processingDirError = is_array($processingDirFiles) && count($processingDirFiles);
        // If the processing directory is not empty (and this is not the reprocessing of the older log files)
        // log that message
        if ($processingDirError && !empty($this->getOnlyConsiderFiles())) {
            $this->addExecutionLogEntry(__('admin.scheduledTask.usageStatsLoader.processingPathNotEmpty', ['directory' => $this->getProcessingPath()]), ScheduledTaskHelper::SCHEDULED_TASK_MESSAGE_TYPE_ERROR);
        }
        if ($this->autoStage) {
            $this->autoStage();
        }
        $processFilesResult = parent::executeActions();

        $site = Application::get()->getRequest()->getSite();
        $jobs = [];
        foreach ($this->logFiles as $filePath) {
            $jobsPerFile = $this->getFileJobs($filePath, $site);
            $jobs = array_merge($jobs, $jobsPerFile);
        }
        foreach ($this->months as $month) {
            $compileMonthlyMetricsJob = new CompileMonthlyMetrics($month, $site);
            $jobs = array_merge($jobs, [$compileMonthlyMetricsJob]);
        }
        // Bus::chain() cannot accept an empty array
        if (!empty($jobs)) {
            Bus::chain($jobs)
                ->catch(function (Throwable $e) {
                })
                ->dispatch();

            $this->addExecutionLogEntry(__(
                'admin.scheduledTask.usageStatsLoader.jobDispatched'
            ), ScheduledTaskHelper::SCHEDULED_TASK_MESSAGE_TYPE_NOTICE);
        }

        return (!$processingDirError && $processFilesResult);
    }

    /**
     * Check if the log file's date is later than the first installation of the new log file format,
     * so that the log file can be processed.
     */
    protected function isDateValid(string $loadId): bool
    {
        $date = substr($loadId, -12, 8);
        // Get the date when the version that uses the new log file format (and COUNTER R5) is installed.
        // Only the log files later than that day can be (regularly) processed here.
        $statsService = app()->get('sushiStats');
        $dateR5Installed = date('Ymd', strtotime($statsService->getEarliestDate()));
        if ($date < $dateR5Installed) {
            // the log file is in old log file format
            // return the file to staging and
            // log the error
            $this->addExecutionLogEntry(__(
                'admin.scheduledTask.usageStatsLoader.veryOldLogFile',
                ['file' => $loadId]
            ), ScheduledTaskHelper::SCHEDULED_TASK_MESSAGE_TYPE_ERROR);
            return false;
        }
        return true;
    }

    /**
     * Check if stats for the log file's month do not already exist.
     * Return true if they do not exist, so that log file can be processed.
     * Else, return the file to staging and log the error that
     * the CLI script for reprocessing should be called.
     * If the log files of the month are being reprocessed,
     * the CLI reprocessing script will first remove all the stats for the month,
     * so that this function will return true in that case.
     */
    protected function isMonthValid(string $loadId, string $month): bool
    {
        $currentMonth = date('Ym');
        $lastMonth = date('Ym', strtotime('last month'));
        $site = Application::get()->getRequest()->getSite();
        // If the daily metrics are not kept, and this is not the current month (which is kept in the DB)
        // the CLI script to reprocess the whole month should be called.
        if (!$site->getData('keepDailyUsageStats') && $month != $currentMonth && $month != $lastMonth) {
            $statsService = app()->get('sushiStats');
            $counterMonthExists = $statsService->monthExists($month);
            $geoService = app()->get('geoStats');
            $geoMonthExists = $geoService->monthExists($month);
            if ($counterMonthExists || $geoMonthExists) {
                $this->addExecutionLogEntry(__(
                    'admin.scheduledTask.usageStatsLoader.monthExists',
                    ['file' => $loadId]
                ), ScheduledTaskHelper::SCHEDULED_TASK_MESSAGE_TYPE_ERROR);
                return false;
            }
        }
        return true;
    }

    /**
     * Add the log file's month to the list of months to be considered for the
     * stats aggregation after the current log files are processed.
     */
    protected function considerMonthForStatsAggregation(string $month): void
    {
        if (!in_array($month, $this->months)) {
            $this->months[] = $month;
        }
    }

    /**
     * @copydoc FileLoader::processFile()
     * The file name MUST be of form usage_events_YYYYMMDD.log
     * If the function successfully finishes, the file will be archived.
     */
    protected function processFile(string $filePath): bool|int
    {
        $loadId = basename($filePath);
        $month = substr($loadId, -12, 6);
        // if the file is not being reprocessed using the CLI tool
        if (!in_array($loadId, $this->getOnlyConsiderFiles())) {
            // Check if the log file is an old log file and if the stats for the month already exist
            if (!$this->isDateValid($loadId) || !$this->isMonthValid($loadId, $month)) {
                return self::FILE_LOADER_RETURN_TO_STAGING;
            }
        }
        // Add this log file to the list, so that all jobs, for all files can be chained.
        $this->logFiles[] = $loadId;
        // Add this log file's month to the list of months the stats need to be aggregated for.
        $this->considerMonthForStatsAggregation($month);
        return self::FILE_LOADER_RETURN_TO_DISPATCH;
    }

    /**
     * Auto stage usage stats log files, also moving files that
     * might be in processing folder to stage folder.
     */
    protected function autoStage(): void
    {
        // Copy all log files to stage directory, except the current day one.
        $fileManager = new FileManager();
        $logFiles = [];
        $logsDirFiles = glob($this->getUsageEventLogsPath() . '/*');
        if (is_array($logsDirFiles)) {
            $logFiles = array_merge($logFiles, $logsDirFiles);
        }
        // It's possible that the processing directory have files that
        // were being processed but the php process was stopped before
        // finishing the processing. Just copy them to the stage directory too.
        $processingDirFiles = glob($this->getProcessingPath() . '/*');
        if (is_array($processingDirFiles)) {
            $logFiles = array_merge($logFiles, $processingDirFiles);
        }

        foreach ($logFiles as $filePath) {
            if ($fileManager->fileExists($filePath)) {
                $filename = pathinfo($filePath, PATHINFO_BASENAME);
                $currentDayFilename = $this->getUsageEventCurrentDayLogName();
                if ($filename == $currentDayFilename) {
                    continue;
                }
                $this->moveFile(pathinfo($filePath, PATHINFO_DIRNAME), $this->getStagePath(), $filename);
            }
        }
    }

    /**
     * Get staged usage log files belonging to a month, that should be reprocessed
     */
    protected function getStagedFilesByMonth(string $month): array
    {
        $files = [];
        $stagePath = StatisticsHelper::getUsageStatsDirPath() . '/' . self::FILE_LOADER_PATH_STAGING;
        $stageDir = opendir($stagePath);
        while ($filename = readdir($stageDir)) {
            if (str_starts_with($filename, 'usage_events_' . $month)) {
                $files[] = $filename;
            }
        }
        return $files;
    }

    /**
     * Get the usage event logs directory path.
     */
    protected function getUsageEventLogsPath(): string
    {
        return StatisticsHelper::getUsageStatsDirPath() . '/usageEventLogs';
    }

    /**
     * Get current day usage event log name.
     */
    protected function getUsageEventCurrentDayLogName(): string
    {
        return 'usage_events_' . date('Ymd') . '.log';
    }
}

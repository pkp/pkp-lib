<?php

/**
 * @file classes/tasks/PKPUsageStatsLoader.php
 *
 * Copyright (c) 2013-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PKPUsageStatsLoader
 * @ingroup tasks
 *
 * @brief Scheduled task to extract transform and load usage statistics data into database.
 */

namespace PKP\task;

use APP\core\Application;
use APP\core\Services;
use APP\Jobs\Statistics\LoadMetricsDataJob;
use APP\Jobs\Statistics\LoadMonthlyMetricsDataJob;
use APP\statistics\StatisticsHelper;
use PKP\core\Core;
use PKP\file\FileManager;
use PKP\scheduledTask\ScheduledTaskHelper;

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

        // shall the archived log files be ompressed
        $site = Application::get()->getRequest()->getSite();
        if ($site->getData('archivedUsageStatsLogFiles') == 1) {
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
        foreach ($this->months as $month) {
            dispatch(new LoadMonthlyMetricsDataJob($month));
        }
        return ($processFilesResult && !$processingDirError);
    }

    /**
     * Delete entries in usage stats temporary tables by loadId
     */
    abstract protected function deleteByLoadId(string $loadId): void;
    /**
     * Insert usage stats log entry into temporary tables
     */
    abstract protected function insertTemporaryUsageStatsData(object $entry, int $lineNumber, string $loadId): void;
    /**
     * Check foreign keys from the usage stats log entry
     */
    abstract protected function checkForeignKeys(object $entry): array;
    /**
     * Get valid assoc types that an usage event can contain
     */
    abstract protected function getValidAssocTypes(): array;
    /**
     * Validate the usage stats log entry
     *
     * @throws \Exception.
     */
    protected function isLogEntryValid(object $entry): void
    {
        if (!$this->validateDate($entry->time)) {
            throw new \Exception(__('admin.scheduledTask.usageStatsLoader.invalidLogEntry.time'));
        }
        // check hashed IP ?
        // check canonicalUrl ?
        if (!is_int($entry->contextId)) {
            throw new \Exception(__('admin.scheduledTask.usageStatsLoader.invalidLogEntry.contextId'));
        } else {
            $contextAssocType = Application::getContextAssocType();
            if ($entry->assocType == $contextAssocType && $entry->assocId != $entry->contextId) {
                throw new \Exception(__('admin.scheduledTask.usageStatsLoader.invalidLogEntry.contextAssocTypeNoMatch'));
            }
        }
        if (!empty($entry->submissionId)) {
            if (!is_int($entry->submissionId)) {
                throw new \Exception(__('admin.scheduledTask.usageStatsLoader.invalidLogEntry.submissionId'));
            } else {
                if ($entry->assocType == Application::ASSOC_TYPE_SUBMISSION && $entry->assocId != $entry->submissionId) {
                    throw new \Exception(__('admin.scheduledTask.usageStatsLoader.invalidLogEntry.submissionAssocTypeNoMatch'));
                }
            }
        }

        $validAssocTypes = $this->getValidAssocTypes();
        if (!in_array($entry->assocType, $validAssocTypes)) {
            throw new \Exception(__('admin.scheduledTask.usageStatsLoader.invalidLogEntry.assocType'));
        }
        if (!is_int($entry->assocId)) {
            throw new \Exception(__('admin.scheduledTask.usageStatsLoader.invalidLogEntry.assocId'));
        }
        $validFileTypes = [
            StatisticsHelper::STATISTICS_FILE_TYPE_PDF,
            StatisticsHelper::STATISTICS_FILE_TYPE_DOC,
            StatisticsHelper::STATISTICS_FILE_TYPE_HTML,
            StatisticsHelper::STATISTICS_FILE_TYPE_OTHER,
        ];
        if (!empty($entry->fileType) && !in_array($entry->fileType, $validFileTypes)) {
            throw new \Exception(__('admin.scheduledTask.usageStatsLoader.invalidLogEntry.fileType'));
        }
        if (!empty($entry->country) && (!ctype_alpha($entry->country) || !(strlen($entry->country) == 2))) {
            throw new \Exception(__('admin.scheduledTask.usageStatsLoader.invalidLogEntry.country'));
        }
        if (!empty($entry->region) && (!ctype_alnum($entry->region) || !(strlen($entry->region) <= 3))) {
            throw new \Exception(__('admin.scheduledTask.usageStatsLoader.invalidLogEntry.region'));
        }
        if (!is_array($entry->institutionIds)) {
            throw new \Exception(__('admin.scheduledTask.usageStatsLoader.invalidLogEntry.institutionIds'));
        }
    }

    /**
     * @copydoc FileLoader::processFile()
     * The file's entries MUST be ordered by date-time to successfully identify double-clicks and unique items.
     * The file name MUST be of form usage_events_YYYYMMDD.log
     */
    protected function processFile(string $filePath): bool|int
    {
        $fhandle = fopen($filePath, 'r');
        if (!$fhandle) {
            throw new \Exception(__('admin.scheduledTask.usageStatsLoader.openFileFailed', ['file' => $filePath]));
        }

        $loadId = basename($filePath);
        // get the date and month of this log file
        $logFileDate = substr($loadId, -12, 8);
        $month = substr($loadId, -12, 6);

        $currentMonth = date('Ym');

        // Get the date when the version that uses the new log file format (and COUNTER R5) is installed.
        // Only the log files later than that day can be (regularly) processed here.
        $statsService = Services::get('sushiStats');
        $dateR5Installed = date('Ymd', strtotime($statsService->getEarliestDate()));
        if ($logFileDate < $dateR5Installed) {
            // the log file is in old log file format
            // return the file to staging and
            // log the error
            // TO-DO: once we decided how the log files in the old format should be reprocessed, this might change
            $this->addExecutionLogEntry(__(
                'admin.scheduledTask.usageStatsLoader.veryOldLogFile',
                ['file' => $loadId]
            ), ScheduledTaskHelper::SCHEDULED_TASK_MESSAGE_TYPE_ERROR);
            return self::FILE_LOADER_RETURN_TO_STAGING;
        }

        $site = Application::get()->getRequest()->getSite();
        // If the daily metrics are not kept, and this is not the current month (which is kept in the DB)
        // the CLI script to reprocess the whole month should be called.
        if (!$site->getData('usageStatsKeepDaily') && $month != $currentMonth) {
            // Check if the month has already been processed,
            // currently only the table metrics_counter_submission_monthly will be considered.
            // TO-DO: once we decided how the log files in the old format should be reprocessed
            // this should eventually be adapted, because the metrics_submission_geo_monthly could contain also earlier months
            $monthExists = $statsService->monthExists($month);
            if ($monthExists) {
                // The month has already been processed
                // return the file to staging and
                // log the error that a script for reprocessing should be called for the whole month
                $this->addExecutionLogEntry(__(
                    'admin.scheduledTask.usageStatsLoader.monthExists',
                    ['file' => $loadId]
                ), ScheduledTaskHelper::SCHEDULED_TASK_MESSAGE_TYPE_ERROR);
                return self::FILE_LOADER_RETURN_TO_STAGING;
            }
        }

        // Consider the month for monthly aggregation
        if (!in_array($month, $this->months)) {
            $this->months[] = $month;
        }

        // Make sure we don't have any temporary records associated
        // with the current load id in database.
        $this->deleteByLoadId($loadId);

        $lineNumber = 0;
        while (!feof($fhandle)) {
            $lineNumber++;
            $line = trim(fgets($fhandle));
            if (empty($line) || substr($line, 0, 1) === '#') {
                continue;
            } // Spacing or comment lines. This actually should not occur in the new format.

            // Regex to parse the usageStats plugin's log access file, i.e. old log file format.
            // Only the log file of the installation/upgrade day can contain both, old and new log file formats, so maybe there is a better solution for this?
            $parseRegex = '/^(?P<ip>\S+) \S+ \S+ "(?P<date>.*?)" (?P<url>\S+) (?P<returnCode>\S+) "(?P<userAgent>.*?)"/';
            if (preg_match($parseRegex, $line, $m)) {
                // This is a line in the old logfile format. Log the message and skip the line.
                $this->addExecutionLogEntry(__('admin.scheduledTask.usageStatsLoader.oldLogfileFormat', ['file' => $loadId, 'lineNumber' => $lineNumber]), ScheduledTaskHelper::SCHEDULED_TASK_MESSAGE_TYPE_ERROR);
                continue;
            } else {
                $entryData = json_decode($line);
            }

            try {
                $this->isLogEntryValid($entryData);
            } catch (\Exception $e) {
                // reject the file if the entry in invalid ???
                throw new \Exception(__(
                    'admin.scheduledTask.usageStatsLoader.invalidLogEntry',
                    ['file' => $loadId, 'lineNumber' => $lineNumber, 'error' => $e->getMessage()]
                ));
            }

            // Avoid bots.
            if (Core::isUserAgentBot($entryData->userAgent)) {
                continue;
            }

            // Check the foreign key constraint violation
            $foreignKeyErrors = $this->checkForeignKeys($entryData);
            if (!empty($foreignKeyErrors)) {
                // Log the message and do not consider this line
                $missingForeignKeys = implode(', ', $foreignKeyErrors);
                $this->addExecutionLogEntry(__('admin.scheduledTask.usageStatsLoader.foreignKeyError', ['missingForeignKeys' => $missingForeignKeys, 'file' => $loadId, 'lineNumber' => $lineNumber]), ScheduledTaskHelper::SCHEDULED_TASK_MESSAGE_TYPE_ERROR);
            } else {
                $this->insertTemporaryUsageStatsData($entryData, $lineNumber, $loadId);
            }
        }
        fclose($fhandle);
        // Despatch the job that will process the usage stats data and store them
        dispatch(new LoadMetricsDataJob($loadId));
        $this->addExecutionLogEntry(__(
            'admin.scheduledTask.usageStatsLoader.jobDispatched',
            ['file' => $filePath]
        ), ScheduledTaskHelper::SCHEDULED_TASK_MESSAGE_TYPE_NOTICE);

        // The log file will be archived
        return true;
    }

    /**
     * Auto stage usage stats log files, also moving files that
     * might be in processing folder to stage folder.
     */
    protected function autoStage(): void
    {
        // Copy all log files to stage directory, except the current day one.
        $fileMgr = new FileManager();
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
            // Make sure it's a file.
            if ($fileMgr->fileExists($filePath)) {
                // Avoid current day file.
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
        $filesToConsider = [];
        $stagePath = StatisticsHelper::getUsageStatsDirPath() . '/' . self::FILE_LOADER_PATH_STAGING;
        $stageDir = opendir($stagePath);
        while ($filename = readdir($stageDir)) {
            if (str_starts_with($filename, 'usage_events_' . $month)) {
                $filesToConsider[] = $filename;
            }
        }
        return $filesToConsider;
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

    /**
     * Validate date, check if the date is a valid date and in requested format
     */
    protected function validateDate(string $datetime, string $format = 'Y-m-d H:i:s'): bool
    {
        $d = \DateTime::createFromFormat($format, $datetime);
        return $d && $d->format($format) === $datetime;
    }
}

if (!PKP_STRICT_MODE) {
    class_alias('\PKP\task\PKPUsageStatsLoader', '\PKPUsageStatsLoader');
}

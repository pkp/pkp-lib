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
use APP\Jobs\Statistics\CompileMonthlyMetrics;
use APP\Jobs\Statistics\CompileUsageStatsFromTemporaryRecords;
use APP\statistics\StatisticsHelper;
use APP\statistics\TemporaryItemInvestigationsDAO;
use APP\statistics\TemporaryItemRequestsDAO;
use APP\statistics\TemporaryTotalsDAO;
use DateTime;
use Exception;
use PKP\core\Core;
use PKP\db\DAORegistry;
use PKP\file\FileManager;
use PKP\plugins\HookRegistry;
use PKP\scheduledTask\ScheduledTaskHelper;
use PKP\statistics\TemporaryInstitutionsDAO;

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

    /** DAOs for temporary usage stats tables where the log entries are inserted for further processing */
    protected TemporaryInstitutionsDAO $temporaryInstitutionsDao;
    protected TemporaryTotalsDAO $temporaryTotalsDao;
    protected TemporaryItemInvestigationsDAO $temporaryItemInvestigationsDao;
    protected TemporaryItemRequestsDAO $temporaryItemRequestsDao;

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

        $this->temporaryInstitutionsDao = DAORegistry::getDAO('TemporaryInstitutionsDAO'); /* @var TemporaryInstitutionsDAO $statsInstitutionDao */
        $this->temporaryTotalsDao = DAORegistry::getDAO('TemporaryTotalsDAO'); /* @var TemporaryTotalsDAO $temporaryTotalsDao */
        $this->temporaryItemInvestigationsDao = DAORegistry::getDAO('TemporaryItemInvestigationsDAO'); /* @var TemporaryItemInvestigationsDAO $temporaryItemInvestigationsDao */
        $this->temporaryItemRequestsDao = DAORegistry::getDAO('TemporaryItemRequestsDAO'); /* @var TemporaryItemRequestsDAO $temporaryItemRequestsDao */
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
            dispatch(new CompileMonthlyMetrics($month, Application::get()->getRequest()->getSite()));
        }
        return ($processFilesResult && !$processingDirError);
    }

    /**
     * Delete entries in usage stats temporary tables by loadId
     */
    protected function deleteByLoadId(string $loadId): void
    {
        $this->temporaryInstitutionsDao->deleteByLoadId($loadId);
        $this->temporaryTotalsDao->deleteByLoadId($loadId);
        $this->temporaryItemInvestigationsDao->deleteByLoadId($loadId);
        $this->temporaryItemRequestsDao->deleteByLoadId($loadId);
    }

    /**
     * Insert usage stats log entry into temporary tables
     */
    protected function insertTemporaryUsageStatsData(object $entry, int $lineNumber, string $loadId): void
    {
        try {
            $this->temporaryTotalsDao->insert($entry, $lineNumber, $loadId);
            $this->temporaryInstitutionsDao->insert($entry->institutionIds, $lineNumber, $loadId);
            if (!empty($entry->submissionId)) {
                $this->temporaryItemInvestigationsDao->insert($entry, $lineNumber, $loadId);
                if ($entry->assocType == Application::ASSOC_TYPE_SUBMISSION_FILE) {
                    $this->temporaryItemRequestsDao->insert($entry, $lineNumber, $loadId);
                }
            }
        } catch (\Illuminate\Database\QueryException $e) {
            $this->addExecutionLogEntry(__('admin.scheduledTask.usageStatsLoader.insertError', ['file' => $loadId, 'lineNumber' => $lineNumber, 'msg' => $e->getMessage()]), ScheduledTaskHelper::SCHEDULED_TASK_MESSAGE_TYPE_ERROR);
        }
    }

    /**
     * Get valid assoc types that an usage event can contain
     */
    abstract protected function getValidAssocTypes(): array;

    /**
     * Validate the usage stats log entry
     *
     * @throws Exception.
     */
    protected function isLogEntryValid(object $entry): void
    {
        if (!$this->validateDate($entry->time)) {
            throw new Exception(__('admin.scheduledTask.usageStatsLoader.invalidLogEntry.time'));
        }
        // check hashed IP ?
        // check canonicalUrl ?
        if (!is_int($entry->contextId)) {
            throw new Exception(__('admin.scheduledTask.usageStatsLoader.invalidLogEntry.contextId'));
        }
        if (!empty($entry->submissionId) && !is_int($entry->submissionId)) {
            throw new Exception(__('admin.scheduledTask.usageStatsLoader.invalidLogEntry.submissionId'));
        }

        $validAssocTypes = $this->getValidAssocTypes();
        if (!in_array($entry->assocType, $validAssocTypes)) {
            throw new Exception(__('admin.scheduledTask.usageStatsLoader.invalidLogEntry.assocType'));
        }
        $validFileTypes = [
            StatisticsHelper::STATISTICS_FILE_TYPE_PDF,
            StatisticsHelper::STATISTICS_FILE_TYPE_DOC,
            StatisticsHelper::STATISTICS_FILE_TYPE_HTML,
            StatisticsHelper::STATISTICS_FILE_TYPE_OTHER,
        ];
        if (!empty($entry->fileType) && !in_array($entry->fileType, $validFileTypes)) {
            throw new Exception(__('admin.scheduledTask.usageStatsLoader.invalidLogEntry.fileType'));
        }
        if (!empty($entry->country) && (!ctype_alpha($entry->country) || !(strlen($entry->country) == 2))) {
            throw new Exception(__('admin.scheduledTask.usageStatsLoader.invalidLogEntry.country'));
        }
        if (!empty($entry->region) && (!ctype_alnum($entry->region) || !(strlen($entry->region) <= 3))) {
            throw new Exception(__('admin.scheduledTask.usageStatsLoader.invalidLogEntry.region'));
        }
        if (!is_array($entry->institutionIds)) {
            throw new Exception(__('admin.scheduledTask.usageStatsLoader.invalidLogEntry.institutionIds'));
        }
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
        $statsService = Services::get('sushiStats');
        $dateR5Installed = date('Ymd', strtotime($statsService->getEarliestDate()));
        if ($date < $dateR5Installed) {
            // the log file is in old log file format
            // return the file to staging and
            // log the error
            // TO-DO: once we decided how the log files in the old format should be reprocessed, this might change
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
     */
    protected function isMonthValid(string $loadId, string $month): bool
    {
        $currentMonth = date('Ym');
        $site = Application::get()->getRequest()->getSite();
        // If the daily metrics are not kept, and this is not the current month (which is kept in the DB)
        // the CLI script to reprocess the whole month should be called.
        if (!$site->getData('keepDailyUsageStats') && $month != $currentMonth) {
            // Check if the month has already been processed,
            // currently only the table metrics_counter_submission_monthly will be considered.
            // TO-DO: once we decided how the log files in the old format should be reprocessed
            // this should eventually be adapted, because the metrics_submission_geo_monthly could contain also earlier months
            $statsService = Services::get('sushiStats');
            $monthExists = $statsService->monthExists($month);
            if ($monthExists) {
                // The month has already been processed
                // return the file to staging and
                // log the error that a script for reprocessing should be called for the whole month.
                // If the log files of the month are being reprocessed, the CLI reprocessing script will first remove them,
                // an then call this script, so this condition will not apply.
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
     * Process the log file:
     * Read the log file line by line, validate, and insert into the temporary stats tables.
     * The file's entries MUST be ordered by date-time to successfully identify double-clicks and unique items.
     *
     * @throws Exception
     */
    protected function process(string $filePath, string $loadId): void
    {
        $fhandle = fopen($filePath, 'r');
        if (!$fhandle) {
            throw new Exception(__('admin.scheduledTask.usageStatsLoader.openFileFailed', ['file' => $filePath]));
        }
        // Make sure we don't have any temporary records associated
        // with the current load ID in database.
        $this->deleteByLoadId($loadId);

        $lineNumber = 0;
        while (!feof($fhandle)) {
            $lineNumber++;
            $line = trim(fgets($fhandle));
            if (empty($line) || substr($line, 0, 1) === '#') {
                continue;
            } // Spacing or comment lines. This actually should not occur in the new format.

            $entryData = json_decode($line);
            if ($entryData === null) {
                // This line is not in the right format..
                $this->addExecutionLogEntry(__('admin.scheduledTask.usageStatsLoader.wrongLoglineFormat', ['file' => $loadId, 'lineNumber' => $lineNumber]), ScheduledTaskHelper::SCHEDULED_TASK_MESSAGE_TYPE_ERROR);
                continue;
            }

            try {
                $this->isLogEntryValid($entryData);
            } catch (Exception $e) {
                // reject the file if the entry in invalid ???
                $this->addExecutionLogEntry(__('admin.scheduledTask.usageStatsLoader.invalidLogEntry', ['file' => $loadId, 'lineNumber' => $lineNumber, 'error' => $e->getMessage()]), ScheduledTaskHelper::SCHEDULED_TASK_MESSAGE_TYPE_ERROR);
                continue;
            }

            // Avoid bots.
            if (Core::isUserAgentBot($entryData->userAgent)) {
                continue;
            }

            HookRegistry::call('Stats::storeUsageEventLogEntry', [$entryData]);
            $this->insertTemporaryUsageStatsData($entryData, $lineNumber, $loadId);
        }
        fclose($fhandle);
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

        // Check if the log file is an old log file and if the stats for the month already exist
        if (!$this->isDateValid($loadId) || !$this->isMonthValid($loadId, $month)) {
            return self::FILE_LOADER_RETURN_TO_STAGING;
        }

        // Add this log file's month to the list of months the stats need to be aggregated for.
        $this->considerMonthForStatsAggregation($month);

        try {
            $this->process($filePath, $loadId);
        } catch (Exception $e) {
            throw $e;
        }

        // Despatch the job that will process the usage stats data in
        // the temporary stats tables and store them in the actual ones
        dispatch(new CompileUsageStatsFromTemporaryRecords($loadId));
        $this->addExecutionLogEntry(__(
            'admin.scheduledTask.usageStatsLoader.jobDispatched',
            ['file' => $filePath]
        ), ScheduledTaskHelper::SCHEDULED_TASK_MESSAGE_TYPE_NOTICE);

        return true;
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

    /**
     * Validate date, check if the date is a valid date and in requested format
     */
    protected function validateDate(string $datetime, string $format = 'Y-m-d H:i:s'): bool
    {
        $d = DateTime::createFromFormat($format, $datetime);
        return $d && $d->format($format) === $datetime;
    }
}

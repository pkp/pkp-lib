<?php

/**
 * @file jobs/statistics/PKPProcessUsageStatsLogFile.php
 *
 * Copyright (c) 2024 Simon Fraser University
 * Copyright (c) 2024 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PKPProcessUsageStatsLogFile
 *
 * @ingroup jobs
 *
 * @brief Compile context metrics.
 */

namespace PKP\jobs\statistics;

use APP\statistics\StatisticsHelper;
use DateTime;
use Exception;
use PKP\core\Core;
use PKP\job\exceptions\JobException;
use PKP\jobs\BaseJob;
use PKP\task\FileLoader;
use SplFileObject;

abstract class PKPProcessUsageStatsLogFile extends BaseJob
{
    /**
     * Create a new job instance.
     *
     * @param string $loadId Usage stats log file name
     */
    public function __construct(protected string $loadId)
    {
        parent::__construct();
    }

    /**
     * Delete entries in usage stats temporary tables by loadId
     */
    abstract protected function deleteByLoadId(): void;

    /**
     * Get valid assoc types that an usage event can contain
     */
    abstract protected function getValidAssocTypes(): array;

    /**
     * Insert usage stats log entry into temporary tables
     */
    abstract protected function insertTemporaryUsageStatsData(object $entry, int $lineNumber): void;

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $filename = $this->loadId;
        $dispatchFilePath = StatisticsHelper::getUsageStatsDirPath() . '/' . FileLoader::FILE_LOADER_PATH_DISPATCH . '/' . $filename;
        if (!file_exists($dispatchFilePath)) {
            throw new JobException(__(
                'admin.job.processLogFile.fileNotFound',
                ['file' => $dispatchFilePath]
            ));
        }
        $this->process($dispatchFilePath);
    }

    /**
     * Parse log file line by line and add the lines into the usage stats temporary DB tables.
     */
    protected function process(string $dispatchFilePath): void
    {
        try {
            $splFileObject = new SplFileObject($dispatchFilePath, 'r');
        } catch (Exception $e) {
            // reject file -- move the file from dispatch to reject folder
            $filename = $this->loadId;
            $rejectFilePath = StatisticsHelper::getUsageStatsDirPath() . '/' . FileLoader::FILE_LOADER_PATH_REJECT . '/' . $filename;
            if (!rename($dispatchFilePath, $rejectFilePath)) {
                error_log(__('admin.job.compileMetrics.returnToStaging.error', ['file' => $filename, 'dispatchFilePath' => $dispatchFilePath, 'rejectFilePath' => $rejectFilePath]));
            }
            throw new JobException(__('admin.job.processLogFile.openFileFailed', ['file' => $dispatchFilePath]), 0, $e);
        }

        // Make sure we don't have any temporary records associated
        // with the current load ID in database.
        $this->deleteByLoadId();

        $lineNumber = 0;
        while (!$splFileObject->eof()) {
            $lineNumber++;
            $line = $splFileObject->fgets();
            if (empty($line) || substr($line, 0, 1) === '#') {
                continue;
            } // Spacing or comment lines. This actually should not occur in the new format.

            $entryData = json_decode($line);
            if ($entryData === null) {
                // This line is not in the right format.
                $message = __(
                    'admin.job.processLogFile.wrongLoglineFormat',
                    ['file' => $this->loadId, 'lineNumber' => $lineNumber]
                );
                error_log($message);
                continue;
            }

            try {
                $this->validateLogEntry($entryData);
            } catch (Exception $e) {
                $message = __(
                    'admin.job.processLogFile.invalidLogEntry',
                    ['file' => $this->loadId, 'lineNumber' => $lineNumber, 'error' => $e->getMessage()]
                );
                error_log($message);
                continue;
            }

            // Avoid bots.
            if (Core::isUserAgentBot($entryData->userAgent)) {
                continue;
            }

            $this->insertTemporaryUsageStatsData($entryData, $lineNumber);
        }
        //explicitly assign null, so that the file can be deleted
        $splFileObject = null;
    }

    /**
     * Validate the usage stats log entry
     *
     * @throws Exception.
     */
    protected function validateLogEntry(object $entry): void
    {
        if (!$this->validateDate($entry->time)) {
            throw new Exception(__('admin.job.processLogFile.invalidLogEntry.time'));
        }
        // check hashed IP ?
        // check canonicalUrl ?
        if (!is_int($entry->contextId)) {
            throw new Exception(__('admin.job.processLogFile.invalidLogEntry.contextId'));
        }
        if (!empty($entry->submissionId) && !is_int($entry->submissionId)) {
            throw new Exception(__('admin.job.processLogFile.invalidLogEntry.submissionId'));
        }

        $validAssocTypes = $this->getValidAssocTypes();
        if (!in_array($entry->assocType, $validAssocTypes)) {
            throw new Exception(__('admin.job.processLogFile.invalidLogEntry.assocType'));
        }
        $validFileTypes = [
            StatisticsHelper::STATISTICS_FILE_TYPE_PDF,
            StatisticsHelper::STATISTICS_FILE_TYPE_DOC,
            StatisticsHelper::STATISTICS_FILE_TYPE_HTML,
            StatisticsHelper::STATISTICS_FILE_TYPE_OTHER,
        ];
        if (!empty($entry->fileType) && !in_array($entry->fileType, $validFileTypes)) {
            throw new Exception(__('admin.job.processLogFile.invalidLogEntry.fileType'));
        }
        if (!empty($entry->country) && (!ctype_alpha($entry->country) || (strlen($entry->country) !== 2))) {
            throw new Exception(__('admin.job.processLogFile.invalidLogEntry.country'));
        }
        if (!empty($entry->region) && (!ctype_alnum($entry->region) || (strlen($entry->region) > 3))) {
            throw new Exception(__('admin.job.processLogFile.invalidLogEntry.region'));
        }
        if (!is_array($entry->institutionIds)) {
            throw new Exception(__('admin.job.processLogFile.invalidLogEntry.institutionIds'));
        }
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

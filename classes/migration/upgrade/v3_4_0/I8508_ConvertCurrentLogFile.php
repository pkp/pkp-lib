<?php

/**
 * @file classes/migration/upgrade/v3_4_0/I8508_ConvertCurrentLogFile.php
 *
 * Copyright (c) 2022 Simon Fraser University
 * Copyright (c) 2022 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class I8508_ConvertCurrentLogFile
 * @brief Convert current usage stats log file into the new format.
 */

namespace PKP\migration\upgrade\v3_4_0;

use APP\statistics\StatisticsHelper;
use PKP\cliTool\ConvertLogFile;
use PKP\config\Config;
use PKP\file\FileManager;
use PKP\install\DowngradeNotSupportedException;
use PKP\migration\Migration;

class I8508_ConvertCurrentLogFile extends Migration
{
    /**
     * Run the migration.
     */
    public function up(): void
    {
        $fileManager = new FileManager();
        $convertCurrentUsageStatsLogFile = new ConvertCurrentUsageStatsLogFile();

        $todayFileName = 'usage_events_' . date('Ymd') . '.log';
        if (file_exists($convertCurrentUsageStatsLogFile->getLogFileDir() . '/' . $todayFileName)) {
            $convertCurrentUsageStatsLogFile->convert($todayFileName);
            $oldFilePath = $convertCurrentUsageStatsLogFile->getLogFileDir() . '/usage_events_' . date('Ymd') . '_old.log';
            $oldFileRemoved = $fileManager->deleteByPath($oldFilePath);
            if ($oldFileRemoved) {
                $this->_installer->log("The old usage stats log file {$oldFilePath} was successfully deleted.");
            } else {
                $this->_installer->log("The old usage stats log file {$oldFilePath} could not be deleted.");
            }
        }

        $yesterdayFileName = 'usage_events_' . date('Ymd', strtotime('-1 days')) . '.log';
        if (file_exists($convertCurrentUsageStatsLogFile->getLogFileDir() . '/' . $yesterdayFileName)) {
            $convertCurrentUsageStatsLogFile->convert($yesterdayFileName);
            $oldFilePath = $convertCurrentUsageStatsLogFile->getLogFileDir() . '/usage_events_' . date('Ymd', strtotime('-1 days')) . '_old.log';
            $oldFileRemoved = $fileManager->deleteByPath($oldFilePath);
            if ($oldFileRemoved) {
                $this->_installer->log("The old usage stats log file {$oldFilePath} was successfully deleted.");
            } else {
                $this->_installer->log("The old usage stats log file {$oldFilePath} could not be deleted.");
            }
        }
    }

    /**
     * Reverse the downgrades
     *
     * @throws DowngradeNotSupportedException
     */
    public function down(): void
    {
        throw new DowngradeNotSupportedException();
    }
}

class ConvertCurrentUsageStatsLogFile extends ConvertLogFile
{
    public function getLogFileDir(): string
    {
        return StatisticsHelper::getUsageStatsDirPath() . '/usageEventLogs';
    }

    public function getParseRegex(): string
    {
        return '/^(?P<ip>\S+) \S+ \S+ "(?P<date>.*?)" (?P<url>\S+) (?P<returnCode>\S+) "(?P<userAgent>.*?)"/';
    }

    public function getPhpDateTimeFormat(): string
    {
        return 'Y-m-d H:i:s';
    }

    public function isPathInfoDisabled(): bool
    {
        return Config::getVar('general', 'disable_path_info') ? true : false;
    }

    public function isApacheAccessLogFile(): bool
    {
        return false;
    }
}

<?php

/**
 * @file classes/migration/upgrade/v3_4_0/I8508_ConvertCurrentLogFile.php
 *
 * Copyright (c) 2022 Simon Fraser University
 * Copyright (c) 2022 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class I8508_ConvertCurrentLogFile
 *
 * @brief Convert current and eventually the usage stats log file from yesterday into the new format.
 */

namespace PKP\migration\upgrade\v3_4_0;

use APP\statistics\StatisticsHelper;
use Illuminate\Support\Facades\DB;
use PKP\cliTool\traits\ConvertLogFile;
use PKP\file\FileManager;
use PKP\install\DowngradeNotSupportedException;
use PKP\migration\Migration;
use PKP\task\UpdateIPGeoDB;

class I8508_ConvertCurrentLogFile extends Migration
{
    /**
     * Run the migration.
     */
    public function up(): void
    {
        $fileManager = new FileManager();
        $convertCurrentUsageStatsLogFile = new ConvertCurrentUsageStatsLogFile();

        // If Geo usage stats are enabled download the GeoIPDB
        $siteGeoUsageStatsSettings = DB::table('site_settings')
            ->where('setting_name', '=', 'enableGeoUsageStats')
            ->value('setting_value');
        if ($siteGeoUsageStatsSettings != null && $siteGeoUsageStatsSettings !== 'disabled') {
            $geoIPDBFile = StatisticsHelper::getGeoDBPath();
            if (!file_exists($geoIPDBFile)) {
                $geoIPDB = new UpdateIPGeoDB();
                $geoIPDB->execute();
            }
        }

        $counterR5StartDate = date('Y-m-d');

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
            $counterR5StartDate = date('Y-m-d', strtotime('-1 days'));
            $oldFilePath = $convertCurrentUsageStatsLogFile->getLogFileDir() . '/usage_events_' . date('Ymd', strtotime('-1 days')) . '_old.log';
            $oldFileRemoved = $fileManager->deleteByPath($oldFilePath);
            if ($oldFileRemoved) {
                $this->_installer->log("The old usage stats log file {$oldFilePath} was successfully deleted.");
            } else {
                $this->_installer->log("The old usage stats log file {$oldFilePath} could not be deleted.");
            }
        }

        DB::table('site_settings')->insert(['setting_name' => 'counterR5StartDate', 'setting_value' => $counterR5StartDate]);
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

class ConvertCurrentUsageStatsLogFile
{
    use ConvertLogFile;

    /**
     * Constructor.
     */
    public function __construct()
    {
        $this->__constructTrait();
    }

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
        return false;
    }

    public function isApacheAccessLogFile(): bool
    {
        return false;
    }
}

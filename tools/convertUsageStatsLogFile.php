<?php

/**
 * @file tools/convertUsageStatsLogFile.php
 *
 * Copyright (c) 2022 Simon Fraser University
 * Copyright (c) 2022 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class ConvertUsageStatsLogFile
 *
 * @ingroup tools
 *
 * @brief CLI tool to convert an old usage stats log file (used in releases < 3.4) into the new format.
 *
 */

require(dirname(__FILE__, 4) . '/tools/bootstrap.php');

use APP\statistics\StatisticsHelper;
use PKP\cliTool\ConvertLogFile;
use PKP\task\FileLoader;

class ConvertUsageStatsLogFile extends ConvertLogFile
{
    /**
     * Weather the URL parameters are used instead of CGI PATH_INFO.
     * This is the former variable 'disable_path_info' in the config.inc.php
     *
     * This needs to be set to true if the URLs in the old log file contain the paramteres as URL query string.
     */
    public const PATH_INFO_DISABLED = false;

    /**
     * Regular expression that is used for parsing the old log file entries that should be converted to the new format.
     *
     * The default regex can parse the usageStats plugin's log files.
     */
    public const PARSEREGEX = '/^(?P<ip>\S+) \S+ \S+ "(?P<date>.*?)" (?P<url>\S+) (?P<returnCode>\S+) "(?P<userAgent>.*?)"/';

    /**
     * PHP format of the time in the log file.
     * S. https://www.php.net/manual/en/datetime.format.php
     *
     * This default format can parse the date in the usageStats plugin's log files.
     */
    public const PHP_DATETIME_FORMAT = 'Y-m-d H:i:s';

    /**
     * Name of the log file that should be converted into the new format.
     */
    public string $fileName;

    /**
     * Constructor.
     *
     * @param array $argv command-line arguments (see usage)
     */
    public function __construct(array $argv = [])
    {
        parent::__construct($argv);
        if (count($this->argv) != 1) {
            $this->usage();
            exit(8);
        }
        $this->fileName = array_shift($this->argv);
    }

    /**
     * Print command usage information.
     */
    public function usage(): void
    {
        $archivePath = $this->getLogFileDir();
        echo "\nConvert an old usage stats log file.\nThe old usage stats log file needs to be in the folder {$archivePath}.\n\n"
            . "  Usage: php {$this->scriptName} [fileName]\n\n";
    }

    public function getLogFileDir(): string
    {
        return StatisticsHelper::getUsageStatsDirPath() . '/' . FileLoader::FILE_LOADER_PATH_ARCHIVE;
    }

    public function getParseRegex(): string
    {
        return self::PARSEREGEX;
    }

    public function getPhpDateTimeFormat(): string
    {
        return self::PHP_DATETIME_FORMAT;
    }

    public function isPathInfoDisabled(): bool
    {
        return self::PATH_INFO_DISABLED;
    }

    public function isApacheAccessLogFile(): bool
    {
        return false;
    }

    /**
     * Convert the file.
     */
    public function execute(): void
    {
        $this->convert($this->fileName);
    }
}

$tool = new ConvertUsageStatsLogFile($argv ?? []);
$tool->execute();

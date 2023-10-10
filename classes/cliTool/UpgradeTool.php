<?php

/**
 * @file classes/cliTool/UpgradeTool.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class upgradeTool
 *
 * @ingroup tools
 *
 * @brief CLI tool for upgrading the system.
 *
 * Note: Some functions require fopen wrappers to be enabled.
 */

namespace PKP\cliTool;

use APP\core\Application;
use APP\install\Upgrade;
use PKP\site\VersionCheck;

Application::upgrade();

class UpgradeTool extends \PKP\cliTool\CommandLineTool
{
    /** @var string command to execute (check|upgrade|download) */
    public $command;

    /**
     * Constructor.
     *
     * @param array $argv command-line arguments
     */
    public function __construct($argv = [])
    {
        parent::__construct($argv);

        if (!isset($this->argv[0]) || !in_array($this->argv[0], ['check', 'latest', 'upgrade', 'download'])) {
            $this->usage();
            exit(1);
        }

        $this->command = $this->argv[0];
    }

    /**
     * Print command usage information.
     */
    public function usage()
    {
        echo "Upgrade tool\n"
            . "Usage: {$this->scriptName} command\n"
            . "Supported commands:\n"
            . "    check     perform version check\n"
            . "    latest    display latest version info\n"
            . "    upgrade   execute upgrade script\n"
            . "    download  download latest version (does not unpack/install)\n";
    }

    /**
     * Execute the specified command.
     */
    public function execute()
    {
        $command = $this->command;
        $this->$command();
    }

    /**
     * Perform version check against latest available version.
     */
    public function check()
    {
        $this->checkVersion(VersionCheck::getLatestVersion());
    }

    /**
     * Print information about the latest available version.
     */
    public function latest()
    {
        $this->checkVersion(VersionCheck::getLatestVersion(), true);
    }

    /**
     * Run upgrade script.
     */
    public function upgrade()
    {
        $installer = new Upgrade([]);
        $installer->setLogger($this);

        if ($installer->execute()) {
            if (count($installer->getNotes()) > 0) {
                printf("\nRelease Notes\n");
                printf("----------------------------------------\n");
                foreach ($installer->getNotes() as $note) {
                    printf("%s\n\n", $note);
                }
            }

            $newVersion = $installer->getNewVersion();
            printf("Successfully upgraded to version %s\n", $newVersion->getVersionString(false));
        } else {
            printf("ERROR: Upgrade failed: %s\n", $installer->getErrorString());
            exit(2);
        }
    }

    /**
     * Download latest package.
     */
    public function download()
    {
        $versionInfo = VersionCheck::getLatestVersion();
        if (!$versionInfo) {
            $application = Application::get();
            printf("Failed to load version info from %s\n", $application->getVersionDescriptorUrl());
            exit(3);
        }

        $download = $versionInfo['package'];
        $outFile = basename($download);

        printf("Download: %s\n", $download);
        printf("File will be saved to: %s\n", $outFile);

        if (!$this->promptContinue()) {
            exit(0);
        }

        $out = fopen($outFile, 'wb');
        if (!$out) {
            printf("Failed to open %s for writing\n", $outFile);
            exit(5);
        }

        $in = fopen($download, 'rb');
        if (!$in) {
            printf("Failed to open %s for reading\n", $download);
            fclose($out);
            exit(6);
        }

        printf('Downloading file...');

        while (($data = fread($in, 4096)) !== '') {
            printf('.');
            fwrite($out, $data);
        }

        printf("done\n");

        fclose($in);
        fclose($out);
    }

    /**
     * Perform version check.
     *
     * @param array $versionInfo latest version info
     * @param bool $displayInfo just display info, don't perform check
     */
    public function checkVersion($versionInfo, $displayInfo = false)
    {
        if (!$versionInfo) {
            $application = Application::get();
            printf("Failed to load version info from %s\n", $application->getVersionDescriptorUrl());
            exit(7);
        }

        $dbVersion = VersionCheck::getCurrentDBVersion();
        $codeVersion = VersionCheck::getCurrentCodeVersion();
        $latestVersion = $versionInfo['version'];

        printf("Code version:      %s\n", $codeVersion->getVersionString(false));
        printf("Database version:  %s\n", $dbVersion->getVersionString(false));
        printf("Latest version:    %s\n", $latestVersion->getVersionString(false));

        $compare1 = $codeVersion->compare($latestVersion);
        $compare2 = $dbVersion->compare($codeVersion);

        if (!$displayInfo) {
            if ($compare2 < 0) {
                printf("Database version is older than code version\n");
                printf("Run \"{$this->scriptName} upgrade\" to update\n");
            } elseif ($compare2 > 0) {
                printf("Database version is newer than code version!\n");
            } elseif ($compare1 == 0) {
                printf("Your system is up-to-date\n");
            } elseif ($compare1 < 0) {
                printf("A newer version is available:\n");
                $displayInfo = true;
            } else {
                printf("Current version is newer than latest!\n");
            }
        }

        if ($displayInfo) {
            printf("         tag:     %s\n", $versionInfo['tag']);
            printf("         date:    %s\n", $versionInfo['date']);
            printf("         info:    %s\n", $versionInfo['info']);
            printf("         package: %s\n", $versionInfo['package']);
        }

        return $compare1;
    }

    /**
     * Prompt user for yes/no input (default no).
     *
     * @param string $prompt
     */
    public function promptContinue($prompt = 'Continue?')
    {
        printf('%s [y/N] ', $prompt);
        $continue = fread(STDIN, 255);
        return (strtolower(substr(trim($continue), 0, 1)) == 'y');
    }

    /**
     * Log install message to stdout.
     *
     * @param string $message
     */
    public function log($message)
    {
        printf("%s [%s]\n", date('Y-m-d H:i:s'), $message);
    }
}

if (!PKP_STRICT_MODE) {
    class_alias('\PKP\cliTool\UpgradeTool', '\UpgradeTool');
}

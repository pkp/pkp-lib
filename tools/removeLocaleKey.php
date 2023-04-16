<?php

/**
 * @file tools/removeLocaleKey.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class RemoveLocaleKey
 *
 * @ingroup tools
 *
 * @brief Remove a locale key from all locale files.
 */

require(dirname(__FILE__, 4) . '/tools/bootstrap.php');

class RemoveLocaleKey extends \PKP\cliTool\CommandLineTool
{
    /** @var string Locale key to be removed */
    public $localeKey = '';

    /** @var array Which files to remove the locale key from */
    public $dirs = ['locale', 'lib/pkp/locale'];

    /**
     * Constructor
     */
    public function __construct($argv = [])
    {
        parent::__construct($argv);

        if (!sizeof($this->argv)) {
            $this->usage();
            exit(1);
        }

        array_shift($argv);

        $this->localeKey = array_shift($argv);

        if (!empty($argv)) {
            $this->dirs = $argv;
        }
    }

    /**
     * Print command usage information.
     */
    public function usage()
    {
        echo "\nRemove a locale key from all locale files.\n\n"
            . "  Usage: php {$this->scriptName} [localeKey] ([path] [path])\n\n"
            . "  Remove locale keys from app:\n  php {$this->scriptName} locale.key locale\n\n"
            . "  Remove locale keys from pkp-lib:\n  php {$this->scriptName} locale.key lib/pkp/locale\n\n"
            . "  If no path is specified it will remove the locale\n  key from files in both directories.\n\n";
    }

    /**
     * Remove the requested locale key
     */
    public function execute()
    {
        $localeKeyLine = 'msgid "' . $this->localeKey . '"';
        $rootDir = dirname(__FILE__, 4);

        foreach ($this->dirs as $dir) {
            $locales = scandir($rootDir . '/' . $dir);
            foreach ($locales as $locale) {
                if ($locale === '.' || $locale === '..') {
                    continue;
                }
                $localeDir = join('/', [$rootDir, $dir, $locale]);
                $files = scandir($localeDir);
                foreach ($files as $file) {
                    if ($file === '.' || $file === '..' || substr($file, -2) !== 'po') {
                        continue;
                    }
                    $content = file_get_contents($localeDir . '/' . $file);
                    $lines = explode("\n", $content);
                    $newLines = [];
                    $removing = false;
                    foreach ($lines as $line) {
                        if ($localeKeyLine === substr($line, 0, strlen($localeKeyLine))) {
                            $removing = true;
                        } elseif ($removing && 'msgid' === substr($line, 0, strlen('msgid'))) {
                            $removing = false;
                        }
                        if (!$removing) {
                            $newLines[] = $line;
                        }
                    }
                    if (count($lines) !== count($newLines)) {
                        file_put_contents($localeDir . '/' . $file, join("\n", $newLines));
                        echo(count($lines) - count($newLines)) . " lines removed from {$localeDir}/{$file}.\n";
                    }
                }
            }
        }
    }
}

$tool = new RemoveLocaleKey($argv ?? []);
$tool->execute();

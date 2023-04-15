<?php

/**
 * @file tools/moveLocaleKeysToLib.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class MoveLocaleKeysToLib
 * @ingroup tools
 *
 * @brief Move a locale key from an application's locale files to the pkp-lib locale files.
 */

require(dirname(__FILE__, 4) . '/tools/bootstrap.php');

class MoveLocaleKeysToLib extends \PKP\cliTool\CommandLineTool
{
    /** @var string The string to match in a msgid */
    public $msgidMatch = '';

    /** @var string The application file to search for keys */
    public $sourceFile = '';

    /** @var string The pkp-lib file to move the keys to */
    public $targetFile = '';

    /** @var bool Whether to move locale keys from lib/pkp to the app */
    public $reverse = false;

    /**
     * Constructor
     */
    public function __construct($argv = [])
    {
        parent::__construct($argv);

        // discard first argument: script name
        array_shift($argv);

        if (sizeof($this->argv) < 3) {
            $this->usage();
            exit(1);
        }

        if ($argv[0] === '-r') {
            $this->reverse = true;
            array_shift($argv);
        }

        $this->msgidMatch = array_shift($argv);
        $this->sourceFile = array_shift($argv);
        $this->targetFile = array_shift($argv);
    }

    /**
     * Print command usage information.
     */
    public function usage()
    {
        echo "\nMove matching locale keys from one file to another.\n\n"
            . "All matching locale keys will be moved from the source file to the target file. This will\n"
            . "effect all locales.\n\n"
            . "  Usage: php {$this->scriptName} (options) [match] [sourceFile] [targetFile]\n\n"
            . "  (options)    Optional flags:\n"
            . "               -r     Move locale keys from lib/pkp into the app.\n"
            . "  [match]      The string to match in the locale key's msgid, Supports partial\n"
            . "               matches from start of msgid. `example.key.` will match `msgid \"example.key.anything\"`.\n\n"
            . "  [sourceFile] The file to look for keys to move, such as `emails.po`.\n\n"
            . "  [targetFile] The file to move keys to, such as `emails.po`. Usually the same as `sourceFile`.\n\n";
    }

    /**
     * Remove the requested locale key
     */
    public function execute()
    {
        $localeDirs = scandir('locale');
        if (!$localeDirs) {
            $this->output('Locale directories could not be found. Run this from the root directory of the application.');
            exit;
        }

        $localeDirs = array_filter($localeDirs, function ($localeDir) {
            return $localeDir !== '.' && $localeDir !== '..';
        });

        $fromDir = $this->reverse
            ? 'lib/pkp/locale/'
            : 'locale/';
        $toDir = $this->reverse
            ? 'locale/'
            : 'lib/pkp/locale/';

        foreach (array_values($localeDirs) as $localeDir) {
            $localeSourceFile = $fromDir . $localeDir . '/' . $this->sourceFile;
            $localeTargetFile = $toDir . $localeDir . '/' . $this->targetFile;
            if (!file_exists($localeSourceFile)) {
                $this->output('No file exists at ' . $localeSourceFile . ' to move locale keys from. Skipping this locale.');
                continue;
            }

            // Create a new file if no file exists at the target and add the weblate header
            if (!file_exists($localeTargetFile)) {
                if (!file_exists(dirname($localeTargetFile))) {
                    mkdir(dirname($localeTargetFile));
                }
                $lines = explode("\n", file_get_contents($localeSourceFile));
                $headerLines = [];
                $endOfHeader = '"X-Generator';
                foreach ($lines as $line) {
                    $headerLines[] = $line;
                    if (substr($line, 0, strlen($endOfHeader)) == $endOfHeader) {
                        break;
                    }
                }
                $headerLines[] = "\n";
                file_put_contents($localeTargetFile, join("\n", $headerLines));
                $this->output('New file created at ' . $localeTargetFile . '.');
            }

            $changedSourceLines = [];
            $newTargetLines = [];
            $isMovingLine = false;

            $lines = explode("\n", file_get_contents($localeSourceFile));
            foreach ($lines as $i => $line) {
                if ($line === "msgid \"{$this->msgidMatch}\"") {
                    $isMovingLine = true;
                } elseif (trim($line) === '#, fuzzy' || substr($line, 0, 5) === 'msgid') {
                    $isMovingLine = false;
                }
                if ($isMovingLine) {
                    // Check for fuzzy flag and make sure it's moved over
                    if ($lines[$i - 1] === '#, fuzzy') {
                        $newTargetLines[] = $lines[$i - 1];
                        array_pop($changedSourceLines);
                    }
                    $newTargetLines[] = $line;
                } else {
                    $changedSourceLines[] = $line;
                }
            }

            if (count($newTargetLines)) {
                file_put_contents($localeTargetFile, "\n" . join("\n", $newTargetLines), FILE_APPEND);
                $this->output(count($newTargetLines) . ' lines added to ' . $localeTargetFile . '.');
            }

            $linesToRemove = count($lines) - count($changedSourceLines);
            if (count($lines) !== count($changedSourceLines)) {
                file_put_contents($localeSourceFile, join("\n", $changedSourceLines));
                $this->output($linesToRemove . ' lines removed from ' . $localeSourceFile . '.');
            }

            if (!count($newTargetLines) && !$linesToRemove) {
                $this->output('No changes made to ' . $localeSourceFile . '.');
            }
        }
    }


    protected function output(string $string)
    {
        echo "\n" . $string;
    }
}

$tool = new MoveLocaleKeysToLib($argv ?? []);
$tool->execute();

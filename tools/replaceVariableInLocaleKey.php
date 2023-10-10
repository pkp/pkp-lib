<?php

/**
 * @file tools/replaceVariableInLocaleKey.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class ReplaceVariableInLocaleKey
 *
 * @ingroup tools
 *
 * @brief Replace a {$variable} in a specific locale key across all locales
 */

require(dirname(__FILE__, 4) . '/tools/bootstrap.php');

class ReplaceVariableInLocaleKey extends \PKP\cliTool\CommandLineTool
{
    /** @var string The string to match in a msgid */
    public $msgidMatch = '';

    /** @var string The {$variable} to search for */
    public $oldVariable = '';

    /** @var string The {$variable} to replace */
    public $newVariable = '';

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

        $this->msgidMatch = array_shift($argv);
        $this->oldVariable = '{$' . array_shift($argv) . '}';
        $this->newVariable = '{$' . array_shift($argv) . '}';
    }

    public function usage()
    {
        echo "\nReplace a variable in a locale key.\n\n"
            . "A variable like {\$example} can be replaced  will be moved from the source file to the target file. This will\n"
            . "effect all locales.\n\n"
            . "  Usage: php {$this->scriptName} [match] [oldVariable] [newVariable]\n\n"
            . "  [match]    The msgid to modify to match in each locale file.\n\n"
            . "  [oldVariable] The variable to replace, without the `{\$` and `}`, such as: old\n\n"
            . "  [newVariable] The new variable value, without the `{\$` and `}`, such as: new\n\n"
            . "  Example: php lib/pkp/tools/replaceVariableInLocaleKey.php emails.submissionAck.body signature contextSignature\n\n";
    }

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

        $searchDirs = [
            'locale/',
            'lib/pkp/locale/'
        ];

        foreach ($searchDirs as $searchDir) {
            foreach (array_values($localeDirs) as $localeDir) {
                $dir = $searchDir . $localeDir;

                if (!file_exists($dir)) {
                    $this->output('No directory exists at ' . $dir . ' to modify. Skipping this locale.');
                    continue;
                }

                $localeFiles = array_filter(scandir($dir), function ($localeDir) {
                    return $localeDir !== '.' && $localeDir !== '..';
                });

                foreach (array_values($localeFiles) as $localeFile) {
                    $countChanges = 0;
                    $isInMsgid = false;
                    $file = $dir . '/' . $localeFile;

                    if (is_dir($file)) {
                        $this->output('Skipping directory ' . $file);
                        continue;
                    }

                    $lines = explode("\n", file_get_contents($file));
                    foreach ($lines as $i => $line) {
                        if ($line === "msgid \"{$this->msgidMatch}\"") {
                            $isInMsgid = true;
                        } elseif (trim($line) === '#, fuzzy' || substr($line, 0, 5) === 'msgid') {
                            $isInMsgid = false;
                        }
                        if (!$isInMsgid) {
                            continue;
                        }
                        if (str_contains($line, $this->oldVariable)) {
                            $lines[$i] = str_replace($this->oldVariable, $this->newVariable, $line);
                            $countChanges++;
                        }
                    }

                    if ($countChanges) {
                        file_put_contents($file, join("\n", $lines));
                        $this->output('Replaced ' . $countChanges . ' lines in ' . $file . '.');
                    }
                }
            }
        }
    }


    protected function output(string $string)
    {
        echo "\n" . $string;
    }
}

$tool = new ReplaceVariableInLocaleKey($argv ?? []);
$tool->execute();

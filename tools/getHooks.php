<?php

/**
 * @file tools/getHooks.php
 *
 * Copyright (c) 2014-2020 Simon Fraser University
 * Copyright (c) 2003-2020 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class getHooks
 *
 * @ingroup tools
 *
 * @brief CLI tool to compile documentation on hooks.
 *
 * getHooks.php searches for .tpl and .php files, watching for @hook self-documentation.
 * It expects @hook annotations of the form:
 *
 *   @hook Hook::Name::Here [parameter, list, here] Hook description goes here
 *
 */

define('APP_ROOT', dirname(dirname(dirname(dirname(__FILE__)))));
require(dirname(__FILE__, 4) . '/tools/bootstrap.php');

class getHooks extends \PKP\cliTool\CommandLineTool
{
    /** @var array Hooks */
    public array $hooks = [];

    /** @var array Directories to exclude from indexing. (.gitignore dirs will be added to this list) */
    public array $excludePaths = [
        './.git',
        './cache',
        './cypress',
        './docs',
        './locale',
        './lib/pkp/.git',
        './lib/pkp/cypress',
        './lib/pkp/lib/vendor',
        './plugins/generic/citationStyleLanguage/lib/vendor',
        './plugins/paymethod/paypal/vendor',
        './lib/pkp/classes/dev',
        './lib/pkp/locale',
        './lib/ui-library',
        './lib/pkp/tools/getHooks.php'
    ];

    /**
     * Constructor.
     *
     * @param array $argv command-line arguments
     */
    public function __construct($argv = [])
    {
        parent::__construct($argv);

        if (!$this->validateArgs()) {
            $this->usage();
            exit(1);
        }
    }

    /**
     * Validate arguments
     */
    public function validateArgs()
    {
        if (count($this->argv) == 0) {
            return true;
        }
        if (count($this->argv) > 1) {
            return false;
        }
        switch ($this->argv[0]) {
            case '-p': return true;
            case '-j': return true;
            case '-r': return true;
        }
        return false;
    }

    /**
     * Print command usage information.
     */
    public function usage()
    {
        echo "Hook list generation tool\n"
            . "Usage: {$this->scriptName} [parameters]\n"
            . "  Parameters:\n"
            . "\t-j: List only hook names in JSON format (default)\n"
            . "\t-d: List details in print_r format\n"
            . "\t-r: List details in RST format\n";
    }

    /**
     * Parse and execute the import/export task.
     */
    public function execute()
    {
        $this->loadIgnoreDirs(APP_ROOT . '/.gitignore');
        $this->loadIgnoreDirs(APP_ROOT . '/lib/pkp/.gitignore', './lib/pkp/');

        $this->processDir('./', function ($fileName) {
            if (in_array(substr($fileName, -4), ['.php', '.tpl'])) {
                $file = file_get_contents($fileName);

                // Try to detect namespace
                if (preg_match('/\nnamespace ([^;]*);\n/', $file, $matches)) {
                    $namespace = $matches[1];
                } else {
                    $namespace = null;
                }

                // Try to detect classname
                if (preg_match('/\n(abstract )?(class|trait|interface|enum) ([^ ]*)( [^{]*{)?\n/', $file, $matches)) {
                    $classname = $matches[3];
                } else {
                    error_log('Unable to detect classname from ' . $fileName);
                    $classname = null;
                }

                // Format: @hook Hook::Name::Here [optional parameter list] Optional description
                if (preg_match_all('/\@hook (\h[^\h]*) (\h+\[.*\])? (\h+[^\n]*)? \v/x', $file, $matches, PREG_SET_ORDER)) {
                    foreach ($matches as $match) {
                        $hookName = trim($match[1]);
                        $paramList = isset($match[2]) ? trim($match[2]) : null;
                        $description = isset($match[3]) ? trim($match[3]) : null;
                        if (isset($this->hooks[$hookName])) {
                            $this->hooks[$hookName]['sources'][] = substr($fileName, 2);
                        } else {
                            $this->hooks[$hookName] = [
                                'name' => $hookName,
                                'params' => $paramList,
                                'description' => $description,
                                'namespace' => $namespace,
                                'classname' => $classname,
                                'sources' => [substr($fileName, 2)], // Trim off leading ./
                            ];
                        }
                    }
                }
            }
        });

        ksort($this->hooks);

        // With -p: print_r format with details
        if (in_array('-p', $this->argv)) {
            print_r(array_values($this->hooks));
            exit();
        }

        // With -r: RST format with details
        if (in_array('-r', $this->argv)) {
            // Write out table
            echo "=====\nHooks\n=====\n..\n  DO NOT EDIT THIS FILE MANUALLY. It is generated by: php lib/pkp/tools/getHooks.php -r\n\n";

            foreach ($this->hooks as $hookDetails) {
                echo '`' . $hookDetails['name'] . '`' . "\n";
                echo '    Class: :php:class:`' . $hookDetails['namespace'] . '\\' . $hookDetails['classname'] . '`' . "\n";
                echo "    \n";
                echo '    Parameters: `' . $hookDetails['params'] . '`' . "\n";
                if (isset($hookDetails['description'])) {
                    echo "    \n";
                    echo '    ' . $hookDetails['description'] . '`' . "\n";
                }
                echo "\n";
            }
            exit();
        }

        // Default or with -j: JSON with just key names
        echo json_encode(array_values($this->hooks));
    }

    /**
     * Recursive function to find hook docblocks in a directory
     */
    public function processDir(string $dir, callable $function)
    {
        foreach (new DirectoryIterator($dir) as $fileInfo) {
            $isExcluded = false;
            foreach ($this->excludePaths as $excludePath) {
                if (strpos($fileInfo->getPathname(), $excludePath) === 0) {
                    $isExcluded = true;
                    break;
                }
            }
            if ($isExcluded) {
                continue;
            }
            if (!$fileInfo->isDot()) {
                if ($fileInfo->isDir()) {
                    $this->processDir($fileInfo->getPathname(), $function);
                } else {
                    if ($fileInfo->getFilename() == 'index.php') {
                        continue;
                    }
                    if ($fileInfo->getExtension() !== 'php') {
                        continue;
                    }
                    call_user_func($function, $dir . '/' . $fileInfo->getFilename());
                }
            }
        }
    }

    /**
     * Load a .gitignore file and add to the excluded to directories
     *
     * @param string $path Path and filename for gitignore file
     * @param string $prefix A prefix to give to each of the paths in the gitignore file
     */
    public function loadIgnoreDirs(string $path, $prefix = '')
    {
        $gitIgnore = file_get_contents($path);
        $gitIgnorePaths = explode("\n", $gitIgnore);
        foreach ($gitIgnorePaths as $gitIgnorePath) {
            if (!strlen(trim($gitIgnorePath))) {
                continue;
            } elseif (substr($gitIgnorePath, 0, 1) === '#') {
                continue;
            } elseif (substr($gitIgnorePath, 0, 1) === '/') {
                $gitIgnorePath = '.' . $gitIgnorePath;
            } elseif (strpos($gitIgnorePath, '.') === 0) {
                if (strpos($gitIgnorePath, '/') !== 1) {
                    $gitIgnorePath = '';
                }
            } elseif (substr($gitIgnorePath, 0, 2) !== './') {
                $gitIgnorePath = './' . $gitIgnorePath;
            }
            if ($gitIgnorePath) {
                $this->excludePaths[] = $prefix . $gitIgnorePath;
            }
        }
    }
}

$tool = new getHooks($argv ?? []);
$tool->execute();

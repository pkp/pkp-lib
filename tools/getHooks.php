<?php

/**
 * @file tools/getHooks.php
 *
 * Copyright (c) 2014-2020 Simon Fraser University
 * Copyright (c) 2003-2020 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class getHooks
 * @ingroup tools
 *
 * @brief CLI tool to compile documentation on hooks in markdown
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
        './lib/pkp/locale',
        './lib/ui-library',
        './lib/pkp/tools/getHooks.php'
    ];

    /**
     * Parse and execute the import/export task.
     */
    function execute()
    {
        $this->loadIgnoreDirs(APP_ROOT . '/.gitignore');
        $this->loadIgnoreDirs(APP_ROOT . '/lib/pkp/.gitignore', './lib/pkp/');

        $this->processDir('./', function ($fileName) {

            if (substr($fileName, -4) === '.php') {
                $file = file_get_contents($fileName);

                preg_match_all('/Hook\:\:call\(\s*\'([\d\D]*?)\'/', $file, $matches);
                if (count($matches) > 1) {
                    foreach ($matches[1] as $hook) {
                        $this->hooks[] = $hook;
                    }
                }
            } elseif (substr($fileName, -4) !== '.tpl') {
                $file = file_get_contents($fileName);

                preg_match_all('/call_hook[\s]*name\=\"([\d\D]*?)\"/', $file, $matches);
                if (count($matches) > 1) {
                    foreach ($matches[1] as $hook) {
                        $this->hooks[] = $hook;
                    }
                }
            }
        });

        sort($this->hooks);

        echo join(',', $this->hooks);
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

$tool = new getHooks(isset($argv) ? $argv : array());
$tool->execute();

<?php

/**
 * @file tools/CopyAcessLogFileTool.php
 *
 * Copyright (c) 2013-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class CopyAccessLogFileTool
 * @ingroup tools
 *
 * @brief CLI tool to copy apache log files while filtering entries
 * related only to the current instalation.
 */

require(dirname(__FILE__, 4) . '/tools/bootstrap.inc.php');

use PKP\task\FileLoader;

class CopyAccessLogFileTool extends \PKP\cliTool\CommandLineTool
{
    public $_usageStatsDir;

    public $_tmpDir;

    public $_usageStatsFiles;

    public $_contextPaths;

    public $_egrepPath;

    /**
     * Constructor.
     *
     * @param array $argv command-line arguments
     */
    public function __construct($argv = [])
    {
        parent::__construct($argv);
        if (count($this->argv) < 1 || count($this->argv) > 2) {
            $this->usage();
            exit(1);
        }

        $plugin = PluginRegistry::getPlugin('generic', 'usagestatsplugin'); /** @var UsageStatsPlugin $plugin */

        $this->_usageStatsDir = $plugin->getFilesPath();
        $this->_tmpDir = "{$this->_usageStatsDir}/tmp";

        // This tool needs egrep path configured.
        $this->_egrepPath = escapeshellarg(Config::getVar('cli', 'egrep'));
        if ($this->_egrepPath == "''") {
            printf(__('admin.error.executingUtil', ['utilPath' => $this->_egrepPath, 'utilVar' => 'egrep']) . "\n");
            exit(1);
        }

        // Get a list of files currently inside the usage stats dir.
        $fileLoaderDirs = [FileLoader::FILE_LOADER_PATH_STAGING, FileLoader::FILE_LOADER_PATH_PROCESSING,
            FileLoader::FILE_LOADER_PATH_ARCHIVE, FileLoader::FILE_LOADER_PATH_REJECT];

        $usageStatsFiles = [];
        foreach ($fileLoaderDirs as $dir) {
            $dirFiles = glob("{$this->_usageStatsDir}/$dir/*");
            if (is_array($dirFiles) && count($dirFiles) > 0) {
                foreach ($dirFiles as $file) {
                    if (!is_file($file)) {
                        continue;
                    }
                    $fileBasename = pathinfo($file, PATHINFO_BASENAME);
                    if (pathinfo($file, PATHINFO_EXTENSION) == 'gz') {
                        // Always save the filename without compression extension.
                        $fileBasename = substr($fileBasename, 0, -3);
                    }
                    $usageStatsFiles[] = $fileBasename;
                }
            }
        }

        $this->_usageStatsFiles = $usageStatsFiles;

        // Get a list of context paths.
        $contextDao = & Application::getContextDAO(); /** @var ContextDAO $contextDao */
        $contextFactory = $contextDao->getAll();
        $contextPaths = [];
        while ($context = & $contextFactory->next()) {
            /** @var Context $context */
            $contextPaths[] = escapeshellarg($context->getPath());
        }
        $contextPaths = implode('/|/', $contextPaths);
        $this->_contextPaths = $contextPaths;
    }

    /**
     * Print command usage information.
     */
    public function usage()
    {
        echo "\n" . __('admin.copyAccessLogFileTool.usage', ['scriptName' => $this->scriptName]) . "\n\n";
    }

    /**
     * Process apache log files, copying and filtering them
     * to the usage stats stage directory. Can work with both
     * a specific file or a directory.
     */
    public function execute()
    {
        $fileMgr = new FileManager();
        $filesDir = Config::getVar('files', 'files_dir');
        $filePath = current($this->argv);
        $usageStatsDir = $this->_usageStatsDir;
        $tmpDir = $this->_tmpDir;

        if ($fileMgr->fileExists($tmpDir, 'dir')) {
            $fileMgr->rmtree($tmpDir);
        }

        if (!$fileMgr->mkdir($tmpDir)) {
            printf(__('admin.copyAccessLogFileTool.error.creatingFolder', ['tmpDir' => $tmpDir]) . "\n");
            exit(1);
        }

        if ($fileMgr->fileExists($filePath, 'dir')) {
            // Directory.
            $filesToCopy = glob("$filePath/*");
            foreach ($filesToCopy as $file) {
                // If a base filename is given as a parameter, check it.
                if (count($this->argv) == 2) {
                    $baseFilename = $this->argv[1];
                    if (strpos(pathinfo($file, PATHINFO_BASENAME), $baseFilename) !== 0) {
                        continue;
                    }
                }

                $this->_copyFile($file);
            }
        } else {
            if ($fileMgr->fileExists($filePath)) {
                // File.
                $this->_copyFile($filePath);
            } else {
                // Can't access.
                printf(__('admin.copyAccessLogFileTool.error.acessingFile', ['filePath' => $filePath]) . "\n");
            }
        }

        $fileMgr->rmtree($tmpDir);
    }


    //
    // Private helper methods.
    //
    /**
     * Copy the passed file, filtering entries
     * related to this installation.
     *
     * @param string $filePath
     */
    public function _copyFile($filePath)
    {
        $usageStatsFiles = $this->_usageStatsFiles;
        $usageStatsDir = $this->_usageStatsDir;
        $tmpDir = $this->_tmpDir;
        $fileName = pathinfo($filePath, PATHINFO_BASENAME);
        $fileMgr = new FileManager();

        $isCompressed = false;
        $uncompressedFileName = $fileName;
        if (pathinfo($filePath, PATHINFO_EXTENSION) == 'gz') {
            $isCompressed = true;
            $uncompressedFileName = substr($fileName, 0, -3);
        }

        if (in_array($uncompressedFileName, $usageStatsFiles)) {
            printf(__('admin.copyAccessLogFileTool.warning.fileAlreadyExists', ['filePath' => $filePath]) . "\n");
            return;
        }

        $tmpFilePath = "$tmpDir/$fileName";

        // Copy the file to a temporary directory.
        if (!$fileMgr->copyFile($filePath, $tmpFilePath)) {
            printf(__('admin.copyAccessLogFileTool.error.copyingFile', ['filePath' => $filePath, 'tmpFilePath' => $tmpFilePath]) . "\n");
            exit(1);
        }

        // Uncompress it, if needed.
        if ($isCompressed) {
            $fileMgr = new FileManager();
            try {
                $tmpFilePath = $fileMgr->decompressFile($tmpFilePath);
            } catch (Exception $e) {
                printf($e->getMessage() . "\n");
                exit(1);
            }
        }

        // Filter only entries that contains context paths.
        $egrepPath = $this->_egrepPath;
        $destinationPath = "$usageStatsDir/" . FileLoader::FILE_LOADER_PATH_STAGING . '/' . pathinfo($tmpFilePath, PATHINFO_BASENAME);
        // Each context path is already escaped, see the constructor.
        $output = null;
        $returnValue = 0;
        exec($egrepPath . " -i '" . $this->_contextPaths . "' " . escapeshellarg($tmpFilePath) . ' > ' . escapeshellarg($destinationPath), $output, $returnValue);
        if ($returnValue > 1) {
            printf(__('admin.error.executingUtil', ['utilPath' => $egrepPath, 'utilVar' => 'egrep']) . "\n");
            exit(1);
        }
        if (!$fileMgr->deleteByPath($tmpFilePath)) {
            printf(__('admin.copyAccessLogFileTool.error.deletingFile', ['tmpFilePath' => $tmpFilePath]) . "\n");
            exit(1);
        }

        printf(__('admin.copyAccessLogFileTool.success', ['filePath' => $filePath, 'destinationPath' => $destinationPath]) . "\n");
    }
}

$tool = new CopyAccessLogFileTool($argv ?? []);
$tool->execute();

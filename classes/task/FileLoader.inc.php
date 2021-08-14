<?php

/**
 * @file classes/task/FileLoader.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class FileLoader
 * @ingroup classes_task
 *
 * @brief Base scheduled task class to reliably handle files processing.
 */

namespace PKP\task;

use PKP\config\Config;
use PKP\db\DAORegistry;
use PKP\file\FileManager;
use PKP\mail\Mail;
use PKP\scheduledTask\ScheduledTask;

use PKP\scheduledTask\ScheduledTaskHelper;

abstract class FileLoader extends ScheduledTask
{
    public const FILE_LOADER_RETURN_TO_STAGING = 0x01;
    public const FILE_LOADER_ERROR_MESSAGE_TYPE = 'common.error';
    public const FILE_LOADER_WARNING_MESSAGE_TYPE = 'common.warning';

    public const FILE_LOADER_PATH_STAGING = 'stage';
    public const FILE_LOADER_PATH_PROCESSING = 'processing';
    public const FILE_LOADER_PATH_REJECT = 'reject';
    public const FILE_LOADER_PATH_ARCHIVE = 'archive';

    /** @var string The current claimed filename that the script is working on. */
    private $_claimedFilename;

    /** @var string Base directory path for the filesystem. */
    private $_basePath;

    /** @var string Stage directory path. */
    private $_stagePath;

    /** @var string Processing directory path. */
    private $_processingPath;

    /** @var string Archive directory path. */
    private $_archivePath;

    /** @var string Reject directory path. */
    private $_rejectPath;

    /** @var string Reject directory path. */
    private $_adminEmail;

    /** @var string Reject directory path. */
    private $_adminName;

    /** @var array List of staged back files after processing. */
    private $_stagedBackFiles = [];

    /** @var bool Whether to compress the archived files or not. */
    private $_compressArchives = false;

    /**
     * Constructor.
     *
     * @param array $args script arguments
     */
    public function __construct($args)
    {
        parent::__construct($args);

        // Canonicalize the base path.
        $basePath = rtrim($args[0], '/');
        $basePathFolder = basename($basePath);
        // We assume that the parent folder of the base path
        // does already exist and can be canonicalized.
        $basePathParent = realpath(dirname($basePath));
        if ($basePathParent === false) {
            $basePath = null;
        } else {
            $basePath = "$basePathParent/$basePathFolder";
        }
        $this->_basePath = $basePath;

        // Configure paths.
        if (!is_null($basePath)) {
            $this->_stagePath = "$basePath/" . FILE_LOADER_PATH_STAGING;
            $this->_archivePath = "$basePath/" . FILE_LOADER_PATH_ARCHIVE;
            $this->_rejectPath = "$basePath/" . FILE_LOADER_PATH_REJECT;
            $this->_processingPath = "$basePath/" . FILE_LOADER_PATH_PROCESSING;
        }

        // Set admin email and name.
        $siteDao = DAORegistry::getDAO('SiteDAO'); /** @var SiteDAO $siteDao */
        $site = $siteDao->getSite(); /** @var Site $site */
        $this->_adminEmail = $site->getLocalizedContactEmail();
        $this->_adminName = $site->getLocalizedContactName();
    }


    //
    // Getters and setters.
    //
    /**
     * Return the staging path.
     *
     * @return string
     */
    public function getStagePath()
    {
        return $this->_stagePath;
    }

    /**
     * Return the processing path.
     *
     * @return string
     */
    public function getProcessingPath()
    {
        return $this->_processingPath;
    }

    /**
     * Return the reject path.
     *
     * @return string
     */
    public function getRejectPath()
    {
        return $this->_rejectPath;
    }

    /**
     * Return the archive path.
     *
     * @return string
     */
    public function getArchivePath()
    {
        return $this->_archivePath;
    }

    /**
     * Return whether the archives must be compressed or not.
     *
     * @return bool
     */
    public function getCompressArchives()
    {
        return $this->_compressArchives;
    }

    /**
     * Set whether the archives must be compressed or not.
     *
     * @param bool $compressArchives
     */
    public function setCompressArchives($compressArchives)
    {
        $this->_compressArchives = $compressArchives;
    }


    //
    // Public methods
    //
    /**
     * @copydoc ScheduledTask::executeActions()
     */
    protected function executeActions()
    {
        if (!$this->checkFolderStructure()) {
            return false;
        }

        $foundErrors = false;
        while (!is_null($filePath = $this->_claimNextFile())) {
            if ($filePath === false) {
                // Problem claiming the file.
                $foundErrors = true;
                break;
            }
            try {
                $result = $this->processFile($filePath);
            } catch (Exception $e) {
                $foundErrors = true;
                $this->_rejectFile();
                $this->addExecutionLogEntry($e->getMessage(), ScheduledTaskHelper::SCHEDULED_TASK_MESSAGE_TYPE_ERROR);
                continue;
            }

            if ($result === FILE_LOADER_RETURN_TO_STAGING) {
                $foundErrors = true;
                $this->_stageFile();
                // Let the script know what files were sent back to staging,
                // so it doesn't claim them again thereby entering an infinite loop.
                $this->_stagedBackFiles[] = $this->_claimedFilename;
            } else {
                $this->_archiveFile();
            }

            if ($result) {
                $this->addExecutionLogEntry(__(
                    'admin.fileLoader.fileProcessed',
                    ['filename' => $filePath]
                ), ScheduledTaskHelper::SCHEDULED_TASK_MESSAGE_TYPE_NOTICE);
            }
        }
        return !$foundErrors;
    }

    /**
     * A public helper function that can be used to ensure
     * that the file structure has actually been installed.
     *
     * @param bool $install Set this parameter to true to
     *  install the folder structure if it is missing.
     *
     * @return bool True if the folder structure exists,
     *  otherwise false.
     */
    public function checkFolderStructure($install = false)
    {
        // Make sure that the base path is inside the private files dir.
        // The files dir has appropriate write permissions and is assumed
        // to be protected against information leak and symlink attacks.
        $filesDir = realpath(Config::getVar('files', 'files_dir'));
        if (is_null($this->_basePath) || strpos($this->_basePath, $filesDir) !== 0) {
            $this->addExecutionLogEntry(
                __('admin.fileLoader.wrongBasePathLocation', ['path' => $this->_basePath]),
                ScheduledTaskHelper::SCHEDULED_TASK_MESSAGE_TYPE_ERROR
            );
            return false;
        }

        // Check folder presence and readability.
        $pathsToCheck = [
            $this->_stagePath,
            $this->_archivePath,
            $this->_rejectPath,
            $this->_processingPath
        ];
        $fileManager = null;
        foreach ($pathsToCheck as $path) {
            if (!(is_dir($path) && is_readable($path))) {
                if ($install) {
                    // Try installing the folder if it is missing.
                    if (is_null($fileManager)) {
                        $fileManager = new FileManager();
                    }
                    $fileManager->mkdirtree($path);
                }

                // Try again.
                if (!(is_dir($path) && is_readable($path))) {
                    // Give up...
                    $this->addExecutionLogEntry(
                        __('admin.fileLoader.pathNotAccessible', ['path' => $path]),
                        ScheduledTaskHelper::SCHEDULED_TASK_MESSAGE_TYPE_ERROR
                    );
                    return false;
                }
            }
        }
        return true;
    }


    //
    // Protected methods.
    //
    /**
     * Process the passed file.
     *
     * @param string $filePath
     *
     * @see FileLoader::execute to understand
     * the expected return values.
     */
    abstract protected function processFile($filePath);

    /**
     * Move file between filesystem directories.
     *
     * @param string $sourceDir
     * @param string $destDir
     * @param string $filename
     *
     * @return string The destination path of the moved file.
     */
    protected function moveFile($sourceDir, $destDir, $filename)
    {
        $currentFilePath = "$sourceDir/$filename";
        $destinationPath = "$destDir/$filename";

        if (!rename($currentFilePath, $destinationPath)) {
            $message = __('admin.fileLoader.moveFileFailed', ['filename' => $filename,
                'currentFilePath' => $currentFilePath, 'destinationPath' => $destinationPath]);
            $this->addExecutionLogEntry($message, ScheduledTaskHelper::SCHEDULED_TASK_MESSAGE_TYPE_ERROR);

            // Script shoudl always stop if it can't manipulate files inside
            // its own directory system.
            fatalError($message);
        }

        return $destinationPath;
    }

    //
    // Private helper methods.
    //
    /**
     * Claim the first file that's inside the staging folder.
     *
     * @return mixed The claimed file path or false if
     * the claim was not successful.
     */
    private function _claimNextFile()
    {
        $stageDir = opendir($this->_stagePath);
        $processingFilePath = false;

        while ($filename = readdir($stageDir)) {
            if ($filename == '..' || $filename == '.' ||
                in_array($filename, $this->_stagedBackFiles)) {
                continue;
            }

            $processingFilePath = $this->moveFile($this->_stagePath, $this->_processingPath, $filename);
            break;
        }

        if (pathinfo($processingFilePath, PATHINFO_EXTENSION) == 'gz') {
            $fileMgr = new FileManager();
            try {
                $processingFilePath = $fileMgr->decompressFile($processingFilePath);
                $filename = pathinfo($processingFilePath, PATHINFO_BASENAME);
            } catch (Exception $e) {
                $this->moveFile($this->_processingPath, $this->_stagePath, $filename);
                $this->addExecutionLogEntry($e->getMessage(), ScheduledTaskHelper::SCHEDULED_TASK_MESSAGE_TYPE_ERROR);
                return false;
            }
        }

        if ($processingFilePath) {
            $this->_claimedFilename = $filename;
            return $processingFilePath;
        } else {
            return null;
        }
    }

    /**
     * Reject the current claimed file.
     */
    private function _rejectFile()
    {
        $this->moveFile($this->_processingPath, $this->_rejectPath, $this->_claimedFilename);
    }

    /**
     * Archive the current claimed file.
     */
    private function _archiveFile()
    {
        $this->moveFile($this->_processingPath, $this->_archivePath, $this->_claimedFilename);
        if ($this->getCompressArchives()) {
            try {
                $fileMgr = new FileManager();
                $filePath = "{$this->_archivePath}/{$this->_claimedFilename}";
                $fileMgr->compressFile($filePath);
            } catch (Exception $e) {
                $this->addExecutionLogEntry($e->getMessage(), ScheduledTaskHelper::SCHEDULED_TASK_MESSAGE_TYPE_ERROR);
            }
        }
    }

    /**
     * Stage the current claimed file.
     */
    private function _stageFile()
    {
        $this->moveFile($this->_processingPath, $this->_stagePath, $this->_claimedFilename);
    }
}

if (!PKP_STRICT_MODE) {
    class_alias('\PKP\task\FileLoader', '\FileLoader');
    foreach ([
        'FILE_LOADER_RETURN_TO_STAGING',
        'FILE_LOADER_ERROR_MESSAGE_TYPE',
        'FILE_LOADER_WARNING_MESSAGE_TYPE',
        'FILE_LOADER_PATH_STAGING',
        'FILE_LOADER_PATH_PROCESSING',
        'FILE_LOADER_PATH_REJECT',
        'FILE_LOADER_PATH_ARCHIVE',
    ] as $constantName) {
        define($constantName, constant('\FileLoader::' . $constantName));
    }
}

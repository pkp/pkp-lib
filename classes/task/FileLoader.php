<?php

/**
 * @file classes/task/FileLoader.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class FileLoader
 *
 * @ingroup classes_task
 *
 * @brief Base scheduled task class to reliably handle files processing.
 */

namespace PKP\task;

use Exception;
use PKP\config\Config;
use PKP\db\DAORegistry;
use PKP\file\FileManager;
use PKP\scheduledTask\ScheduledTask;
use PKP\scheduledTask\ScheduledTaskHelper;
use PKP\site\Site;
use PKP\site\SiteDAO;

abstract class FileLoader extends ScheduledTask
{
    public const FILE_LOADER_RETURN_TO_STAGING = 0x01;
    public const FILE_LOADER_ERROR_MESSAGE_TYPE = 'common.error';
    public const FILE_LOADER_WARNING_MESSAGE_TYPE = 'common.warning';

    public const FILE_LOADER_PATH_STAGING = 'stage';
    public const FILE_LOADER_PATH_PROCESSING = 'processing';
    public const FILE_LOADER_PATH_REJECT = 'reject';
    public const FILE_LOADER_PATH_ARCHIVE = 'archive';

    /** The current claimed filename that the script is working on. */
    private string $_claimedFilename;

    /** Base directory path for the filesystem. */
    private string $_basePath;

    /** Stage directory path. */
    private string $_stagePath;

    /** Processing directory path. */
    private string $_processingPath;

    /** Archive directory path. */
    private string $_archivePath;

    /** Reject directory path. */
    private string $_rejectPath;

    /** Admin email. */
    private string $_adminEmail;

    /** Admin name. */
    private string $_adminName;

    /** List of staged back files after processing. */
    private array $_stagedBackFiles = [];

    /** Whether to compress the archived files or not. */
    private bool $_compressArchives = false;

    /** List of files that should only be considered. */
    private array $_onlyConsiderFiles = [];

    /**
     * Constructor.
     *
     * @param array $args script arguments
     */
    public function __construct(array $args)
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
            $basePath = "{$basePathParent}/{$basePathFolder}";
        }
        $this->_basePath = $basePath;

        // Configure paths.
        if (!is_null($basePath)) {
            $this->_stagePath = "{$basePath}/" . self::FILE_LOADER_PATH_STAGING;
            $this->_archivePath = "{$basePath}/" . self::FILE_LOADER_PATH_ARCHIVE;
            $this->_rejectPath = "{$basePath}/" . self::FILE_LOADER_PATH_REJECT;
            $this->_processingPath = "{$basePath}/" . self::FILE_LOADER_PATH_PROCESSING;
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
     */
    public function getStagePath(): string
    {
        return $this->_stagePath;
    }

    /**
     * Return the processing path.
     */
    public function getProcessingPath(): string
    {
        return $this->_processingPath;
    }

    /**
     * Return the reject path.
     */
    public function getRejectPath(): string
    {
        return $this->_rejectPath;
    }

    /**
     * Return the archive path.
     */
    public function getArchivePath(): string
    {
        return $this->_archivePath;
    }

    /**
     * Return whether the archives must be compressed or not.
     */
    public function getCompressArchives(): bool
    {
        return $this->_compressArchives;
    }

    /**
     * Set whether the archives must be compressed or not.
     */
    public function setCompressArchives(bool $compressArchives): void
    {
        $this->_compressArchives = $compressArchives;
    }

    /**
     * Get the files that should only be considered.
     */
    public function getOnlyConsiderFiles(): array
    {
        return $this->_onlyConsiderFiles;
    }

    /**
     * Set the files that should only be considered.
     */
    public function setOnlyConsiderFiles(array $onlyConsiderFiles): void
    {
        $this->_onlyConsiderFiles = $onlyConsiderFiles;
    }

    //
    // Public methods
    //
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
    public function checkFolderStructure(bool $install = false): bool
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
     * @copydoc ScheduledTask::executeActions()
     */
    protected function executeActions(): bool
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

            if ($result === self::FILE_LOADER_RETURN_TO_STAGING) {
                // Send the file back to staging
                $foundErrors = true;
                $this->_stageFile();
                // Let the script know what files were sent back to staging,
                // so it doesn't claim them again thereby entering an infinite loop.
                $this->_stagedBackFiles[] = $this->_claimedFilename;
            } else {
                $this->_archiveFile();
            }

            if ($result === true) {
                $this->addExecutionLogEntry(__(
                    'admin.fileLoader.fileProcessed',
                    ['filename' => $filePath]
                ), ScheduledTaskHelper::SCHEDULED_TASK_MESSAGE_TYPE_NOTICE);
            }
        }
        return !$foundErrors;
    }

    /**
     * Process the passed file.
     *
     *  @throws \Exception
     *
     * @return mixed True or self::FILE_LOADER_RETURN_TO_STAGING
     *
     * @see FileLoader::executeActions() to understand the expected return values.
     *
     */
    abstract protected function processFile(string $filePath): bool|int;

    /**
     * Move file between filesystem directories.
     *
     * @return string The destination path of the moved file.
     */
    protected function moveFile(string $sourceDir, string $destDir, string $filename): string
    {
        $currentFilePath = "{$sourceDir}/{$filename}";
        $destinationPath = "{$destDir}/{$filename}";

        if (!rename($currentFilePath, $destinationPath)) {
            $message = __('admin.fileLoader.moveFileFailed', ['filename' => $filename,
                'currentFilePath' => $currentFilePath, 'destinationPath' => $destinationPath]);
            $this->addExecutionLogEntry($message, ScheduledTaskHelper::SCHEDULED_TASK_MESSAGE_TYPE_ERROR);

            // Script should always stop if it can't manipulate files inside
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
    private function _claimNextFile(): string|false|null
    {
        $stageDir = opendir($this->_stagePath);
        $processingFilePath = false;

        while ($filename = readdir($stageDir)) {
            if ($filename == '..' || $filename == '.' ||
                in_array($filename, $this->_stagedBackFiles) ||
                (!empty($this->_onlyConsiderFiles) && !in_array($filename, $this->_onlyConsiderFiles))) {
                continue;
            }

            $processingFilePath = $this->moveFile($this->_stagePath, $this->_processingPath, $filename);
            break;
        }

        if (pathinfo($processingFilePath, PATHINFO_EXTENSION) == 'gz') {
            $fileMgr = new FileManager();
            try {
                $processingFilePath = $fileMgr->gzDecompressFile($processingFilePath);
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
    private function _rejectFile(): void
    {
        $this->moveFile($this->_processingPath, $this->_rejectPath, $this->_claimedFilename);
    }

    /**
     * Archive the current claimed file.
     */
    private function _archiveFile(): void
    {
        $this->moveFile($this->_processingPath, $this->_archivePath, $this->_claimedFilename);
        if ($this->getCompressArchives()) {
            try {
                $fileMgr = new FileManager();
                $filePath = "{$this->_archivePath}/{$this->_claimedFilename}";
                $fileMgr->gzCompressFile($filePath);
            } catch (Exception $e) {
                $this->addExecutionLogEntry($e->getMessage(), ScheduledTaskHelper::SCHEDULED_TASK_MESSAGE_TYPE_ERROR);
            }
        }
    }

    /**
     * Stage the current claimed file.
     */
    private function _stageFile(): void
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

<?php

/**
 * @file classes/task/FileLoader.php
 *
 * Copyright (c) 2013-2015 Simon Fraser University Library
 * Copyright (c) 2003-2015 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class FileLoader
 * @ingroup classes_task
 *
 * @brief Base scheduled task class to reliably handle files processing.
 */
import('lib.pkp.classes.scheduledTask.ScheduledTask');

define('FILE_LOADER_RETURN_TO_STAGING', 0x01);

define('FILE_LOADER_PATH_STAGING', 'stage');
define('FILE_LOADER_PATH_PROCESSING', 'processing');
define('FILE_LOADER_PATH_REJECT', 'reject');
define('FILE_LOADER_PATH_ARCHIVE', 'archive');

class FileLoader extends ScheduledTask {

	/** @var string The current claimed filename that the script is working on. */
	var $_claimedFilename;

	/** @var string Base directory path for the filesystem. */
	var $_basePath;

	/** @var string Stage directory path. */
	var $_stagePath;

	/** @var string Processing directory path. */
	var $_processingPath;

	/** @var string Archive directory path. */
	var $_archivePath;

	/** @var string Reject directory path. */
	var $_rejectPath;

	/** @var array List of staged back files after processing. */
	var $_stagedBackFiles = array();

	/**
	 * Constructor.
	 * @param $args array script arguments
	 */
	function FileLoader($args) {
		parent::ScheduledTask($args);

		// Canonicalize the base path.
		$basePath = rtrim($args[0], DIRECTORY_SEPARATOR);
		$basePathFolder = basename($basePath);
		// We assume that the parent folder of the base path
		// does already exist and can be canonicalized.
		$basePathParent = realpath(dirname($basePath));
		if ($basePathParent === false) {
			$basePath = null;
		} else {
			$basePath = $basePathParent . DIRECTORY_SEPARATOR . $basePathFolder;
		}
		$this->_basePath = $basePath;

		// Configure paths.
		if (!is_null($basePath)) {
			$this->_stagePath = $basePath . DIRECTORY_SEPARATOR . FILE_LOADER_PATH_STAGING;
			$this->_archivePath = $basePath . DIRECTORY_SEPARATOR . FILE_LOADER_PATH_ARCHIVE;
			$this->_rejectPath = $basePath . DIRECTORY_SEPARATOR . FILE_LOADER_PATH_REJECT;
			$this->_processingPath = $basePath . DIRECTORY_SEPARATOR . FILE_LOADER_PATH_PROCESSING;
		}

		// Set admin email and name.
		$siteDao =& DAORegistry::getDAO('SiteDAO'); /* @var $siteDao SiteDAO */
		$site =& $siteDao->getSite(); /* @var $site Site */
		$this->_adminEmail = $site->getLocalizedContactEmail();
		$this->_adminName = $site->getLocalizedContactName();
	}


	//
	// Getters
	//
	/**
	 * Return the staging path.
	 * @return string
	 */
	function getStagePath() {
		return $this->_stagePath;
	}

	/**
	 * Return the processing path.
	 * @return string
	 */
	function getProcessingPath() {
		return $this->_processingPath;
	}

	/**
	 * Return the reject path.
	 * @return string
	 */
	function getRejectPath() {
		return $this->_rejectPath;
	}

	/**
	 * Return the archive path.
	 * @return string
	 */
	function getArchivePath() {
		return $this->_archivePath;
	}


	//
	// Public methods
	//
	/**
	 * @see ScheduledTask::executeActions()
	 */
	function executeActions() {
		if (!$this->checkFolderStructure()) return false;

		$foundErrors = false;
		while($filePath = $this->_claimNextFile()) {
			$errorMsg = null;
			$result = $this->processFile($filePath, $errorMsg);
			if ($result === false) {
				$foundErrors = true;
				$this->_rejectFile();
				$this->addExecutionLogEntry($errorMsg, SCHEDULED_TASK_MESSAGE_TYPE_ERROR);
				continue;
			}

			if ($result === FILE_LOADER_RETURN_TO_STAGING) {
				$foundErrors = true;
				$this->_stageFile();
				$this->addExecutionLogEntry($errorMsg, SCHEDULED_TASK_MESSAGE_TYPE_ERROR);
				// Let the script know what files were sent back to staging,
				// so it doesn't claim them again thereby entering an infinite loop.
				$this->_stagedBackFiles[] = $this->_claimedFilename;
			} else {
				$this->_archiveFile();
			}

			if ($result) {
				$this->addExecutionLogEntry(__('admin.fileLoader.fileProcessed',
						array('filename' => $filePath)), SCHEDULED_TASK_MESSAGE_TYPE_NOTICE);
			}
		}
		return !$foundErrors;
	}

	/**
	 * A public helper function that can be used to ensure
	 * that the file structure has actually been installed.
	 *
	 * @param $install boolean Set this parameter to true to
	 *  install the folder structure if it is missing.
	 *
	 * @return boolean True if the folder structure exists,
	 *  otherwise false.
	 */
	function checkFolderStructure($install = false) {
		// Make sure that the base path is inside the private files dir.
		// The files dir has appropriate write permissions and is assumed
		// to be protected against information leak and symlink attacks.
		$filesDir = realpath(Config::getVar('files', 'files_dir'));
		if (is_null($this->_basePath) || strpos($this->_basePath, $filesDir) !== 0) {
			$this->addExecutionLogEntry(__('admin.fileLoader.wrongBasePathLocation',
					array('path' => $this->_basePath)), SCHEDULED_TASK_MESSAGE_TYPE_ERROR);
			return false;
		}

		// Check folder presence and readability.
		$pathsToCheck = array(
			$this->_stagePath,
			$this->_archivePath,
			$this->_rejectPath,
			$this->_processingPath
		);
		$fileManager = null;
		foreach($pathsToCheck as $path) {
			if (!(is_dir($path) && is_readable($path))) {
				if ($install) {
					// Try installing the folder if it is missing.
					if (is_null($fileManager)) {
						import('lib.pkp.classes.file.FileManager');
						$fileManager = new FileManager();
					}
					$fileManager->mkdirtree($path);
				}

				// Try again.
				if (!(is_dir($path) && is_readable($path))) {
					// Give up...
					$this->addExecutionLogEntry(__('admin.fileLoader.pathNotAccessible',
							array('path' => $path)), SCHEDULED_TASK_MESSAGE_TYPE_ERROR);
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
	 * Abstract method that must be
	 * implemented by subclasses to
	 * process the passed file.
	 * @param $filePath string
	 * @param $errorMsg string Define a custom error message
	 * to be used to notify the administrator about the error.
	 * This message will be used if the return value is false.
	 * @return mixed
	 * @see FileLoader::execute to understand
	 * the expected return values.
	 */
	function processFile($filePath, &$errorMsg) {
		assert(false);
	}

	/**
	 * @see ScheduledTask::getName()
	 */
	function getName() {
		return __('admin.fileLoader');
	}


	//
	// Private helper methods.
	//
	/**
	 * Claim the first file that's inside the staging folder.
	 * @return mixed The claimed file path or false if
	 * the claim was not successful.
	 */
	function _claimNextFile() {
		$stageDir = opendir($this->_stagePath);
		$processingFilePath = false;

		while($filename = readdir($stageDir)) {
			if ($filename == '..' || $filename == '.' ||
				in_array($filename, $this->_stagedBackFiles)) continue;

			$processingFilePath = $this->_moveFile($this->_stagePath, $this->_processingPath, $filename);
			break;
		}

		if ($processingFilePath) {
			$this->_claimedFilename = $filename;
			return $processingFilePath;
		} else {
			return false;
		}
	}

	/**
	 * Reject the current claimed file.
	 */
	function _rejectFile() {
		$this->_moveFile($this->_processingPath, $this->_rejectPath, $this->_claimedFilename);
	}

	/**
	 * Archive the current claimed file.
	 */
	function _archiveFile() {
		$this->_moveFile($this->_processingPath, $this->_archivePath, $this->_claimedFilename);
	}

	/**
	 * Stage the current claimed file.
	 */
	function _stageFile() {
		$this->_moveFile($this->_processingPath, $this->_stagePath, $this->_claimedFilename);
	}

	/**
	 * Move file between filesystem directories.
	 * @param $sourceDir string
	 * @param $destDir string
	 * @param $filename string
	 * @return string The destination path of the moved file.
	 */
	function _moveFile($sourceDir, $destDir, $filename) {
		$currentFilePath = $sourceDir . DIRECTORY_SEPARATOR . $filename;
		$destinationPath = $destDir . DIRECTORY_SEPARATOR . $filename;

		if (!rename($currentFilePath, $destinationPath)) {
			$message = __('admin.fileLoader.moveFileFailed', array('filename' => $filename,
				'currentFilePath' => $currentFilePath, 'destinationPath' => $destinationPath));
			$this->addExecutionLogEntry($message, SCHEDULED_TASK_MESSAGE_TYPE_ERROR);

			// Script should always stop if it can't manipulate files inside
			// its own directory system.
			fatalError($message);
		}

		return $destinationPath;
	}
}

?>

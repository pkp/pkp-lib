<?php

/**
 * @file classes/task/FileLoader.inc.php
 *
 * Copyright (c) 2014 Simon Fraser University Library
 * Copyright (c) 2003-2014 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class FileLoader
 * @ingroup classes_task
 *
 * @brief Base scheduled task class to reliably handle files processing.
 */
import('lib.pkp.classes.scheduledTask.ScheduledTask');

define('FILE_LOADER_RETURN_TO_STAGING', 0x01);
define('FILE_LOADER_ERROR_MESSAGE_TYPE', 'common.error');
define('FILE_LOADER_WARNING_MESSAGE_TYPE', 'common.warning');

define('FILE_LOADER_PATH_STAGING', 'stage');
define('FILE_LOADER_PATH_PROCESSING', 'processing');
define('FILE_LOADER_PATH_REJECT', 'reject');
define('FILE_LOADER_PATH_ARCHIVE', 'archive');

abstract class FileLoader extends ScheduledTask {

	/** @var string This process id. */
	private $_processId = null;

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
	private $_stagedBackFiles = array();

	/**
	 * Constructor.
	 * @param $args array script arguments
	 */
	function FileLoader($args) {
		parent::ScheduledTask($args);

		// Set an initial process id and load translations (required
		// for email notifications).
		AppLocale::requireComponents(LOCALE_COMPONENT_PKP_ADMIN);
		$this->_newProcessId();

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
		$siteDao = DAORegistry::getDAO('SiteDAO'); /* @var $siteDao SiteDAO */
		$site = $siteDao->getSite(); /* @var $site Site */
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
	public function getStagePath() {
		return $this->_stagePath;
	}

	/**
	 * Return the processing path.
	 * @return string
	 */
	public function getProcessingPath() {
		return $this->_processingPath;
	}

	/**
	 * Return the reject path.
	 * @return string
	 */
	public function getRejectPath() {
		return $this->_rejectPath;
	}

	/**
	 * Return the archive path.
	 * @return string
	 */
	public function getArchivePath() {
		return $this->_archivePath;
	}


	//
	// Public methods
	//
	/**
	 * Execute the specified command.
	 *
	 * @return boolean True if no errors, otherwise false.
	 */
	public function execute() {
		// Create a new process id to identify individual execution instances.
		$this->_newProcessId();

		if (!$this->checkFolderStructure()) return false;

		$foundErrors = false;
		while($filePath = $this->_claimNextFile()) {
			try {
				$result = $this->processFile($filePath);
			} catch(Exception $e) {
				$foundErrors = true;
				$this->_rejectFile();
				$this->_notify($e->getMessage(), FILE_LOADER_WARNING_MESSAGE_TYPE);
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
	public function checkFolderStructure($install = false) {
		// Make sure that the base path is inside the private files dir.
		// The files dir has appropriate write permissions and is assumed
		// to be protected against information leak and symlink attacks.
		$filesDir = realpath(Config::getVar('files', 'files_dir'));
		if (is_null($this->_basePath) || strpos($this->_basePath, $filesDir) !== 0) {
			$this->_notify(__('admin.fileLoader.wrongBasePathLocation', array('path' => $this->_basePath)),
					FILE_LOADER_ERROR_MESSAGE_TYPE);
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
					$this->_notify(__('admin.fileLoader.pathNotAccessible', array('path' => $path)),
							FILE_LOADER_ERROR_MESSAGE_TYPE);
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
	 * @param $filePath string
	 * @return mixed
	 * @see FileLoader::execute to understand
	 * the expected return values.
	 */
	abstract protected function processFile($filePath);


	//
	// Private helper methods.
	//
	/**
	 * Set a new process id.
	 */
	private function _newProcessId() {
		$this->_processId = uniqid();
	}

	/**
	 * Claim the first file that's inside the staging folder.
	 * @return mixed The claimed file path or false if
	 * the claim was not successful.
	 */
	private function _claimNextFile() {
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
	private function _rejectFile() {
		$this->_moveFile($this->_processingPath, $this->_rejectPath, $this->_claimedFilename);
	}

	/**
	 * Archive the current claimed file.
	 */
	private function _archiveFile() {
		$this->_moveFile($this->_processingPath, $this->_archivePath, $this->_claimedFilename);
	}

	/**
	 * Stage the current claimed file.
	 */
	private function _stageFile() {
		$this->_moveFile($this->_processingPath, $this->_stagePath, $this->_claimedFilename);
	}

	/**
	 * Move file between filesystem directories.
	 * @param $sourceDir string
	 * @param $destDir string
	 * @param $filename string
	 * @return string The destination path of the moved file.
	 */
	private function _moveFile($sourceDir, $destDir, $filename) {
		$currentFilePath = $sourceDir . DIRECTORY_SEPARATOR . $filename;
		$destinationPath = $destDir . DIRECTORY_SEPARATOR . $filename;

		if (!rename($currentFilePath, $destinationPath)) {
			$message = __('admin.fileLoader.moveFileFailed', array('filename' => $filename,
				'currentFilePath' => $currentFilePath, 'destinationPath' => $destinationPath));
			$this->_notify($message, FILE_LOADER_ERROR_MESSAGE_TYPE);

			// Script shoudl always stop if it can't manipulate files inside
			// its own directory system.
			fatalError($message);
		}

		return $destinationPath;
	}

	/**
	 * Send the passed message to the administrator by email.
	 * @param $message string
	 */
	private function _notify($message, $messageType) {
		// Instantiate the email to the admin.
		import('lib.pkp.classes.mail.Mail');
		$mail = new Mail();

		// Recipient
		$mail->addRecipient($this->_adminEmail, $this->_adminName);

		// The message
		$mail->setSubject(__('admin.fileLoader.emailSubject', array('processId' => $this->_processId)) .
			' - ' . __($messageType));
		$mail->setBody($message);

		$mail->send();
	}
}

?>

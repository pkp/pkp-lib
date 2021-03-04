<?php
/**
 * @defgroup controllers_api_file File API controller
 */

/**
 * @file controllers/api/file/FileApiHandler.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class FileApiHandler
 * @ingroup controllers_api_file
 *
 * @brief Class defining an AJAX API for supplying file information.
 */

// Import the base handler.
import('classes.handler.Handler');
import('lib.pkp.classes.core.JSONMessage');
import('lib.pkp.classes.security.authorization.SubmissionFileAccessPolicy');

class FileApiHandler extends Handler {

	/**
	 * Constructor.
	 */
	function __construct() {
		parent::__construct();
		$this->addRoleAssignment(
			array(ROLE_ID_MANAGER, ROLE_ID_SUB_EDITOR, ROLE_ID_ASSISTANT, ROLE_ID_REVIEWER, ROLE_ID_AUTHOR),
			array('downloadFile', 'downloadLibraryFile', 'downloadAllFiles', 'recordDownload', 'enableLinkAction')
		);
	}

	//
	// Implement methods from PKPHandler
	//
	function authorize($request, &$args, $roleAssignments) {
		$submissionId = (int) $request->getUserVar('submissionId');
		$submissionFileId = (int) $request->getUserVar('submissionFileId');
		$fileStage = (int) $request->getUserVar('fileStage');
		$libraryFileId = $request->getUserVar('libraryFileId');

		if (!empty($submissionFileId)) {
			import('lib.pkp.classes.security.authorization.SubmissionFileAccessPolicy');
			$this->addPolicy(new SubmissionFileAccessPolicy($request, $args, $roleAssignments, SUBMISSION_FILE_ACCESS_READ, $submissionFileId));
		} else if (is_numeric($libraryFileId)) {
			import('lib.pkp.classes.security.authorization.ContextAccessPolicy');
			$this->addPolicy(new ContextAccessPolicy($request, $roleAssignments));
		} else if (!empty($fileStage) && empty($submissionFileId)) {
			$submissionFileIds = Services::get('submissionFile')->getIds([
				'submissionIds' => [$submissionId],
				'fileStages' => [$fileStage],
				'includeDependentFiles' => $fileStage === SUBMISSION_FILE_DEPENDENT,
			]);
			import('lib.pkp.classes.security.authorization.SubmissionFileAccessPolicy');
			$allFilesAccessPolicy = new PolicySet(COMBINING_DENY_OVERRIDES);
			foreach ($submissionFileIds as $submissionFileId) {
				$allFilesAccessPolicy->addPolicy(new SubmissionFileAccessPolicy($request, $args, $roleAssignments, SUBMISSION_FILE_ACCESS_READ, $submissionFileId));
			}
			$this->addPolicy($allFilesAccessPolicy);
		}

		return parent::authorize($request, $args, $roleAssignments);
	}

	//
	// Public handler methods
	//
	/**
	 * Download a file.
	 * @param $args array
	 * @param $request Request
	 */
	function downloadFile($args, $request) {
		$submissionFile = $this->getAuthorizedContextObject(ASSOC_TYPE_SUBMISSION_FILE);
		$fileId = $request->getUserVar('fileId') ?? $submissionFile->getData('fileId');
		$revisions = DAORegistry::getDAO('SubmissionFileDAO')->getRevisions($submissionFile->getId());
		$file = null;
		foreach ($revisions as $revision) {
			if ($revision->fileId == $fileId) {
				$file = $revision;
			}
		}
		if (!$file) {
			throw new Exception('File ' . $fileId . ' is not a revision of submission file ' . $submissionFile->getId());
		}
		if (!Services::get('file')->fs->has($file->path)) {
			$request->getDispatcher()->handle404();
		}

		$filename = $request->getUserVar('filename') ?? $submissionFile->getLocalizedData('name');

		// Enforce anonymous filenames for anonymous review assignments
		$reviewAssignment = $this->getAuthorizedContextObject(ASSOC_TYPE_REVIEW_ASSIGNMENT);
		if ($reviewAssignment
				&& $reviewAssignment->getReviewMethod() == SUBMISSION_REVIEW_METHOD_DOUBLEANONYMOUS
				&& $reviewAssignment->getReviewerId() == $request->getUser()->getId()) {
			AppLocale::requireComponents([LOCALE_COMPONENT_PKP_SUBMISSION, LOCALE_COMPONENT_APP_SUBMISSION]);
			$genreDao = DAORegistry::getDAO('GenreDAO'); /* @var $genreDao GenreDAO */
			$genre = $genreDao->getById($submissionFile->getData('genreId'));
			$filename = sprintf(
				'%s-%s-%d-%s-%d',
				\Stringy\Stringy::create($request->getContext()->getLocalizedData('acronym'))->toLowerCase(),
				\Stringy\Stringy::create(__('submission.list.reviewAssignment'))->dasherize(),
				$submissionFile->getData('submissionId'),
				$genre->getLocalizedName(),
				$submissionFile->getId()
			);
		}

		$filename = Services::get('file')->formatFilename($file->path, $filename);
		Services::get('file')->download((int) $fileId, $filename);
	}

	/**
	 * Download a library file.
	 * @param $args array
	 * @param $request Request
	 */
	function downloadLibraryFile($args, $request) {
		import('lib.pkp.pages.libraryFiles.LibraryFileHandler');
		$libraryFileHandler = new LibraryFileHandler($this);
		return $libraryFileHandler->downloadLibraryFile($args, $request);
	}

	/**
	 * Download all passed files.
	 * @param $args array
	 * @param $request Request
	 */
	function downloadAllFiles($args, $request) {
		// Retrieve the authorized objects.
		$submissionFiles = $this->getAuthorizedContextObject(ASSOC_TYPE_SUBMISSION_FILES);

		$files = [];
		foreach ($submissionFiles as $submissionFile) {
			$path = $submissionFile->getData('path');
			$files[$path] = Services::get('file')->formatFilename($path, $submissionFile->getLocalizedData('name'));
		}

		AppLocale::requireComponents([LOCALE_COMPONENT_PKP_SUBMISSION, LOCALE_COMPONENT_APP_SUBMISSION, LOCALE_COMPONENT_PKP_EDITOR, LOCALE_COMPONENT_APP_EDITOR]);
		$filename = !empty($args['nameLocaleKey'])
			? __($args['nameLocaleKey'])
			: __('submission.files');
		$filename = $args['submissionId'] . '-' . $filename;
		$filename = \Stringy\Stringy::create($filename)->toLowerCase()->dasherize()->regexReplace('[^a-z0-9\-\_.]', '');

		import('lib.pkp.classes.file.FileArchive');
		$fileArchive = new FileArchive();
		$archivePath = $fileArchive->create($files, rtrim(Config::getVar('files', 'files_dir'), '/'));
		if (file_exists($archivePath)) {
			$fileManager = new FileManager();
			if ($fileArchive->zipFunctional()) {
				$fileManager->downloadByPath($archivePath, 'application/x-zip', false, $filename . '.zip');
			} else {
				$fileManager->downloadByPath($archivePath, 'application/x-gtar', false, $filename . '.tar.gz');
			}
			$fileManager->deleteByPath($archivePath);
		} else {
			throw new Exception('Creating archive with submission files failed!');
		}
	}

	/**
	 * Record file download and return js event to update grid rows.
	 * @param $args array
	 * @param $request Request
	 * @return string
	 */
	function recordDownload($args, $request) {
		return $this->enableLinkAction($args, $request);
	}

	/**
	 * Returns a data changd event to re-enable the link action.  Refactored out of
	 *  recordDownload since library files do not have downloads recorded and are in a
	 *  different context.
	 * @param $args aray
	 * @param $request Request
	 * @return JSONMessage JSON object
	 */
	function enableLinkAction($args, $request) {
		return DAO::getDataChangedEvent();
	}
}



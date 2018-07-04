<?php

/**
 * @file controllers/wizard/fileUpload/form/SubmissionFilesUploadForm.inc.php
 *
 * Copyright (c) 2014-2018 Simon Fraser University
 * Copyright (c) 2003-2018 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class SubmissionFilesUploadForm
 * @ingroup controllers_wizard_fileUpload_form
 *
 * @brief Form for adding/editing a submission file
 */


import('lib.pkp.controllers.wizard.fileUpload.form.PKPSubmissionFilesUploadBaseForm');

class SubmissionFilesUploadForm extends PKPSubmissionFilesUploadBaseForm {

	/** @var array */
	var $_uploaderRoles;


	/**
	 * Constructor.
	 * @param $request Request
	 * @param $submissionId integer
	 * @param $stageId integer One of the WORKFLOW_STAGE_ID_* constants.
	 * @param $uploaderRoles array
	 * @param $fileStage integer
	 * @param $revisionOnly boolean
	 * @param $stageId integer
	 * @param $reviewRound ReviewRound
	 * @param $revisedFileId integer
	 */
	function __construct($request, $submissionId, $stageId, $uploaderRoles, $fileStage,
			$revisionOnly = false, $reviewRound = null, $revisedFileId = null, $assocType = null, $assocId = null) {

		// Initialize class.
		assert(is_null($uploaderRoles) || (is_array($uploaderRoles) && count($uploaderRoles) >= 1));
		$this->_uploaderRoles = $uploaderRoles;

		AppLocale::requireComponents(LOCALE_COMPONENT_APP_MANAGER);

		parent::__construct(
			$request, 'controllers/wizard/fileUpload/form/fileUploadForm.tpl',
			$submissionId, $stageId, $fileStage, $revisionOnly, $reviewRound, $revisedFileId, $assocType, $assocId
		);

		// Disable the genre selector for review file attachments
		if ($fileStage == SUBMISSION_FILE_REVIEW_ATTACHMENT) {
			$this->setData('isReviewAttachment', true);
		}
	}


	//
	// Getters and Setters
	//
	/**
	 * Get the uploader roles.
	 * @return array
	 */
	function getUploaderRoles() {
		assert(!is_null($this->_uploaderRoles));
		return $this->_uploaderRoles;
	}


	//
	// Implement template methods from Form
	//
	/**
	 * @copydoc Form::readInputData()
	 */
	function readInputData() {
		$this->readUserVars(array('genreId'));
		return parent::readInputData();
	}

	/**
	 * @copydoc Form::validate()
	 */
	function validate($request) {
		// Is this a revision?
		$revisedFileId = $this->getRevisedFileId();
		if ($this->getData('revisionOnly')) {
			assert($revisedFileId > 0);
		}

		// Retrieve the request context.
		$router = $request->getRouter();
		$context = $router->getContext($request);
		if (
			$this->getData('fileStage') != SUBMISSION_FILE_REVIEW_ATTACHMENT and
			!$revisedFileId
		) {
			// Add an additional check for the genre to the form.
			$this->addCheck(new FormValidatorCustom(
				$this, 'genreId', FORM_VALIDATOR_REQUIRED_VALUE,
				'submission.upload.noGenre',
				function($genreId) use ($context) {
					$genreDao = DAORegistry::getDAO('GenreDAO');
					return is_a($genreDao->getById($genreId, $context->getId()), 'Genre');
				}
			));
		}

		return parent::validate();
	}

	/**
	 * @copydoc Form::fetch()
	 */
	function fetch($request, $template = null, $display = false) {
		// Retrieve available submission file genres.
		$genreList = $this->_retrieveGenreList($request);
		$this->setData('submissionFileGenres', $genreList);

		return parent::fetch($request, $template, $display);
	}

	/**
	 * Save the submission file upload form.
	 * @see Form::execute()
	 * @param $request Request
	 * @return SubmissionFile if successful, otherwise null
	 */
	function execute($request) {
		// Identify the file genre and category.
		$revisedFileId = $this->getRevisedFileId();
		if ($revisedFileId) {
			// The file genre and category will be copied over from the revised file.
			$fileGenre = null;
		} else {
			// This is a new file so we need the file genre and category from the form.
			$fileGenre = $this->getData('genreId') ? (int)$this->getData('genreId') : null;
		}

		// Identify the uploading user.
		$user = $request->getUser();
		assert(is_a($user, 'User'));

		$assocType = $this->getData('assocType') ? (int) $this->getData('assocType') : null;
		$assocId = $this->getData('assocId') ? (int) $this->getData('assocId') : null;
		$fileStage = $this->getData('fileStage');

		// Upload the file.
		import('lib.pkp.classes.file.SubmissionFileManager');
		$submissionFileManager = new SubmissionFileManager(
			$request->getContext()->getId(),
			$this->getData('submissionId')
		);
		$submissionFile = $submissionFileManager->uploadSubmissionFile(
			'uploadedFile', $fileStage, $user->getId(),
			$revisedFileId, $fileGenre, $assocType, $assocId
		);
		if (!$submissionFile) return null;

		// Log the event.
		import('lib.pkp.classes.log.SubmissionFileLog');
		import('lib.pkp.classes.log.SubmissionFileEventLogEntry'); // constants
		SubmissionFileLog::logEvent(
			$request,
			$submissionFile,
			$revisedFileId?SUBMISSION_LOG_FILE_REVISION_UPLOAD:SUBMISSION_LOG_FILE_UPLOAD, // assocId
			$revisedFileId?'submission.event.revisionUploaded':'submission.event.fileUploaded',
			array(
				'fileStage' => $fileStage,
				'revisedFileId' => $revisedFileId,
				'fileId' => $submissionFile->getFileId(),
				'fileRevision' => $submissionFile->getRevision(),
				'originalFileName' => $submissionFile->getOriginalFileName(),
				'submissionId' => $this->getData('submissionId'),
				'username' => $user->getUsername()
			)
		);

		return $submissionFile;
	}


	//
	// Private helper methods
	//
	/**
	 * Retrieve the genre list.
	 * @param $request Request
	 * @return array
	 */
	function _retrieveGenreList($request) {
		$context = $request->getContext();
		$genreDao = DAORegistry::getDAO('GenreDAO'); /* @var $genreDao GenreDAO */
		$dependentFilesOnly = $request->getUserVar('dependentFilesOnly') ? true : false;
		$genres = $genreDao->getByDependenceAndContextId($dependentFilesOnly, $context->getId());

		// Transform the genres into an array and
		// assign them to the form.
		$genreList = array();
		while ($genre = $genres->next()) {
			$genreList[$genre->getId()] = $genre->getLocalizedName();
		}
		return $genreList;
	}
}

?>

<?php

/**
 * @file controllers/wizard/fileUpload/form/SubmissionFilesUploadConfirmationForm.inc.php
 *
 * Copyright (c) 2014-2018 Simon Fraser University
 * Copyright (c) 2003-2018 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class SubmissionFilesUploadConfirmationForm
 * @ingroup controllers_wizard_fileUpload_form
 *
 * @brief Form for adding/editing a submission file
 */


import('lib.pkp.controllers.wizard.fileUpload.form.PKPSubmissionFilesUploadBaseForm');

class SubmissionFilesUploadConfirmationForm extends PKPSubmissionFilesUploadBaseForm {
	/**
	 * Constructor.
	 * @param $request Request
	 * @param $submissionId integer
	 * @param $stageId integer One of the WORKFLOW_STAGE_ID_* constants.
	 * @param $fileStage integer
	 * @param $reviewRound object
	 * @param $revisedFileId integer
	 * @param $assocType int optional
	 * @param $assocId int optional
	 * @param $uploadedFile integer
	 */
	function __construct($request, $submissionId, $stageId, $fileStage,
			$reviewRound, $revisedFileId = null, $assocType = null, $assocId = null, $uploadedFile = null) {

		// Initialize class.
		parent::__construct(
			$request, 'controllers/wizard/fileUpload/form/fileUploadConfirmationForm.tpl',
			$submissionId, $stageId, $fileStage, false, $reviewRound, $revisedFileId, $assocType, $assocId
		);

		if (is_a($uploadedFile, 'SubmissionFile')) {
			$this->setData('uploadedFile', $uploadedFile);
		}
	}


	//
	// Implement template methods from Form
	//
	/**
	 * @copydoc Form::readInputData()
	 */
	function readInputData() {
		$this->readUserVars(array('uploadedFileId'));
		return parent::readInputData();
	}

	/**
	 * Save the submission file upload confirmation form.
	 * @see Form::execute()
	 * @return SubmissionFile if successful, otherwise null
	 */
	function execute() {
		// Retrieve the file ids of the revised and the uploaded files.
		$revisedFileId = $this->getRevisedFileId();
		$uploadedFileId = (int)$this->getData('uploadedFileId');
		if ($revisedFileId == $uploadedFileId) fatalError('The revised file id and the uploaded file id cannot be the same!');

		// Assign the new file as the latest revision of the old file.
		$submissionFileDao = DAORegistry::getDAO('SubmissionFileDAO'); /* @var $submissionFileDao SubmissionFileDAO */
		$submissionId = $this->getData('submissionId');
		$fileStage = $this->getData('fileStage');
		$newFileLatestRevision = $submissionFileDao->getLatestRevision($uploadedFileId, $fileStage, $submissionId);
		// detach the latest attached revision, because the file returned here will then be attached
		import('controllers.api.file.ManageFileApiHandler');
		$mangeFileApiHandler = new ManageFileApiHandler();
		$mangeFileApiHandler->detachEntities($newFileLatestRevision, $newFileLatestRevision->getSubmissionId(), $this->getStageId());
		if ($revisedFileId) {
			// The file was revised; update revision information
			return $submissionFileDao->setAsLatestRevision($revisedFileId, $uploadedFileId, $submissionId, $fileStage);
		} else {
			// This is a new upload, not a revision; don't do anything.
			return $newFileLatestRevision;
		}
	}
}



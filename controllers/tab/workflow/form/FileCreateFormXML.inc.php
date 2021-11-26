<?php

/**
 * @file controllers/tab/workflow/form/FileCreateFormXML.inc.php
 *
 * Copyright (c) 2014-2018 Simon Fraser University
 * Copyright (c) 2003-2018 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class FileCreateFormXML
 *
 * @brief Form for adding a XML submission file
 */


import('lib.pkp.classes.form.Form');
import('lib.pkp.classes.submission.Genre');
import('lib.pkp.classes.submission.SubmissionFile');


class FileCreateFormXML extends Form {

	/** @var array */
	var $_uploaderRoles;

	/**
	 * Constructor.
	 * @param $request Request
	 * @param $submissionId integer
	 * @param $stageId integer
	 * @param $fileStage integer
	 * @param $revisionOnly boolean
	 * @param $reviewRound ReviewRound
	 * @param $revisedFileId integer
	 * @param null $assocType
	 * @param null $assocId
	 */
	function __construct($request, $submissionId, $stageId, $fileStage, $revisionOnly = false, $reviewRound = null, $revisedFileId = null, $assocType = null, $assocId = null) {

		parent::__construct('controllers/tab/workflow/form/fileCreateFormXML.tpl');

		// Check the incoming parameters.
		if (!is_numeric($submissionId) || $submissionId <= 0 ||
			!is_numeric($fileStage) || $fileStage <= 0 ||
			!is_numeric($stageId) || $stageId < 1 || $stageId > 5 ||
			isset($assocType) !== isset($assocId)) {
			fatalError('Invalid parameters!');
		}

		$this->setData('submissionId', (int)$submissionId);
		$this->setData('stageId', (int)$stageId);
		$this->setData('fileStage', (int)$fileStage);
		$this->setData('genreId', GENRE_CATEGORY_DOCUMENT);
		$this->setData('revisionOnly', (boolean)$revisionOnly);
		$this->setData('revisedFileId', $revisedFileId ? (int)$revisedFileId : null);
		$this->setData('reviewRoundId', $reviewRound ? $reviewRound->getId() : null);
		$this->setData('assocType', $assocType ? (int)$assocType : null);
		$this->setData('assocId', $assocId ? (int)$assocId : null);

		$this->addCheck(new FormValidatorPost($this));
		$this->addCheck(new FormValidatorCSRF($this));

	}

	/**
	 * Save the submission file upload form.
	 * @see Form::execute()
	 * @return SubmissionFile if successful, otherwise null
	 */
	function execute() {

		$request = Application::getRequest();
		$user = $request->getUser();
		assert(is_a($user, 'User'));

		$fileStage = $this->getData('fileStage');
		$genreId = $this->getData('genreId');
		$revisedFileId = (int)$request->getUserVar('revisedFileId');

		import('lib.pkp.classes.file.SubmissionFileManager');
		$submissionFileManager = new SubmissionFileManager(
			$request->getContext()->getId(),
			$this->getData('submissionId')
		);
		$submissionFile = $submissionFileManager->uploadSubmissionFile('uploadedFile', $fileStage, $user->getId(), null, $genreId);

		if (!$submissionFile) return null;

		// Log the event.
		import('lib.pkp.classes.log.SubmissionFileLog');
		import('lib.pkp.classes.log.SubmissionFileEventLogEntry'); // constants
		SubmissionFileLog::logEvent(
			$request,
			$submissionFile,
			$revisedFileId ? SUBMISSION_LOG_FILE_REVISION_UPLOAD : SUBMISSION_LOG_FILE_UPLOAD, // assocId
			$revisedFileId ? 'submission.event.revisionUploaded' : 'submission.event.fileUploaded',
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

}

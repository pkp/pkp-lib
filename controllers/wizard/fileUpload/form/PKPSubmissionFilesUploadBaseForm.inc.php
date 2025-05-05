<?php

/**
 * @file controllers/wizard/fileUpload/form/PKPSubmissionFilesUploadBaseForm.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PKPSubmissionFilesUploadBaseForm
 * @ingroup controllers_wizard_fileUpload_form
 *
 * @brief Form for adding/editing a submission file
 */


import('lib.pkp.classes.form.Form');
import('lib.pkp.classes.submission.SubmissionFile');

class PKPSubmissionFilesUploadBaseForm extends Form {

	/** @var integer */
	var $_stageId;

	/** @var ReviewRound */
	var $_reviewRound;

	/** @var array the submission files for this submission and file stage */
	var $_submissionFiles;

	/**
	 * Constructor.
	 * @param $request Request
	 * @param $template string
	 * @param $submissionId integer
	 * @param $stageId integer One of the WORKFLOW_STAGE_ID_* constants.
	 * @param $fileStage integer
	 * @param $revisionOnly boolean
	 * @param $reviewRound ReviewRound
	 * @param $revisedFileId integer
	 * @param $assocType integer
	 * @param $assocId integer
	 * @param $queryId integer
	 */
	function __construct($request, $template, $submissionId, $stageId, $fileStage,
			$revisionOnly = false, $reviewRound = null, $revisedFileId = null, $assocType = null, $assocId = null, $queryId = null) {

		// Check the incoming parameters.
		if ( !is_numeric($submissionId) || $submissionId <= 0 ||
			!is_numeric($fileStage) || $fileStage <= 0 ||
			!is_numeric($stageId) || $stageId < 1 || $stageId > 5 ||
			isset($assocType) !== isset($assocId)) {
			fatalError('Invalid parameters!');
		}

		// Initialize class.
		parent::__construct($template);
		$this->_stageId = $stageId;

		if ($reviewRound) {
			$this->_reviewRound =& $reviewRound;
		} else if ($assocType == ASSOC_TYPE_REVIEW_ASSIGNMENT && !$reviewRound) {
			// Get the review assignment object.
			$reviewAssignmentDao = DAORegistry::getDAO('ReviewAssignmentDAO'); /* @var $reviewAssignmentDao ReviewAssignmentDAO */
			$reviewAssignment = $reviewAssignmentDao->getById((int) $assocId); /* @var $reviewAssignment ReviewAssignment */
			if ($reviewAssignment->getDateCompleted()) fatalError('Review already completed!');

			// Get the review round object.
			$reviewRoundDao = DAORegistry::getDAO('ReviewRoundDAO');
			$this->_reviewRound = $reviewRoundDao->getById($reviewAssignment->getReviewRoundId());
		} else if (!$assocType && !$reviewRound) {
			$reviewRound = null;
		}

		$this->setData('fileStage', (int)$fileStage);
		$this->setData('submissionId', (int)$submissionId);
		$this->setData('revisionOnly', (boolean)$revisionOnly);
		$this->setData('revisedFileId', $revisedFileId ? (int)$revisedFileId : null);
		$this->setData('reviewRoundId', $reviewRound?$reviewRound->getId():null);
		$this->setData('assocType', $assocType ? (int)$assocType : null);
		$this->setData('assocId', $assocId ? (int)$assocId : null);
		$this->setData('queryId', $queryId ? (int) $queryId : null);

		// Add validators.
		$this->addCheck(new FormValidatorPost($this));
	}


	//
	// Setters and Getters
	//
	/**
	 * Get the workflow stage id.
	 * @return integer
	 */
	function getStageId() {
		return $this->_stageId;
	}

	/**
	 * Get the review round object (if any).
	 * @return ReviewRound
	 */
	function getReviewRound() {
		return $this->_reviewRound;
	}

	/**
	 * Get the revised file id (if any).
	 * @return int the revised file id
	 */
	function getRevisedFileId() {
		return $this->getData('revisedFileId') ? (int)$this->getData('revisedFileId') : null;
	}

	/**
	 * Get the associated type
	 * @return integer
	 */
	function getAssocType() {
		return $this->getData('assocType');
	}

	/**
	 * Get the associated id.
	 * @return integer
	 */
	function getAssocId() {
		return $this->getData('assocId');
	}

	/**
	 * Get the submission files belonging to the
	 * submission and to the file stage.
	 * @return array a list of SubmissionFile instances.
	 */
	function getSubmissionFiles() {
		if (is_null($this->_submissionFiles)) {
			$submissionFileDao = DAORegistry::getDAO('SubmissionFileDAO'); /* @var $submissionFileDao SubmissionFileDAO */
			if ($this->getStageId() == WORKFLOW_STAGE_ID_INTERNAL_REVIEW || $this->getStageId() == WORKFLOW_STAGE_ID_EXTERNAL_REVIEW) {
				// If we have a review stage id then we also expect a review round.
				if (!$this->getData('fileStage') == SUBMISSION_FILE_QUERY && !is_a($this->getReviewRound(), 'ReviewRound')) {
					throw new Exception('Can not request submission files for a review stage without specifying a review round.');
				}
				// Can only upload submission files, review files, review attachments, dependent files, or query attachments.
				if (!in_array($this->getData('fileStage'), array(SUBMISSION_FILE_SUBMISSION, SUBMISSION_FILE_REVIEW_FILE, SUBMISSION_FILE_INTERNAL_REVIEW_FILE, SUBMISSION_FILE_REVIEW_ATTACHMENT, SUBMISSION_FILE_REVIEW_REVISION, SUBMISSION_FILE_INTERNAL_REVIEW_REVISION, SUBMISSION_FILE_QUERY, SUBMISSION_FILE_DEPENDENT, SUBMISSION_FILE_ATTACHMENT))) {
					throw new Exception('The file stage is not valid for the review stage.');
				}

				// Hide the revision selector for review
				// attachments to make it easier for reviewers
				$reviewRound = $this->getReviewRound();
				if ($this->getData('fileStage') == SUBMISSION_FILE_REVIEW_ATTACHMENT) {
					$this->_submissionFiles = array();
				} elseif ($reviewRound) {
					// Retrieve the submission files for the given review round.
					$submissionFilesIterator = Services::get('submissionFile')->getMany([
						'fileStages' => [(int) $this->getData('fileStage')],
						'reviewRoundIds' => [(int) $reviewRound->getId()],
						'submissionIds' => [(int) $this->getData('submissionId')],
					]);
					$this->_submissionFiles = iterator_to_array($submissionFilesIterator);
				} else {
					// No review round, e.g. for dependent or query files
					$this->_submissionFiles = array();
				}
			} else {
				$params = [
					'fileStages' => [(int) $this->getData('fileStage')],
					'submissionIds' => [(int) $this->getData('submissionId')],
				];
				if ($this->getAssocType() && $this->getAssocType() != ASSOC_TYPE_SUBMISSION) {
					$params['assocTypes'] = [$this->getAssocType()];
					$params['assocIds'] = [$this->getAssocId()];
				}
				$this->_submissionFiles = iterator_to_array(Services::get('submissionFile')->getMany($params));
			}
		}

		return $this->_submissionFiles;
	}

	/**
	 * Get the submission files possible to select/consider for revision by the given user.
	 * @param $user User
	 * @param $uploadedFile uploaded SubmissionFile
	 * @return array a list of SubmissionFile instances.
	 */
	function getRevisionSubmissionFilesSelection($user, $uploadedFile = null) {
		$stageAssignmentDao = DAORegistry::getDAO('StageAssignmentDAO'); /* @var $stageAssignmentDao StageAssignmentDAO */
		$allSubmissionFiles = $this->getSubmissionFiles();
		$submissionFiles = array();
		foreach ($allSubmissionFiles as $submissionFile) {
			// The uploaded file must be excluded from the list of revisable files.
			if ($uploadedFile && $uploadedFile->getId() == $submissionFile->getId()) continue;
			
			
			$allowedFileStages = [
                SUBMISSION_FILE_REVIEW_ATTACHMENT,
                SUBMISSION_FILE_REVIEW_FILE,
                SUBMISSION_FILE_INTERNAL_REVIEW_FILE,
            ];

			if (
				in_array($submissionFile->getFileStage(), $allowedFileStages) &&
				$stageAssignmentDao->getBySubmissionAndRoleId($submissionFile->getData('submissionId'), ROLE_ID_AUTHOR, $this->getStageId(), $user->getId())->next()
			) {
				// Authors are not permitted to revise reviewer documents.
				continue;
			}
			$submissionFiles[] = $submissionFile;
		}
		return $submissionFiles;
	}

	//
	// Implement template methods from Form
	//
	/**
	 * @copydoc Form::readInputData()
	 */
	function readInputData() {
		// Only Genre and revised file can be set in the form. All other
		// information is generated on our side.
		$this->readUserVars(array('revisedFileId'));
	}

	/**
	 * @copydoc Form::fetch()
	 */
	function fetch($request, $template = null, $display = false) {
		// Set the workflow stage.
		$this->setData('stageId', $this->getStageId());

		// Set the review round id, if any.
		$reviewRound = $this->getReviewRound();
		if (is_a($reviewRound, 'ReviewRound')) {
			$this->setData('reviewRoundId', $reviewRound->getId());
		}

		// Retrieve the uploaded file (if any).
		$uploadedFile = $this->getData('uploadedFile');

		$user = $request->getUser();

		// Initialize the list with files available for review.
		$submissionFileOptions = array();
		$currentSubmissionFileGenres = array();

		// Go through all files and build a list of files available for review.
		$revisedFileId = $this->getRevisedFileId();
		$foundRevisedFile = false;
		$submissionFiles = $this->getRevisionSubmissionFilesSelection($user, $uploadedFile);

		foreach ((array) $submissionFiles as $submissionFile) {

			// Is this the revised file?
			if ($revisedFileId && $revisedFileId == $submissionFile->getId()) {
				// This is the revised submission file, so pass its data on to the form.
				$this->setData('genreId', $submissionFile->getGenreId());
				$foundRevisedFile = true;
			}

			// Create an entry in the list of existing files which
			// the user can select from in case he chooses to upload
			// a revision.
			$fileName = $submissionFile->getLocalizedData('name') != '' ? $submissionFile->getLocalizedData('name') : __('common.untitled');

			$submissionFileOptions[$submissionFile->getId()] = $fileName;
			$currentSubmissionFileGenres[$submissionFile->getId()] = $submissionFile->getGenreId();

			$lastSubmissionFile = $submissionFile;
		}

		// If there is only one option for a file to review, and user must revise, do not show the selector.
		if (count($submissionFileOptions) == 1 && $this->getData('revisionOnly')) {
			// There was only one option, use the last added submission file
			$this->setData('revisedFileId', $lastSubmissionFile->getId());
			$this->setData('genreId', $lastSubmissionFile->getGenreId());
		}

		// If this is not a "review only" form then add a default item.
		if (count($submissionFileOptions) && !$this->getData('revisionOnly')) {
			$submissionFileOptions = array('' => __('submission.upload.uploadNewFile')) + $submissionFileOptions;
		}

		// Make sure that the revised file (if any) really was among
		// the retrieved submission files in the current file stage.
		if ($revisedFileId && !$foundRevisedFile) fatalError('Invalid revised file id!');

		// Set the review file candidate data in the template.
		$this->setData('currentSubmissionFileGenres', $currentSubmissionFileGenres);
		$this->setData('submissionFileOptions', $submissionFileOptions);

		// Show ensuring an anonymous review link.
		$context = $request->getContext();
		if ($context->getData('showEnsuringLink') && in_array($this->getStageId(), array(WORKFLOW_STAGE_ID_SUBMISSION, WORKFLOW_STAGE_ID_INTERNAL_REVIEW, WORKFLOW_STAGE_ID_EXTERNAL_REVIEW))) {
			import('lib.pkp.classes.linkAction.request.ConfirmationModal');
			$ensuringLink = new LinkAction(
				'addUser',
				new ConfirmationModal(
					__('review.anonymousPeerReview'),
					__('review.anonymousPeerReview.title')),
				__('review.anonymousPeerReview.title'));

			$templateMgr = TemplateManager::getManager($request);
			$templateMgr->assign('ensuringLink', $ensuringLink);
		}

		return parent::fetch($request, $template, $display);
	}
}



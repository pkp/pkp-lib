<?php

/**
 * @file controllers/wizard/fileUpload/FileUploadWizardHandler.inc.php
 *
 * Copyright (c) 2014-2018 Simon Fraser University
 * Copyright (c) 2003-2018 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class FileUploadWizardHandler
 * @ingroup controllers_wizard_fileUpload
 *
 * @brief A controller that handles basic server-side
 *  operations of the file upload wizard.
 */

// Import the base handler.
import('classes.handler.Handler');

// Import JSON class for use with all AJAX requests.
import('lib.pkp.classes.core.JSONMessage');

class PKPFileUploadWizardHandler extends Handler {
	/** @var integer */
	var $_fileStage;

	/** @var array */
	var $_uploaderRoles;

	/** @var boolean */
	var $_revisionOnly;

	/** @var int */
	var $_reviewRound;

	/** @var integer */
	var $_revisedFileId;

	/** @var integer */
	var $_assocType;

	/** @var integer */
	var $_assocId;


	/**
	 * Constructor
	 */
	function __construct() {
		parent::__construct();
		$this->addRoleAssignment(
			array(ROLE_ID_MANAGER, ROLE_ID_SUB_EDITOR, ROLE_ID_AUTHOR, ROLE_ID_REVIEWER, ROLE_ID_ASSISTANT),
			array(
				'startWizard', 'displayFileUploadForm',
				'uploadFile', 'confirmRevision',
				'editMetadata',
				'finishFileSubmission'
			)
		);
	}


	//
	// Implement template methods from PKPHandler
	//
	/**
	 * @copydoc PKPHandler::initialize()
	 */
	function initialize($request) {
		parent::initialize($request);
		// Configure the wizard with the authorized submission and file stage.
		// Validated in authorize.
		$this->_fileStage = (int)$request->getUserVar('fileStage');

		// Set the uploader roles (if given).
		$uploaderRoles = $request->getUserVar('uploaderRoles');
		if (!empty($uploaderRoles)) {
			$this->_uploaderRoles = array();
			$uploaderRoles = explode('-', $uploaderRoles);
			foreach($uploaderRoles as $uploaderRole) {
				if (!is_numeric($uploaderRole)) fatalError('Invalid uploader role!');
				$this->_uploaderRoles[] = (int)$uploaderRole;
			}
		}

		// Do we allow revisions only?
		$this->_revisionOnly = (boolean)$request->getUserVar('revisionOnly');
		$reviewRound = $this->getReviewRound();
		$this->_assocType = $request->getUserVar('assocType') ? (int)$request->getUserVar('assocType') : null;
		$this->_assocId = $request->getUserVar('assocId') ? (int)$request->getUserVar('assocId') : null;

		// The revised file will be non-null if we revise a single existing file.
		if ($this->getRevisionOnly() && $request->getUserVar('revisedFileId')) {
			// Validated in authorize.
			$this->_revisedFileId = (int)$request->getUserVar('revisedFileId');
		}

		// Load translations.
		AppLocale::requireComponents(
			LOCALE_COMPONENT_APP_SUBMISSION,
			LOCALE_COMPONENT_PKP_SUBMISSION,
			LOCALE_COMPONENT_PKP_COMMON,
			LOCALE_COMPONENT_APP_COMMON
		);
	}

	function authorize($request, &$args, $roleAssignments) {
		// Allow both reviewers (if in review) and context roles.
		import('lib.pkp.classes.security.authorization.ReviewStageAccessPolicy');

		$this->addPolicy(new ReviewStageAccessPolicy($request, $args, $roleAssignments, 'submissionId', $request->getUserVar('stageId')), true);

		return parent::authorize($request, $args, $roleAssignments);
	}


	//
	// Getters and Setters
	//
	/**
	 * The submission to which we upload files.
	 * @return Submission
	 */
	function getSubmission() {
		return $this->getAuthorizedContextObject(ASSOC_TYPE_SUBMISSION);
	}


	/**
	 * Get the authorized workflow stage.
	 * @return integer One of the WORKFLOW_STAGE_ID_* constants.
	 */
	function getStageId() {
		return $this->getAuthorizedContextObject(ASSOC_TYPE_WORKFLOW_STAGE);
	}

	/**
	 * Get the workflow stage file storage that
	 * we upload files to. One of the SUBMISSION_FILE_*
	 * constants.
	 * @return integer
	 */
	function getFileStage() {
		return $this->_fileStage;
	}

	/**
	 * Get the uploader roles.
	 * @return array
	 */
	function getUploaderRoles() {
		return $this->_uploaderRoles;
	}

	/**
	 * Does this uploader only allow revisions and no new files?
	 * @return boolean
	 */
	function getRevisionOnly() {
		return $this->_revisionOnly;
	}

	/**
	 * Get review round object.
	 * @return ReviewRound
	 */
	function getReviewRound() {
		return $this->getAuthorizedContextObject(ASSOC_TYPE_REVIEW_ROUND);
	}

	/**
	 * Get the id of the file to be revised (if any).
	 * @return integer
	 */
	function getRevisedFileId() {
		return $this->_revisedFileId;
	}

	/**
	 * Get the assoc type (if any)
	 * @return integer
	 */
	function getAssocType() {
		return $this->_assocType;
	}

	/**
	 * Get the assoc id (if any)
	 * @return integer
	 */
	function getAssocId() {
		return $this->_assocId;
	}

	//
	// Public handler methods
	//
	/**
	 * Displays the file upload wizard.
	 * @param $args array
	 * @param $request Request
	 * @return JSONMessage JSON object
	 */
	function startWizard($args, $request) {
		$templateMgr = TemplateManager::getManager($request);
		$reviewRound = $this->getReviewRound();
		$templateMgr->assign(array(
			'submissionId' => $this->getSubmission()->getId(),
			'stageId' => $this->getStageId(),
			'uploaderRoles' => implode('-', (array) $this->getUploaderRoles()),
			'fileStage' => $this->getFileStage(),
			'isReviewer' => $request->getUserVar('isReviewer'),
			'revisionOnly' => $this->getRevisionOnly(),
			'reviewRoundId' => is_a($reviewRound, 'ReviewRound')?$reviewRound->getId():null,
			'revisedFileId' => $this->getRevisedFileId(),
			'assocType' => $this->getAssocType(),
			'assocId' => $this->getAssocId(),
			'dependentFilesOnly' => $request->getUserVar('dependentFilesOnly'),
		));
		return $templateMgr->fetchJson('controllers/wizard/fileUpload/fileUploadWizard.tpl');
	}

	/**
	 * Render the file upload form in its initial state.
	 * @param $args array
	 * @param $request Request
	 * @return JSONMessage JSON object
	 */
	function displayFileUploadForm($args, $request) {
		// Instantiate, configure and initialize the form.
		import('lib.pkp.controllers.wizard.fileUpload.form.SubmissionFilesUploadForm');
		$submission = $this->getSubmission();
		$fileForm = new SubmissionFilesUploadForm(
			$request, $submission->getId(), $this->getStageId(), $this->getUploaderRoles(), $this->getFileStage(),
			$this->getRevisionOnly(), $this->getReviewRound(), $this->getRevisedFileId(),
			$this->getAssocType(), $this->getAssocId()
		);
		$fileForm->initData();

		// Render the form.
		return new JSONMessage(true, $fileForm->fetch($request));
	}

	/**
	 * Upload a file and render the modified upload wizard.
	 * @param $args array
	 * @param $request Request
	 * @return JSONMessage JSON object
	 */
	function uploadFile($args, $request) {
		// Instantiate the file upload form.
		$submission = $this->getSubmission();
		import('lib.pkp.controllers.wizard.fileUpload.form.SubmissionFilesUploadForm');
		$uploadForm = new SubmissionFilesUploadForm(
			$request, $submission->getId(), $this->getStageId(), null, $this->getFileStage(),
			$this->getRevisionOnly(), $this->getReviewRound(), null, $this->getAssocType(), $this->getAssocId()
		);
		$uploadForm->readInputData();

		// Validate the form and upload the file.
		if (!$uploadForm->validate()) {
			return new JSONMessage(true, $uploadForm->fetch($request));
		}

		$uploadedFile = $uploadForm->execute(); /* @var $uploadedFile SubmissionFile */
		if (!is_a($uploadedFile, 'SubmissionFile')) {
			return new JSONMessage(false, __('common.uploadFailed'));
		}

		$this->_attachEntities($uploadedFile);

		// Retrieve file info to be used in a JSON response.
		$uploadedFileInfo = $this->_getUploadedFileInfo($uploadedFile);
		$reviewRound = $this->getReviewRound();

		// If no revised file id was given then try out whether
		// the user maybe accidentally didn't identify this file as a revision.
		if (!$uploadForm->getRevisedFileId()) {
			$user = $request->getUser();
			$revisionSubmissionFilesSelection = $uploadForm->getRevisionSubmissionFilesSelection($user, $uploadedFile);
			$revisedFileId = $this->_checkForRevision($uploadedFile, $revisionSubmissionFilesSelection);
			if ($revisedFileId) {
				// Instantiate the revision confirmation form.
				import('lib.pkp.controllers.wizard.fileUpload.form.SubmissionFilesUploadConfirmationForm');
				$confirmationForm = new SubmissionFilesUploadConfirmationForm($request, $submission->getId(), $this->getStageId(), $this->getFileStage(), $reviewRound, $revisedFileId, $this->getAssocType(), $this->getAssocId(), $uploadedFile);
				$confirmationForm->initData();

				// Render the revision confirmation form.
				return new JSONMessage(true, $confirmationForm->fetch($request), '0', $uploadedFileInfo);
			}
		}

		// Advance to the next step (i.e. meta-data editing).
		return new JSONMessage(true, '', '0', $uploadedFileInfo);
	}

	/**
	 * Attach any dependent entities to a new file upload.
	 * @param $submissionFile SubmissionFile
	 */
	protected function _attachEntities($submissionFile) {
		switch ($submissionFile->getFileStage()) {
			case SUBMISSION_FILE_REVIEW_FILE:
			case SUBMISSION_FILE_REVIEW_ATTACHMENT:
			case SUBMISSION_FILE_REVIEW_REVISION:
				// Add the uploaded review file to the review round.
				$reviewRound = $this->getReviewRound();
				$submissionFileDao = DAORegistry::getDAO('SubmissionFileDAO');
				$submissionFileDao->assignRevisionToReviewRound($submissionFile->getFileId(), $submissionFile->getRevision(), $reviewRound);

				if ($submissionFile->getFileStage() == SUBMISSION_FILE_REVIEW_REVISION) {
					// Get a list of author user IDs
					$authorUserIds = array();
					$stageAssignmentDao = DAORegistry::getDAO('StageAssignmentDAO');
					$submitterAssignments = $stageAssignmentDao->getBySubmissionAndRoleId($reviewRound->getSubmissionId(), ROLE_ID_AUTHOR);
					while ($assignment = $submitterAssignments->next()) {
						$authorUserIds[] = $assignment->getUserId();
					}

					// Update the task notifications
					$notificationMgr = new NotificationManager();
					$notificationMgr->updateNotification(
						Application::getRequest(),
						array(NOTIFICATION_TYPE_PENDING_INTERNAL_REVISIONS, NOTIFICATION_TYPE_PENDING_EXTERNAL_REVISIONS),
						$authorUserIds,
						ASSOC_TYPE_SUBMISSION,
						$reviewRound->getSubmissionId()
					);

					// Update the ReviewRound status when revision is submitted
					import('lib.pkp.classes.submission.reviewRound.ReviewRoundDAO');
					$reviewRoundDao = DAORegistry::getDAO('ReviewRoundDAO');
					$reviewRoundDao->updateStatus($reviewRound);

					// Notify editors about the revision upload
					$submission = $this->getSubmission();
					$request = Application::getRequest();
					$router = $request->getRouter();
					$dispatcher = $router->getDispatcher();
					$context = $request->getContext();
					$uploader = $request->getUser();
					// If the file is uploaded by an author
					if (in_array($uploader->getId(), $authorUserIds)) {
						import('lib.pkp.classes.mail.SubmissionMailTemplate');
						$mail = new SubmissionMailTemplate($submission, 'REVISED_VERSION_NOTIFY');
						$mail->setReplyTo($context->getSetting('contactEmail'), $context->getSetting('contactName'));
						// Get editors assigned to the submission, consider also the recommendOnly editors
						$userDao = DAORegistry::getDAO('UserDAO');
						$editorsStageAssignments = $stageAssignmentDao->getEditorsAssignedToStage($submission->getId(), $this->getStageId());
						foreach ($editorsStageAssignments as $editorsStageAssignment) {
							$editorId = $editorsStageAssignment->getUserId();
							$editor = $userDao->getById($editorId);
							$mail->addRecipient($editor->getEmail(), $editor->getFullName());
						}
						// Get uploader name
						$submissionUrl = $dispatcher->url($request, ROUTE_PAGE, null, 'workflow', 'index', array($submission->getId(), $this->getStageId()));
						$mail->assignParams(array(
							'authorName' => $uploader->getFullName(),
							'editorialContactSignature' => $context->getSetting('contactName'),
							'submissionUrl' => $submissionUrl,
						));
						$mail->send();
					}
				}
				break;
		}
	}

	/**
	 * Confirm that the uploaded file is a revision of an
	 * earlier uploaded file.
	 * @param $args array
	 * @param $request Request
	 * @return JSONMessage JSON object
	 */
	function confirmRevision($args, $request) {
		// Instantiate the revision confirmation form.
		$submission = $this->getSubmission();
		import('lib.pkp.controllers.wizard.fileUpload.form.SubmissionFilesUploadConfirmationForm');
		// FIXME?: need assocType and assocId? Not sure if they would be used, so not adding now.
		$reviewRound = $this->getReviewRound();
		$confirmationForm = new SubmissionFilesUploadConfirmationForm(
			$request, $submission->getId(), $this->getStageId(), $this->getFileStage(), $reviewRound
		);
		$confirmationForm->readInputData();

		// Validate the form and revise the file.
		if ($confirmationForm->validate()) {
			if (is_a($uploadedFile = $confirmationForm->execute(), 'SubmissionFile')) {

				$this->_attachEntities($uploadedFile);

				// Go to the meta-data editing step.
				return new JSONMessage(true, '', '0', $this->_getUploadedFileInfo($uploadedFile));
			} else {

				return new JSONMessage(false, __('common.uploadFailed'));
			}
		} else {
			return new JSONMessage(true, $confirmationForm->fetch($request));
		}
	}

	/**
	 * Edit the metadata of the latest revision of
	 * the requested submission file.
	 * @param $args array
	 * @param $request Request
	 * @return JSONMessage JSON object
	 */
	function editMetadata($args, $request) {
		$metadataForm = $this->_getMetadataForm($request);
		$metadataForm->initData();
		return new JSONMessage(true, $metadataForm->fetch($request));
	}

	/**
	 * Display the final tab of the modal
	 * @param $args array
	 * @param $request Request
	 * @return JSONMessage JSON object
	 */
	function finishFileSubmission($args, $request) {
		$submission = $this->getSubmission();

		// Validation not req'd -- just generating a JSON update message.
		$fileId = (int)$request->getUserVar('fileId');

		$templateMgr = TemplateManager::getManager($request);
		$templateMgr->assign('submissionId', $submission->getId());
		$templateMgr->assign('fileId', $fileId);
		if (isset($args['fileStage'])) {
			$templateMgr->assign('fileStage', $args['fileStage']);
		}

		return $templateMgr->fetchJson('controllers/wizard/fileUpload/form/fileSubmissionComplete.tpl');
	}


	//
	// Private helper methods
	//
	/**
	 * Retrieve the requested meta-data form.
	 * @param $request Request
	 * @return SubmissionFilesMetadataForm
	 */
	function _getMetadataForm($request) {
		$submissionFile = $this->getAuthorizedContextObject(ASSOC_TYPE_SUBMISSION_FILE);
		return $submissionFile->getMetadataForm($this->getStageId(), $this->getReviewRound());
	}

	/**
	 * Check if the uploaded file has a similar name to an existing
	 * file which would then be a candidate for a revised file.
	 * @param $uploadedFile SubmissionFile
	 * @param $submissionFiles array a list of submission files to
	 *  check the uploaded file against.
	 * @return integer the if of the possibly revised file or null
	 *  if no matches were found.
	 */
	function &_checkForRevision(&$uploadedFile, &$submissionFiles) {
		// Get the file name.
		$uploadedFileName = $uploadedFile->getOriginalFileName();

		// Start with the minimal required similarity.
		$minPercentage = Config::getVar('files', 'filename_revision_match', 70);

		// Find out whether one of the files belonging to the current
		// file stage matches the given file name.
		$possibleRevisedFileId = null;
		$matchedPercentage = 0;
		foreach ((array) $submissionFiles as $submissionFile) { /* @var $submissionFile SubmissionFile */
			// Test whether the current submission file is similar
			// to the uploaded file. (Transliterate to ASCII -- the
			// similar_text function can't handle UTF-8.)
			similar_text(
				$a = Stringy\Stringy::create($uploadedFileName)->toAscii(),
				$b = Stringy\Stringy::create($submissionFile->getOriginalFileName())->toAscii(),
				$matchedPercentage
			);
			if($matchedPercentage > $minPercentage && !$this->_onlyNumbersDiffer($a, $b)) {
				// We found a file that might be a possible revision.
				$possibleRevisedFileId = $submissionFile->getFileId();

				// Reset the min percentage to this comparison's precentage
				// so that only better matches will be considered from now on.
				$minPercentage = $matchedPercentage;
			}
		}

		// Return the id of the file that we found similar.
		return $possibleRevisedFileId;
	}

	/**
	 * Helper function: check if the only difference between $a and $b
	 * is numeric. Used to exclude well-named but nearly identical file
	 * names from the revision detection pile (e.g. "Chapter 1" and
	 * "Chapter 2")
	 * @param $a string
	 * @param $b string
	 */
	function _onlyNumbersDiffer($a, $b) {
		if ($a == $b) return false;

		$pattern = '/([^0-9]*)([0-9]*)([^0-9]*)/';
		$aMatchCount = preg_match_all($pattern, $a, $aMatches, PREG_SET_ORDER);
		$bMatchCount = preg_match_all($pattern, $b, $bMatches, PREG_SET_ORDER);
		if ($aMatchCount != $bMatchCount || $aMatchCount == 0) return false;

		// Check each match. If the 1st and 3rd (text) parts all match
		// then only numbers differ in the two supplied strings.
		for ($i=0; $i<count($aMatches); $i++) {
			if ($aMatches[$i][1] != $bMatches[$i][1]) return false;
			if ($aMatches[$i][3] != $bMatches[$i][3]) return false;
		}

		// No counterexamples were found. Only numbers differ.
		return true;
	}

	/**
	 * Create an array that describes an uploaded file which can
	 * be used in a JSON response.
	 * @param SubmissionFile $uploadedFile
	 * @return array
	 */
	function _getUploadedFileInfo($uploadedFile) {
		return array(
			'uploadedFile' => array(
				'fileId' => $uploadedFile->getFileId(),
				'revision' => $uploadedFile->getRevision(),
				'name' => $uploadedFile->getLocalizedName(),
				'fileLabel' => $uploadedFile->getFileLabel(),
				'type' => $uploadedFile->getDocumentType(),
				'genreId' => $uploadedFile->getGenreId(),
			)
		);
	}
}



<?php

/**
 * @file controllers/wizard/fileUpload/FileUploadWizardHandler.inc.php
 *
 * Copyright (c) 2014-2016 Simon Fraser University Library
 * Copyright (c) 2003-2016 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class FileUploadWizardHandler
 * @ingroup controllers_wizard_fileUpload
 *
 * @brief A controller that handles basic server-side
 *  operations of the file upload wizard.
 */

// Import the base handler.
import('lib.pkp.classes.file.FileManagementHandler');

// Import JSON class for use with all AJAX requests.
import('lib.pkp.classes.core.JSONMessage');

// The percentage of characters that the name of a file
// has to share with an existing file for it to be
// considered as a revision of that file.
define('SUBMISSION_MIN_SIMILARITY_OF_REVISION', 70);

class PKPFileUploadWizardHandler extends FileManagementHandler {
	/** @var integer */
	var $_fileStage;

	/** @var array */
	var $_uploaderRoles;

	/** @var array */
	var $_uploaderGroupIds;

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
	function PKPFileUploadWizardHandler() {
		parent::Handler();
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
	function initialize($request, $args) {
		parent::initialize($request, $args);
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

		// Set the uploader group IDs (if given).
		$uploaderGroupIds = $request->getUserVar('uploaderGroupIds');
		if (!empty($uploaderGroupIds)) {
			$this->_uploaderGroupIds = array();
			$uploaderGroupIds = explode('-', $uploaderGroupIds);
			foreach($uploaderGroupIds as $uploaderGroupId) {
				if (!is_numeric($uploaderGroupId)) fatalError('Invalid uploader group ID!');
				$this->_uploaderGroupIds[] = (int)$uploaderGroupId;
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
		return parent::authorize($request, $args, $roleAssignments);
	}


	//
	// Getters and Setters
	//
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
	 * Get the uploader group IDs.
	 * @return array
	 */
	function getUploaderGroupIds() {
		return $this->_uploaderGroupIds;
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

		// Assign the submission.
		$submission = $this->getSubmission();
		$templateMgr->assign('submissionId', $submission->getId());

		// Assign the workflow stage.
		$templateMgr->assign('stageId', $this->getStageId());

		// Assign the roles allowed to upload in the given context.
		$templateMgr->assign('uploaderRoles', implode('-', (array) $this->getUploaderRoles()));

		// Assign the roles allowed to upload in the given context.
		$templateMgr->assign('uploaderGroupIds', implode('-', (array) $this->getUploaderGroupIds()));

		// Assign the file stage.
		$templateMgr->assign('fileStage', $this->getFileStage());

		// Preserve the isReviewer flag
		$templateMgr->assign('isReviewer', $request->getUserVar('isReviewer'));

		// Configure the "revision only" feature.
		$templateMgr->assign('revisionOnly', $this->getRevisionOnly());
		$reviewRound = $this->getReviewRound();
		if (is_a($reviewRound, 'ReviewRound')) {
			$templateMgr->assign('reviewRoundId', $reviewRound->getId());
		}
		$templateMgr->assign('revisedFileId', $this->getRevisedFileId());
		$templateMgr->assign('assocType', $this->getAssocType());
		$templateMgr->assign('assocId', $this->getAssocId());

		// only dependent file types?
		$templateMgr->assign('dependentFilesOnly', $request->getUserVar('dependentFilesOnly'));

		// Render the file upload wizard.
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
			$request, $submission->getId(), $this->getStageId(), $this->getUploaderRoles(), $this->getUploaderGroupIds(), $this->getFileStage(),
			$this->getRevisionOnly(), $this->getReviewRound(), $this->getRevisedFileId(),
			$this->getAssocType(), $this->getAssocId()
		);
		$fileForm->initData($args, $request);

		// Render the form.
		return new JSONMessage(true, $fileForm->fetch($request));
	}

	/**
	 * Upload a file and render the modified upload wizard.
	 * @param $args array
	 * @param $request Request
	 * @return JSONMessage JSON object
	 */
	function uploadFile($args, $request, $fileModifyCallback = null) {
		// Instantiate the file upload form.
		$submission = $this->getSubmission();
		import('lib.pkp.controllers.wizard.fileUpload.form.SubmissionFilesUploadForm');
		$uploadForm = new SubmissionFilesUploadForm(
			$request, $submission->getId(), $this->getStageId(), null, null, $this->getFileStage(),
			$this->getRevisionOnly(), $this->getReviewRound(), null, $this->getAssocType(), $this->getAssocId()
		);
		$uploadForm->readInputData();

		// Validate the form and upload the file.
		if ($uploadForm->validate($request)) {
			if (is_a($uploadedFile = $uploadForm->execute($request), 'SubmissionFile')) { /* @var $uploadedFile SubmissionFile */
				// Retrieve file info to be used in a JSON response.
				$uploadedFileInfo = $this->_getUploadedFileInfo($uploadedFile);
				$reviewRound = $this->getReviewRound();

				// If no revised file id was given then try out whether
				// the user maybe accidentally didn't identify this file as a revision.
				if (!$uploadForm->getRevisedFileId()) {
					$revisedFileId = $this->_checkForRevision($uploadedFile, $uploadForm->getSubmissionFiles());
					if ($revisedFileId) {
						// Instantiate the revision confirmation form.
						import('lib.pkp.controllers.wizard.fileUpload.form.SubmissionFilesUploadConfirmationForm');
						$confirmationForm = new SubmissionFilesUploadConfirmationForm($request, $submission->getId(), $this->getStageId(), $this->getFileStage(), $reviewRound, $revisedFileId, $this->getAssocType(), $this->getAssocId(), $uploadedFile);
						$confirmationForm->initData($args, $request);

						// Render the revision confirmation form.
						return new JSONMessage(true, $confirmationForm->fetch($request), '0', $uploadedFileInfo);
					}
				}

				// Advance to the next step (i.e. meta-data editing).
				return new JSONMessage(true, '', '0', $uploadedFileInfo);
			} else {
				return new JSONMessage(false, __('common.uploadFailed'));
			}
		} else {
			return new JSONMessage(false, array_pop($uploadForm->getErrorsArray()));
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
		if ($confirmationForm->validate($request)) {
			if (is_a($uploadedFile = $confirmationForm->execute($request), 'SubmissionFile')) {
				// Go to the meta-data editing step.
				return new JSONMessage(true, '', '0', $this->_getUploadedFileInfo($uploadedFile));
			} else {
				return new JSONMessage(false, __('common.uploadFailed'));
			}
		} else {
			return new JSONMessage(false, array_pop($confirmationForm->getErrorsArray()));
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
		$metadataForm->initData($args, $request);
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
		$minPercentage = SUBMISSION_MIN_SIMILARITY_OF_REVISION;

		// Find out whether one of the files belonging to the current
		// file stage matches the given file name.
		$possibleRevisedFileId = null;
		$matchedPercentage = 0;
		foreach ($submissionFiles as $submissionFile) { /* @var $submissionFile SubmissionFile */
			// Do not consider the uploaded file itself.
			if ($uploadedFile->getFileId() == $submissionFile->getFileId()) continue;

			// Do not consider files from different publication formats.
			if ((($uploadedFile->getAssocType() == ASSOC_TYPE_REPRESENTATION &&
				$submissionFile->getAssocType() == ASSOC_TYPE_REPRESENTATION)) &&
				$uploadedFile->getAssocId() != $submissionFile->getAssocId()) continue;

			// Test whether the current submission file is similar
			// to the uploaded file. (Transliterate to ASCII -- the
			// similar_text function can't handle UTF-8.)

			import('lib.pkp.classes.core.Transcoder');
			$transcoder = new Transcoder('UTF-8', 'ASCII', true);

			similar_text(
				$a = $transcoder->trans($uploadedFileName),
				$b = $transcoder->trans($submissionFile->getOriginalFileName()),
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
				'name' => $uploadedFile->getLocalizedName() ? $uploadedFile->getLocalizedName() : $uploadedFile->getFileLabel(),
				'type' => $uploadedFile->getDocumentType(),
				'genreId' => $uploadedFile->getGenreId(),
			)
		);
	}
}

?>

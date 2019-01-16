<?php

/**
 * @file controllers/modals/editorDecision/form/EditorDecisionWithEmailForm.inc.php
 *
 * Copyright (c) 2014-2018 Simon Fraser University
 * Copyright (c) 2003-2018 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class EditorDecisionWithEmailForm
 * @ingroup controllers_modals_editorDecision_form
 *
 * @brief Base class for the editor decision forms.
 */

import('lib.pkp.classes.controllers.modals.editorDecision.form.EditorDecisionForm');

class EditorDecisionWithEmailForm extends EditorDecisionForm {

	/** @var String */
	var $_saveFormOperation;

	//
	// Getters and Setters
	//
	/**
	 * Get the operation to save this form.
	 * @return string
	 */
	function getSaveFormOperation() {
		return $this->_saveFormOperation;
	}

	/**
	 * Set the operation to save this form.
	 * @param $saveFormOperation string
	 */
	function setSaveFormOperation($saveFormOperation) {
		$this->_saveFormOperation = $saveFormOperation;
	}

	//
	// Implement protected template methods from Form
	//
	/**
	 * @see Form::initData()
	 * @param $actionLabels array
	 */
	function initData($actionLabels = array()) {
		$request = Application::getRequest();
		$context = $request->getContext();
		$router = $request->getRouter();
		$dispatcher = $router->getDispatcher();

		$submission = $this->getSubmission();
		$user = $request->getUser();

		import('lib.pkp.classes.mail.SubmissionMailTemplate');
		$emailKeys = array(
			SUBMISSION_EDITOR_DECISION_ACCEPT => 'EDITOR_DECISION_ACCEPT',
			SUBMISSION_EDITOR_DECISION_DECLINE => 'EDITOR_DECISION_DECLINE',
			SUBMISSION_EDITOR_DECISION_INITIAL_DECLINE => 'EDITOR_DECISION_INITIAL_DECLINE',
			SUBMISSION_EDITOR_DECISION_EXTERNAL_REVIEW => 'EDITOR_DECISION_SEND_TO_EXTERNAL',
			SUBMISSION_EDITOR_DECISION_RESUBMIT => 'EDITOR_DECISION_RESUBMIT',
			SUBMISSION_EDITOR_DECISION_PENDING_REVISIONS => 'EDITOR_DECISION_REVISIONS',
			SUBMISSION_EDITOR_DECISION_SEND_TO_PRODUCTION => 'EDITOR_DECISION_SEND_TO_PRODUCTION',
		);

		$email = new SubmissionMailTemplate($submission, $emailKeys[$this->getDecision()]);

		$submissionUrl = $dispatcher->url($request, ROUTE_PAGE, null, 'authorDashboard', 'submission', $submission->getId());
		$email->assignParams(array(
			'authorName' => $submission->getAuthorString(),
			'editorialContactSignature' => $user->getContactSignature(),
			'submissionUrl' => $submissionUrl,
		));
		$email->replaceParams();

		// If we are in review stage we need a review round.
		$reviewRound = $this->getReviewRound();
		if (is_a($reviewRound, 'ReviewRound')) {
			$this->setData('reviewRoundId', $reviewRound->getId());
		}

		$data = array(
			'submissionId' => $submission->getId(),
			'decision' => $this->getDecision(),
			'authorName' => $submission->getAuthorString(),
			'personalMessage' => $email->getBody(),
			'actionLabel' => $actionLabels[$this->getDecision()]
		);
		foreach($data as $key => $value) {
			$this->setData($key, $value);
		}

		return parent::initData();
	}

	/**
	 * @copydoc Form::readInputData()
	 */
	function readInputData() {
		$this->readUserVars(array('personalMessage', 'selectedAttachments', 'skipEmail', 'selectedLibraryFiles'));
		parent::readInputData();
	}

	/**
	 * @copydoc EditorDecisionForm::fetch()
	 */
	function fetch($request, $template = null, $display = false) {

		$templateMgr = TemplateManager::getManager($request);

		// On the review stage, determine if any reviews are available for import
		$stageId = $this->getStageId();
		if ($stageId == WORKFLOW_STAGE_ID_INTERNAL_REVIEW || $stageId == WORKFLOW_STAGE_ID_EXTERNAL_REVIEW) {
			$reviewsAvailable = false;
			$submission = $this->getSubmission();
			$reviewRound = $this->getReviewRound();
			$reviewAssignmentDao = DAORegistry::getDAO('ReviewAssignmentDAO');
			$reviewAssignments = $reviewAssignmentDao->getBySubmissionId($submission->getId(), $reviewRound->getId());
			foreach ($reviewAssignments as $reviewAssignment) {
				if ($reviewAssignment->getDateCompleted() != null) {
					$reviewsAvailable = true;
					break;
				}
			}

			$templateMgr->assign('reviewsAvailable', $reviewsAvailable);

			// Retrieve a URL to fetch the reviews
			if ($reviewsAvailable) {
				$router = $request->getRouter();
				$this->setData(
					'peerReviewUrl',
					$router->url(
						$request, null, null,
						'importPeerReviews', null,
						array(
							'submissionId' => $submission->getId(),
							'stageId' => $stageId,
							'reviewRoundId' => $reviewRound->getId()
						)
					)
				);

			}
		}

		// When this form is being used in review stages, we need a different
		// save operation to allow the EditorDecisionHandler authorize the review
		// round object.
		if ($this->getSaveFormOperation()) {
			$templateMgr = TemplateManager::getManager($request);
			$templateMgr->assign('saveFormOperation', $this->getSaveFormOperation());
		}

		$templateMgr->assign('allowedVariables', $this->_getAllowedVariables($request));
		$templateMgr->assign('allowedVariablesType', $this->_getAllowedVariablesType());

		return parent::fetch($request, $template, $display);
	}


	//
	// Private helper methods
	//
	/**
	 * Retrieve the last review round and update it with the new status.
	 *
	 * The review round status is typically set according to the statuses of its
	 * ReviewAssignments. This method overrides that status and sets a new one
	 * based on an EditorDecision.
	 *
	 * @param $submission Submission
	 * @param $status integer One of the REVIEW_ROUND_STATUS_* constants.
	 */
	function _updateReviewRoundStatus($submission, $status, $reviewRound = null) {
		$reviewRoundDao = DAORegistry::getDAO('ReviewRoundDAO'); /* @var $reviewRoundDao ReviewRoundDAO */
		if (!$reviewRound) {
			$reviewRound = $reviewRoundDao->getLastReviewRoundBySubmissionId($submission->getId());
		}

		// If we don't have a review round, it's because the submission is being
		// accepted without starting any of the review stages. In that case we
		// do nothing.
		if (is_a($reviewRound, 'ReviewRound')) {
			$reviewRoundDao->updateStatus($reviewRound, $status);
		}
	}

	/**
	 * Sends an email with a personal message and the selected
	 * review attachements to the author. Also marks review attachments
	 * selected by the editor as "viewable" for the author.
	 * @param $submission Submission
	 * @param $emailKey string An email template.
	 * @param $request PKPRequest
	 */
	function _sendReviewMailToAuthor($submission, $emailKey, $request) {
		// Send personal message to author.
		import('lib.pkp.classes.mail.SubmissionMailTemplate');
		$email = new SubmissionMailTemplate($submission, $emailKey, null, null, null, false);
		$email->setBody($this->getData('personalMessage'));

		// Get submission authors in the same way as for the email template form,
		// that editor sees. This also ensures that the recipient list is not empty.
		$authors = $submission->getAuthors(true);
		foreach($authors as $author) {
			$email->addRecipient($author->getEmail(), $author->getFullName());
		}

		DAORegistry::getDAO('SubmissionEmailLogDAO'); // Load constants
		$email->setEventType(SUBMISSION_EMAIL_EDITOR_NOTIFY_AUTHOR);

		// Get review round.
		$reviewRound = $this->getReviewRound();

		if(is_a($reviewRound, 'ReviewRound')) {
			// Retrieve review indexes.
			$reviewAssignmentDao = DAORegistry::getDAO('ReviewAssignmentDAO'); /* @var $reviewAssignmentDao ReviewAssignmentDAO */
			$reviewIndexes = $reviewAssignmentDao->getReviewIndexesForRound($submission->getId(), $reviewRound->getId());
			assert(is_array($reviewIndexes));

			// Add a review index for review attachments not associated with
			// a review assignment (i.e. attachments uploaded by the editor).
			$lastIndex = end($reviewIndexes);
			$reviewIndexes[-1] = $lastIndex + 1;

			// Attach the selected reviewer attachments to the email.
			$submissionFileDao = DAORegistry::getDAO('SubmissionFileDAO'); /* @var $submissionFileDao SubmissionFileDAO */
			$selectedAttachments = $this->getData('selectedAttachments');
			if(is_array($selectedAttachments)) {
				foreach ($selectedAttachments as $fileId) {

					// Retrieve the submission file.
					$submissionFile = $submissionFileDao->getLatestRevision($fileId);
					assert(is_a($submissionFile, 'SubmissionFile'));

					// Check the association information.
					if($submissionFile->getAssocType() == ASSOC_TYPE_REVIEW_ASSIGNMENT) {
						// The review attachment has been uploaded by a reviewer.
						$reviewAssignmentId = $submissionFile->getAssocId();
						assert(is_numeric($reviewAssignmentId));
					} else {
						// The review attachment has been uploaded by the editor.
						$reviewAssignmentId = -1;
					}

					// Identify the corresponding review index.
					assert(isset($reviewIndexes[$reviewAssignmentId]));
					$reviewIndex = $reviewIndexes[$reviewAssignmentId];
					assert(!is_null($reviewIndex));

					// Add the attachment to the email.
					$email->addAttachment(
						$submissionFile->getFilePath(),
						PKPString::enumerateAlphabetically($reviewIndex).'-'.$submissionFile->getOriginalFileName()
					);

					// Update submission file to set viewable as true, so author
					// can view the file on their submission summary page.
					$submissionFile->setViewable(true);
					$submissionFileDao->updateObject($submissionFile);
				}
			}
		}

		// Attach the selected Library files as attachments to the email.
		import('classes.file.LibraryFileManager');
		$libraryFileDao = DAORegistry::getDAO('LibraryFileDAO'); /* @var $libraryFileDao LibraryFileDAO */
		$selectedLibraryFilesAttachments = $this->getData('selectedLibraryFiles');
		if(is_array($selectedLibraryFilesAttachments)) {
			foreach ($selectedLibraryFilesAttachments as $fileId) {
				// Retrieve the Library file.
				$libraryFile = $libraryFileDao->getById($fileId);
				assert(is_a($libraryFile, 'LibraryFile'));

				$libraryFileManager = new LibraryFileManager($libraryFile->getContextId());

				// Add the attachment to the email.
				$email->addAttachment(
					$libraryFileManager->getBasePath() .  $libraryFile->getOriginalFileName(),
					$libraryFile->getOriginalFileName()
				);
			}
		}

		// Send the email.
		if (!$this->getData('skipEmail')) {
			$router = $request->getRouter();
			$dispatcher = $router->getDispatcher();
			$context = $request->getContext();
			$user = $request->getUser();
			$email->assignParams(array(
				'submissionUrl' => $dispatcher->url($request, ROUTE_PAGE, null, 'authorDashboard', 'submission', $submission->getId()),
				'contextName' => $context->getLocalizedName(),
				'authorName' => $submission->getAuthorString(),
				'editorialContactSignature' => $user->getContactSignature(),
			));
			if (!$email->send($request)) {
				import('classes.notification.NotificationManager');
				$notificationMgr = new NotificationManager();
				$notificationMgr->createTrivialNotification($request->getUser()->getId(), NOTIFICATION_TYPE_ERROR, array('contents' => __('email.compose.error')));
			}
		}
	}

	/**
	 * Get a list of allowed email template variables.
	 * @param $request PKPRequest Request object
	 * @return array
	 */
	function _getAllowedVariables($request) {
		$router = $request->getRouter();
		$dispatcher = $router->getDispatcher();
		$submission = $this->getSubmission();
		$user = $request->getUser();
		return array(
			'submissionUrl' => __('common.url'),
			'contextName' => $request->getContext()->getLocalizedName(),
			'editorialContactSignature' => strip_tags($user->getContactSignature(), "<br>"),
			'submissionTitle' => strip_tags($submission->getLocalizedTitle()),
			'authorName' => strip_tags($submission->getAuthorString()),
		);
	}

	/**
	 * Get a list of allowed email template variables type.
	 * @param $request PKPRequest Request object
	 * @return array
	 */
	function _getAllowedVariablesType() {
		return array(
			'contextName' => INSERT_TAG_VARIABLE_TYPE_PLAIN_TEXT,
			'editorialContactSignature' => INSERT_TAG_VARIABLE_TYPE_PLAIN_TEXT,
			'submissionTitle' => INSERT_TAG_VARIABLE_TYPE_PLAIN_TEXT,
			'authorName' => INSERT_TAG_VARIABLE_TYPE_PLAIN_TEXT,
		);
	}
}



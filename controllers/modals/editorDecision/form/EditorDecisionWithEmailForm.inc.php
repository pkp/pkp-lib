<?php

/**
 * @file controllers/modals/editorDecision/form/EditorDecisionWithEmailForm.inc.php
 *
 * Copyright (c) 2014 Simon Fraser University Library
 * Copyright (c) 2003-2014 John Willinsky
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

	/**
	 * Constructor.
	 * @param $submission Submission
	 * @param $decision integer
	 * @param $stageId integer
	 * @param $template string The template to display
	 * @param $reviewRound ReviewRound
	 */
	function EditorDecisionWithEmailForm($submission, $decision, $stageId, $template, $reviewRound = null) {
		parent::EditorDecisionForm($submission, $decision, $stageId, $template, $reviewRound);
	}

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
	 * @copydoc Form::initData()
	 */
	function initData($args, $request, $actionLabels) {
		$context = $request->getContext();
		$router = $request->getRouter();
		$dispatcher = $router->getDispatcher();

		$submission = $this->getSubmission();
		$submitter = $submission->getUser();
		$user = $request->getUser();

		import('lib.pkp.classes.mail.SubmissionMailTemplate');
		$emailKeys = array(
			SUBMISSION_EDITOR_DECISION_ACCEPT => 'EDITOR_DECISION_ACCEPT',
			SUBMISSION_EDITOR_DECISION_DECLINE => 'EDITOR_DECISION_DECLINE',
			SUBMISSION_EDITOR_DECISION_EXTERNAL_REVIEW => 'EDITOR_DECISION_SEND_TO_EXTERNAL',
			SUBMISSION_EDITOR_DECISION_RESUBMIT => 'EDITOR_DECISION_RESUBMIT',
			SUBMISSION_EDITOR_DECISION_PENDING_REVISIONS => 'EDITOR_DECISION_REVISIONS',
			SUBMISSION_EDITOR_DECISION_SEND_TO_PRODUCTION => 'EDITOR_DECISION_SEND_TO_PRODUCTION',
		);

		$email = new SubmissionMailTemplate($submission, $emailKeys[$this->getDecision()]);

		$paramArray = array(
			'authorName' => $submitter->getFullName(),
			'editorialContactSignature' => $user->getContactSignature(),
			'authorUsername' => $submitter->getUsername(),
			'submissionUrl' => $dispatcher->url($request, ROUTE_PAGE, null, 'authorDashboard', 'submission', $submission->getId()),
		);
		$email->assignParams($paramArray);

		// If we are in review stage we need a review round.
		$reviewRound = $this->getReviewRound();
		if (is_a($reviewRound, 'ReviewRound')) {
			$this->setData('reviewRoundId', $reviewRound->getId());
		}

		$data = array(
			'submissionId' => $submission->getId(),
			'decision' => $this->getDecision(),
			'authorName' => $submission->getAuthorString(),
			'personalMessage' => $email->getBody() . "\n" . $context->getSetting('emailSignature'),
			'actionLabel' => $actionLabels[$this->getDecision()]
		);
		foreach($data as $key => $value) {
			$this->setData($key, $value);
		}

		return parent::initData($args, $request);
	}

	/**
	 * @copydoc Form::readInputData()
	 */
	function readInputData() {
		$this->readUserVars(array('personalMessage', 'selectedAttachments', 'skipEmail'));
		parent::readInputData();
	}

	/**
	 * @copydoc Form::fetch()
	 */
	function fetch($request) {
		// No all decision forms need a review round.
		// Try to get a review round.
		$reviewRound = $this->getReviewRound();

		// If we have a review round, then we are in a review stage.
		if (is_a($reviewRound, 'ReviewRound')) {
			// URL to retrieve peer reviews:
			$router = $request->getRouter();
			$submission = $this->getSubmission();
			$stageId = $reviewRound->getStageId();
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

		// When this form is being used in review stages, we need a different
		// save operation to allow the EditorDecisionHandler authorize the review
		// round object.
		if ($this->getSaveFormOperation()) {
			$templateMgr = TemplateManager::getManager($request);
			$templateMgr->assign('saveFormOperation', $this->getSaveFormOperation());
		}

		return parent::fetch($request);
	}


	//
	// Private helper methods
	//
	/**
	 * Retrieve the last review round and update it with the new status.
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
			$reviewRoundDao->updateStatus($reviewRound, null, $status);
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
		$submitter = $submission->getUser();
		import('lib.pkp.classes.mail.SubmissionMailTemplate');
		$email = new SubmissionMailTemplate($submission, $emailKey, null, null, null, false);
		$email->setBody($this->getData('personalMessage'));
		$email->addRecipient($submitter->getEmail(), $submitter->getFullName());
		$email->setEventType(SUBMISSION_EMAIL_EDITOR_NOTIFY_AUTHOR);

		$userStageAssignmentDao = DAORegistry::getDAO('UserStageAssignmentDAO');
		$authorStageParticipants = $userStageAssignmentDao->getUsersBySubmissionAndStageId($submission->getId(), $submission->getStageId(), null, ROLE_ID_AUTHOR);
		while ($author = $authorStageParticipants->next()) {
			if (preg_match('{^' . quotemeta($submitter->getEmail()) . '$}', $author->getEmail())) {
				$email->addRecipient($author->getEmail(), $author->getFullName());
			} else {
				$email->addCc($author->getEmail(), $author->getFullName());
			}
		}

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
						String::enumerateAlphabetically($reviewIndex).'-'.$submissionFile->getOriginalFileName()
					);

					// Update submission file to set viewable as true, so author
					// can view the file on their submission summary page.
					$submissionFile->setViewable(true);
					$submissionFileDao->updateObject($submissionFile);
				}
			}
		}

		// Send the email.
		if (!$this->getData('skipEmail')) {
			$router = $request->getRouter();
			$dispatcher = $router->getDispatcher();
			$paramArray = array(
				'authorUsername' => $submitter->getUsername(),
				'submissionUrl' => $dispatcher->url($request, ROUTE_PAGE, null, 'authorDashboard', 'submission', $submission->getId()),
			);
			$email->assignParams($paramArray);
			$email->send($request);
		}
	}
}

?>

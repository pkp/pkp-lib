<?php

/**
 * @file controllers/modals/editorDecision/form/PromoteForm.inc.php
 *
 * Copyright (c) 2014-2018 Simon Fraser University
 * Copyright (c) 2003-2018 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class PromoteForm
 * @ingroup controllers_modals_editorDecision_form
 *
 * @brief Form for promoting a submission (to external review or editing)
 */

import('lib.pkp.controllers.modals.editorDecision.form.EditorDecisionWithEmailForm');

// Access decision actions constants.
import('classes.workflow.EditorDecisionActionsManager');

class PromoteForm extends EditorDecisionWithEmailForm {

	/**
	 * Constructor.
	 * @param $submission Submission
	 * @param $decision int
	 * @param $stageId int
	 * @param $reviewRound ReviewRound
	 */
	function __construct($submission, $decision, $stageId, $reviewRound = null) {
		if (!in_array($decision, $this->_getDecisions())) {
			fatalError('Invalid decision!');
		}

		$this->setSaveFormOperation('savePromote');

		parent::__construct(
			$submission, $decision, $stageId,
			'controllers/modals/editorDecision/form/promoteForm.tpl',
			$reviewRound
		);

		AppLocale::requireComponents(LOCALE_COMPONENT_PKP_MANAGER);
	}


	//
	// Implement protected template methods from Form
	//
	/**
	 * @copydoc EditorDecisionWithEmailForm::initData()
	 */
	function initData($actionLabels = array()) {
		$request = Application::getRequest();
		$actionLabels = EditorDecisionActionsManager::getActionLabels($request->getContext(), $this->_getDecisions());

		$submission = $this->getSubmission();
		$this->setData('stageId', $this->getStageId());

		// If payments are enabled for this stage/form, default to requiring them
		$this->setData('requestPayment', true);

		return parent::initData($actionLabels);
	}

	/**
	 * @copydoc Form::readInputData()
	 */
	function readInputData() {
		$this->readUserVars(array('requestPayment'));
		parent::readInputData();
	}

	/**
	 * @copydoc Form::execute()
	 */
	function execute() {
		$request = Application::getRequest();

		// Retrieve the submission.
		$submission = $this->getSubmission();

		// Get this form decision actions labels.
		$actionLabels = EditorDecisionActionsManager::getActionLabels($request->getContext(), $this->_getDecisions());

		// Record the decision.
		$reviewRound = $this->getReviewRound();
		$decision = $this->getDecision();
		import('lib.pkp.classes.submission.action.EditorAction');
		$editorAction = new EditorAction();
		$editorAction->recordDecision($request, $submission, $decision, $actionLabels, $reviewRound);

		// Identify email key and status of round.
		import('lib.pkp.classes.file.SubmissionFileManager');
		$submissionFileManager = new SubmissionFileManager($submission->getContextId(), $submission->getId());
		switch ($decision) {
			case SUBMISSION_EDITOR_DECISION_ACCEPT:
				$emailKey = 'EDITOR_DECISION_ACCEPT';
				$status = REVIEW_ROUND_STATUS_ACCEPTED;

				$this->_updateReviewRoundStatus($submission, $status, $reviewRound);

				// Move to the editing stage.
				$editorAction->incrementWorkflowStage($submission, WORKFLOW_STAGE_ID_EDITING, $request);

				// Bring in the SUBMISSION_FILE_* constants.
				import('lib.pkp.classes.submission.SubmissionFile');
				// Bring in the Manager (we need it).
				import('lib.pkp.classes.file.SubmissionFileManager');

				$submissionFileDao = DAORegistry::getDAO('SubmissionFileDAO'); /* @var $submissionFileDao SubmissionFileDAO */

				$selectedFiles = $this->getData('selectedFiles');
				if(is_array($selectedFiles)) {
					foreach ($selectedFiles as $fileId) {
						$revisionNumber = $submissionFileDao->getLatestRevisionNumber($fileId);
						$submissionFileManager->copyFileToFileStage($fileId, $revisionNumber, SUBMISSION_FILE_FINAL, null, true);
					}
				}

				// Send email to the author.
				$this->_sendReviewMailToAuthor($submission, $emailKey, $request);
				break;

			case SUBMISSION_EDITOR_DECISION_EXTERNAL_REVIEW:
				$emailKey = 'EDITOR_DECISION_SEND_TO_EXTERNAL';
				$status = REVIEW_ROUND_STATUS_SENT_TO_EXTERNAL;

				$this->_updateReviewRoundStatus($submission, $status, $reviewRound);

				// Move to the external review stage.
				$editorAction->incrementWorkflowStage($submission, WORKFLOW_STAGE_ID_EXTERNAL_REVIEW, $request);

				// Create an initial external review round.
				$this->_initiateReviewRound($submission, WORKFLOW_STAGE_ID_EXTERNAL_REVIEW, $request, REVIEW_ROUND_STATUS_PENDING_REVIEWERS);

				// Send email to the author.
				$this->_sendReviewMailToAuthor($submission, $emailKey, $request);
				break;
			case SUBMISSION_EDITOR_DECISION_SEND_TO_PRODUCTION:
				$emailKey = 'EDITOR_DECISION_SEND_TO_PRODUCTION';
				// FIXME: this is copy-pasted from above, save the FILE_GALLEY.

				// Move to the editing stage.
				$editorAction->incrementWorkflowStage($submission, WORKFLOW_STAGE_ID_PRODUCTION, $request);

				// Bring in the SUBMISSION_FILE_* constants.
				import('lib.pkp.classes.submission.SubmissionFile');
				// Bring in the Manager (we need it).
				import('lib.pkp.classes.file.SubmissionFileManager');

				// Move the revisions to the next stage
				$submissionFileDao = DAORegistry::getDAO('SubmissionFileDAO'); /* @var $submissionFileDao SubmissionFileDAO */

				$selectedFiles = $this->getData('selectedFiles');
				if(is_array($selectedFiles)) {
					foreach ($selectedFiles as $fileId) {
						$revisionNumber = $submissionFileDao->getLatestRevisionNumber($fileId);
						$submissionFileManager->copyFileToFileStage($fileId, $revisionNumber, SUBMISSION_FILE_PRODUCTION_READY);
					}
				}
				// Send email to the author.
				$this->_sendReviewMailToAuthor($submission, $emailKey, $request);
				break;
			default:
				fatalError('Unsupported decision!');
		}

		if ($this->getData('requestPayment')) {
			$context = $request->getContext();
			$stageDecisions = EditorDecisionActionsManager::getStageDecisions($context, $this->getStageId());
			$decisionData = $stageDecisions[$decision];
			if (isset($decisionData['paymentType'])) {
				$paymentType = $decisionData['paymentType'];

				// Queue a payment.
				$paymentManager = Application::getPaymentManager($context);
				$queuedPayment = $paymentManager->createQueuedPayment($request, $paymentType, $request->getUser()->getId(), $submission->getId(), $decisionData['paymentAmount'], $decisionData['paymentCurrency']);
				$paymentManager->queuePayment($queuedPayment);

				// Notify any authors that this needs payment.
				$notificationMgr = new NotificationManager();
				$stageAssignmentDao = DAORegistry::getDAO('StageAssignmentDAO');
				$stageAssignments = $stageAssignmentDao->getBySubmissionAndRoleId($submission->getId(), ROLE_ID_AUTHOR, null);
				$userIds = array();
				while ($stageAssignment = $stageAssignments->next()) {
					if (!in_array($stageAssignment->getUserId(), $userIds)) {
						$notificationMgr->createNotification($request, $stageAssignment->getUserId(), NOTIFICATION_TYPE_PAYMENT_REQUIRED,
							$context->getId(), ASSOC_TYPE_QUEUED_PAYMENT, $queuedPayment->getId(), NOTIFICATION_LEVEL_TASK);
						$userIds[] = $stageAssignment->getUserId();
					}
				}
			}
		}
	}

	//
	// Private functions
	//
	/**
	 * Get this form decisions.
	 * @return array
	 */
	function _getDecisions() {
		return array(
			SUBMISSION_EDITOR_DECISION_EXTERNAL_REVIEW,
			SUBMISSION_EDITOR_DECISION_ACCEPT,
			SUBMISSION_EDITOR_DECISION_SEND_TO_PRODUCTION
		);
	}
}



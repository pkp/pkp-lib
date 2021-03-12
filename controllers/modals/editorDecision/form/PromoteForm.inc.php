<?php

/**
 * @file controllers/modals/editorDecision/form/PromoteForm.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
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
	 * @param Submission $submission
	 * @param int $decision
	 * @param int $stageId
	 * @param ReviewRound $reviewRound
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
	function initData($actionLabels = []) {
		$request = Application::get()->getRequest();
		$actionLabels = (new EditorDecisionActionsManager())->getActionLabels($request->getContext(), $this->getSubmission(), $this->getStageId(), $this->_getDecisions());

		$this->setData('stageId', $this->getStageId());

		// If payments are enabled for this stage/form, default to requiring them
		$this->setData('requestPayment', true);

		return parent::initData($actionLabels);
	}

	/**
	 * @copydoc Form::readInputData()
	 */
	function readInputData() {
		$this->readUserVars(['requestPayment']);
		parent::readInputData();
	}

	/**
	 * @copydoc Form::execute()
	 */
	function execute(...$functionParams) {
		parent::execute(...$functionParams);

		$request = Application::get()->getRequest();

		// Retrieve the submission.
		$submission = $this->getSubmission();

		// Get this form decision actions labels.
		$actionLabels = (new EditorDecisionActionsManager())->getActionLabels($request->getContext(), $submission, $this->getStageId(), $this->_getDecisions());

		// Record the decision.
		$reviewRound = $this->getReviewRound();
		$decision = $this->getDecision();
		import('lib.pkp.classes.submission.action.EditorAction');
		$editorAction = new EditorAction();
		$editorAction->recordDecision($request, $submission, $decision, $actionLabels, $reviewRound);

		// Bring in the SUBMISSION_FILE_* constants.
		import('lib.pkp.classes.submission.SubmissionFile');

		// Identify email key and status of round.
		switch ($decision) {
			case SUBMISSION_EDITOR_DECISION_ACCEPT:
				$emailKey = 'EDITOR_DECISION_ACCEPT';
				$status = REVIEW_ROUND_STATUS_ACCEPTED;

				$this->_updateReviewRoundStatus($submission, $status, $reviewRound);

				// Move to the editing stage.
				$editorAction->incrementWorkflowStage($submission, WORKFLOW_STAGE_ID_EDITING, $request);


				$selectedFiles = $this->getData('selectedFiles');
				if(is_array($selectedFiles)) {
					foreach ($selectedFiles as $submissionFileId) {
						$submissionFile = Services::get('submissionFile')->get($submissionFileId);
						$newSubmissionFile = clone $submissionFile;
						$newSubmissionFile->setData('fileStage', SUBMISSION_FILE_FINAL);
						$newSubmissionFile->setData('sourceSubmissionFileId', $submissionFile->getId());
						$newSubmissionFile->setData('assocType', null);
						$newSubmissionFile->setData('assocId', null);
						$newSubmissionFile = Services::get('submissionFile')->add($newSubmissionFile, Application::get()->getRequest());
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

				$selectedFiles = $this->getData('selectedFiles');
				if(is_array($selectedFiles)) {
					foreach ($selectedFiles as $submissionFileId) {
						$submissionFile = Services::get('submissionFile')->get($submissionFileId);
						$newSubmissionFile = clone $submissionFile;
						$newSubmissionFile->setData('fileStage', SUBMISSION_FILE_PRODUCTION_READY);
						$newSubmissionFile->setData('sourceSubmissionFileId', $submissionFile->getId());
						$newSubmissionFile->setData('assocType', null);
						$newSubmissionFile->setData('assocId', null);
						$newSubmissionFile = Services::get('submissionFile')->add($newSubmissionFile, Application::get()->getRequest());
					}
				}
				// Send email to the author.
				$this->_sendReviewMailToAuthor($submission, $emailKey, $request);
				break;
			default:
				throw new Exception('Unsupported decision!');
		}

		if ($this->getData('requestPayment')) {
			$context = $request->getContext();
			$stageDecisions = (new EditorDecisionActionsManager())->getStageDecisions($context, $submission, $this->getStageId());
			$decisionData = $stageDecisions[$decision];
			if (isset($decisionData['paymentType'])) {
				$paymentType = $decisionData['paymentType'];

				// Queue a payment.
				$paymentManager = Application::getPaymentManager($context);
				$queuedPayment = $paymentManager->createQueuedPayment($request, $paymentType, $request->getUser()->getId(), $submission->getId(), $decisionData['paymentAmount'], $decisionData['paymentCurrency']);
				$paymentManager->queuePayment($queuedPayment);

				// Notify any authors that this needs payment.
				$notificationMgr = new NotificationManager();
				$stageAssignmentDao = DAORegistry::getDAO('StageAssignmentDAO'); /** @var StageAssignmentDAO $stageAssignmentDao */
				$stageAssignments = $stageAssignmentDao->getBySubmissionAndRoleId($submission->getId(), ROLE_ID_AUTHOR, null);
				$userIds = [];
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
		return [
			SUBMISSION_EDITOR_DECISION_EXTERNAL_REVIEW,
			SUBMISSION_EDITOR_DECISION_ACCEPT,
			SUBMISSION_EDITOR_DECISION_SEND_TO_PRODUCTION
		];
	}
}



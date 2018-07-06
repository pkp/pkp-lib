<?php

/**
 * @file controllers/modals/editorDecision/form/SendReviewsForm.inc.php
 *
 * Copyright (c) 2014-2018 Simon Fraser University
 * Copyright (c) 2003-2018 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class SendReviewsForm
 * @ingroup controllers_modals_editorDecision_form
 *
 * @brief Form to request additional work from the author (Request revisions or
 *  resubmit for review), or to decline the submission.
 */

import('lib.pkp.controllers.modals.editorDecision.form.EditorDecisionWithEmailForm');

// Access decision actions constants.
import('classes.workflow.EditorDecisionActionsManager');

class SendReviewsForm extends EditorDecisionWithEmailForm {
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

		$this->setSaveFormOperation('saveSendReviews');

		parent::__construct(
			$submission, $decision, $stageId,
			'controllers/modals/editorDecision/form/sendReviewsForm.tpl', $reviewRound
		);
	}


	//
	// Implement protected template methods from Form
	//
	/**
	 * @copydoc EditorDecisionWithEmailForm::initData()
	 */
	function initData($actionLabels = array()) {
		$actionLabels = EditorDecisionActionsManager::getActionLabels($request->getContext(), $this->_getDecisions());

		return parent::initData($actionLabels);
	}

	/**
	 * @copydoc Form::readInputData()
	 */
	function readInputData() {
		$this->readUserVars(array('decision'));
		parent::readInputData();
	}

	/**
	 * @copydoc Form::fetch()
	 */
	function fetch($request) {
		$templateMgr = TemplateManager::getManager($request);
		$router = $request->getRouter();
		$dispatcher = $router->getDispatcher();
		$submission = $this->getSubmission();
		$user = $request->getUser();

		import('lib.pkp.classes.mail.SubmissionMailTemplate');
		$revisionsEmail = new SubmissionMailTemplate($submission, 'EDITOR_DECISION_REVISIONS');
		$resubmitEmail = new SubmissionMailTemplate($submission, 'EDITOR_DECISION_RESUBMIT');

		foreach (array($revisionsEmail, $resubmitEmail) as &$email) {
			$email->assignParams(array(
				'authorName' => $submission->getAuthorString(),
				'editorialContactSignature' => $user->getContactSignature(),
				'submissionUrl' => $dispatcher->url($request, ROUTE_PAGE, null, 'authorDashboard', 'submission', $submission->getId()),
			));
			$email->replaceParams();
		}

		$templateMgr->assign(array(
			'revisionsEmail' => $revisionsEmail->getBody(),
			'resubmitEmail' => $resubmitEmail->getBody(),
		));

		return parent::fetch($request);
	}

	/**
	 * @copydoc Form::execute()
	 */
	function execute($args, $request) {
		// Retrieve the submission.
		$submission = $this->getSubmission();

		// Get this form decision actions labels.
		$actionLabels = EditorDecisionActionsManager::getActionLabels($request->getContext(), $this->_getDecisions());

		// Record the decision.
		$reviewRound = $this->getReviewRound();
		$decision = $this->getDecision();
		$stageId = $this->getStageId();
		import('lib.pkp.classes.submission.action.EditorAction');
		$editorAction = new EditorAction();
		$editorAction->recordDecision($request, $submission, $decision, $actionLabels, $reviewRound, $stageId);

		// Identify email key and status of round.
		switch ($decision) {
			case SUBMISSION_EDITOR_DECISION_PENDING_REVISIONS:
				$emailKey = 'EDITOR_DECISION_REVISIONS';
				$status = REVIEW_ROUND_STATUS_REVISIONS_REQUESTED;
				break;

			case SUBMISSION_EDITOR_DECISION_RESUBMIT:
				$emailKey = 'EDITOR_DECISION_RESUBMIT';
				$status = REVIEW_ROUND_STATUS_RESUBMIT_FOR_REVIEW;
				break;

			case SUBMISSION_EDITOR_DECISION_DECLINE:
				$emailKey = 'EDITOR_DECISION_DECLINE';
				$status = REVIEW_ROUND_STATUS_DECLINED;
				break;

			case SUBMISSION_EDITOR_DECISION_INITIAL_DECLINE:
				$emailKey = 'EDITOR_DECISION_INITIAL_DECLINE';
				$status = REVIEW_ROUND_STATUS_DECLINED;
				break;

			default:
				fatalError('Unsupported decision!');
		}

		$this->_updateReviewRoundStatus($submission, $status, $reviewRound);

		// Send email to the author.
		$this->_sendReviewMailToAuthor($submission, $emailKey, $request);
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
			SUBMISSION_EDITOR_DECISION_PENDING_REVISIONS,
			SUBMISSION_EDITOR_DECISION_RESUBMIT,
			SUBMISSION_EDITOR_DECISION_DECLINE,
			SUBMISSION_EDITOR_DECISION_INITIAL_DECLINE
		);
	}
}

?>

<?php

/**
 * @file controllers/modals/editorDecision/form/RevertDeclineForm.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class RevertDeclineForm
 * @ingroup controllers_modals_revertDecline_form
 *
 * @brief Form to revert declined submissions
 */

import('lib.pkp.classes.controllers.modals.editorDecision.form.EditorDecisionForm');

// Access decision actions constants.
import('classes.workflow.EditorDecisionActionsManager');

class RevertDeclineForm extends EditorDecisionForm {

	/**
	 * Constructor.
	 * @param $submission Submission
	 * @param $decision int
	 * @param $stageId int
	 * @param $reviewRound ReviewRound
	 */
	function __construct($submission, $decision, $stageId) {
		parent::__construct($submission, $decision, $stageId, 'controllers/modals/editorDecision/form/revertDeclineForm.tpl');
	}

	//
	// Implement protected template methods from Form
	//

	/**
	 * @see Form::initData()
	 * @param $actionLabels array
	 */
	function initData($actionLabels = array()) {
		$this->setData('decision', $this->getDecision());
		// If we are in review stage we need a review round.
		$reviewRound = $this->getReviewRound();
		if (is_a($reviewRound, 'ReviewRound')) {
			$this->setData('reviewRoundId', $reviewRound->getId());
		}
		return parent::initData();
	}

	/**
	 * @copydoc Form::execute()
	 */
	function execute(...$formParams) {
		parent::execute(...$formParams);

		$request = Application::get()->getRequest();

		// Retrieve the submission.
		$submission = $this->getSubmission();

		// Record the decision.
		import('classes.workflow.EditorDecisionActionsManager');
		$actionLabels = (new EditorDecisionActionsManager())->getActionLabels($request->getContext(), $submission, $this->getStageId(), array($this->getDecision()));
		import('lib.pkp.classes.submission.action.EditorAction');
		$editorAction = new EditorAction();
		$editorAction->recordDecision($request, $submission, $this->getDecision(), $actionLabels);

		$submissionDao = DAORegistry::getDAO('SubmissionDAO'); /* @var $submissionDao SubmissionDAO */
		$submission->setStatus(STATUS_QUEUED); // Always return submission to STATUS_QUEUED

		// If we are on a review round, return the round status
		// prior to the decline decision
		$reviewRoundDao = DAORegistry::getDAO('ReviewRoundDAO'); /* @var $reviewRoundDao ReviewRoundDAO */
		$reviewRound = $reviewRoundDao->getLastReviewRoundBySubmissionId($submission->getId(), $this->getStageId());
		if (is_a($reviewRound, 'ReviewRound')) {
			$reviewRound->setStatus(null);
			$reviewRoundDao = DAORegistry::getDAO('ReviewRoundDAO'); /* @var $reviewRoundDao ReviewRoundDAO */
			$reviewRoundDao->updateStatus($reviewRound);
		}

		$submissionDao->updateObject($submission);

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
			SUBMISSION_EDITOR_DECISION_REVERT_DECLINE
		);
	}
}

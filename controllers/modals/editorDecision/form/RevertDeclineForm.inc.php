<?php

/**
 * @file controllers/modals/editorDecision/form/RevertDeclineForm.inc.php
 *
 * Copyright (c) 2014-2020 Simon Fraser University
 * Copyright (c) 2003-2020 John Willinsky
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
		$this->setData('decision', $this->_decision);
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
		$actionLabels = (new EditorDecisionActionsManager())->getActionLabels($request->getContext(), $submission, $this->getStageId(), array($this->_decision));
		import('lib.pkp.classes.submission.action.EditorAction');
		$editorAction = new EditorAction();
		$editorAction->recordDecision($request, $submission, $this->_decision, $actionLabels);

		$submissionDao = DAORegistry::getDAO('SubmissionDAO'); /* @var $submissionDao SubmissionDAO */
		$submission->setStatus(STATUS_QUEUED); // Always return submission to STATUS_QUEUED
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

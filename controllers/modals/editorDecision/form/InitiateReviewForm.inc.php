<?php

/**
 * @file controllers/modals/editorDecision/form/InitiateReviewForm.inc.php
 *
 * Copyright (c) 2014-2019 Simon Fraser University
 * Copyright (c) 2003-2019 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class InitiateReviewForm
 * @ingroup controllers_modal_editorDecision_form
 *
 * @brief Form for creating the first review round for a submission
 */

import('lib.pkp.classes.controllers.modals.editorDecision.form.EditorDecisionForm');

class InitiateReviewForm extends EditorDecisionForm {

	/**
	 * Constructor.
	 * @param $submission Submission
	 */
	function __construct($submission, $decision, $stageId, $template) {
		parent::__construct($submission, $decision, $stageId, $template);
	}

	/**
	 * Get the stage ID constant for the submission to be moved to.
	 * @return int WORKFLOW_STAGE_ID_...
	 */
	function _getStageId() {
		assert(false); // Subclasses should override.
	}

	//
	// Implement protected template methods from Form
	//
	/**
	 * Execute the form.
	 */
	function execute(...$functionParams) {
		parent::execute(...$functionParams);

		$request = Application::get()->getRequest();

		// Retrieve the submission.
		$submission = $this->getSubmission();

		// Record the decision.
		import('classes.workflow.EditorDecisionActionsManager');
		$actionLabels = (new EditorDecisionActionsManager())->getActionLabels($request->getContext(), $this->getStageId(), array($this->_decision));
		import('lib.pkp.classes.submission.action.EditorAction');
		$editorAction = new EditorAction();
		$editorAction->recordDecision($request, $submission, $this->_decision, $actionLabels);

		// Move to the internal review stage.
		$editorAction->incrementWorkflowStage($submission, $this->_getStageId(), $request);

		// Create an initial internal review round.
		$this->_initiateReviewRound($submission, $this->_getStageId(), $request, REVIEW_ROUND_STATUS_PENDING_REVIEWERS);
	}
}



<?php

/**
 * @file controllers/modals/editorDecision/form/NewReviewRoundForm.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class NewReviewRoundForm
 * @ingroup controllers_modal_editorDecision_form
 *
 * @brief Form for creating a new review round (after the first)
 */

use APP\workflow\EditorDecisionActionsManager;
use PKP\controllers\modals\editorDecision\form\EditorDecisionForm;
use PKP\submission\action\EditorAction;

use PKP\submission\reviewRound\ReviewRound;

class NewReviewRoundForm extends EditorDecisionForm
{
    /**
     * Constructor.
     *
     * @param $submission Submission
     * @param $decision int
     * @param stageid int
     * @param null|mixed $stageId
     */
    public function __construct($submission, $decision = EditorDecisionActionsManager::SUBMISSION_EDITOR_DECISION_NEW_ROUND, $stageId = null, $reviewRound)
    {
        parent::__construct($submission, $decision, $stageId, 'controllers/modals/editorDecision/form/newReviewRoundForm.tpl', $reviewRound);
        // WARNING: this constructor may be invoked dynamically by
        // EditorDecisionHandler::_instantiateEditorDecision.
    }


    //
    // Implement protected template methods from Form
    //
    /**
     * @copydoc Form::execute()
     *
     * @return integer The new review round number
     */
    public function execute(...$functionArgs)
    {
        $request = Application::get()->getRequest();

        // Retrieve the submission.
        $submission = $this->getSubmission();

        // Get this form decision actions labels.
        $actionLabels = (new EditorDecisionActionsManager())->getActionLabels($request->getContext(), $submission, $this->getStageId(), $this->_getDecisions());

        // Record the decision.
        $reviewRound = $this->getReviewRound();
        $editorAction = new EditorAction();
        $editorAction->recordDecision($request, $submission, EditorDecisionActionsManager::SUBMISSION_EDITOR_DECISION_NEW_ROUND, $actionLabels, $reviewRound);

        // Update the review round status.
        $reviewRoundDao = DAORegistry::getDAO('ReviewRoundDAO'); /** @var ReviewRoundDAO $reviewRoundDao */
        $reviewRoundDao->updateStatus($reviewRound, ReviewRound::REVIEW_ROUND_STATUS_PENDING_REVIEWERS);

        // Create a new review round.
        $newRound = $this->_initiateReviewRound(
            $submission,
            $submission->getStageId(),
            $request,
            ReviewRound::REVIEW_ROUND_STATUS_PENDING_REVIEWERS
        );

        parent::execute(...$functionArgs);

        return $newRound;
    }

    //
    // Private functions
    //
    /**
     * Get this form decisions.
     *
     * @return array
     */
    public function _getDecisions()
    {
        return [
            EditorDecisionActionsManager::SUBMISSION_EDITOR_DECISION_NEW_ROUND
        ];
    }
}

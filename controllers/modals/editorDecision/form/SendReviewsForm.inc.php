<?php

/**
 * @file controllers/modals/editorDecision/form/SendReviewsForm.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class SendReviewsForm
 * @ingroup controllers_modals_editorDecision_form
 *
 * @brief Form to request additional work from the author (Request revisions or
 *  resubmit for review), or to decline the submission.
 */

use APP\template\TemplateManager;
use APP\workflow\EditorDecisionActionsManager;
use PKP\mail\SubmissionMailTemplate;

use PKP\submission\action\EditorAction;
use PKP\submission\reviewRound\ReviewRound;

// FIXME: Add namespacing
import('lib.pkp.controllers.modals.editorDecision.form.EditorDecisionWithEmailForm');

class SendReviewsForm extends EditorDecisionWithEmailForm
{
    /**
     * Constructor.
     *
     * @param $submission Submission
     * @param $decision int
     * @param $stageId int
     * @param $reviewRound ReviewRound
     */
    public function __construct($submission, $decision, $stageId, $reviewRound = null)
    {
        if (!in_array($decision, $this->_getDecisions())) {
            fatalError('Invalid decision!');
        }

        $this->setSaveFormOperation('saveSendReviews');

        parent::__construct(
            $submission,
            $decision,
            $stageId,
            'controllers/modals/editorDecision/form/sendReviewsForm.tpl',
            $reviewRound
        );
    }


    //
    // Implement protected template methods from Form
    //
    /**
     * @copydoc EditorDecisionWithEmailForm::initData()
     */
    public function initData($actionLabels = [])
    {
        $request = Application::get()->getRequest();
        $actionLabels = (new EditorDecisionActionsManager())->getActionLabels($request->getContext(), $this->getSubmission(), $this->getStageId(), $this->_getDecisions());

        return parent::initData($actionLabels);
    }

    /**
     * @copydoc Form::readInputData()
     */
    public function readInputData()
    {
        $this->readUserVars(['decision']);
        parent::readInputData();
    }

    /**
     * @copydoc EditorDecisionWithEmailForm::fetch()
     *
     * @param null|mixed $template
     */
    public function fetch($request, $template = null, $display = false)
    {
        $templateMgr = TemplateManager::getManager($request);
        $router = $request->getRouter();
        $dispatcher = $router->getDispatcher();
        $submission = $this->getSubmission();
        $user = $request->getUser();

        $revisionsEmail = new SubmissionMailTemplate($submission, 'EDITOR_DECISION_REVISIONS');
        $resubmitEmail = new SubmissionMailTemplate($submission, 'EDITOR_DECISION_RESUBMIT');

        foreach ([$revisionsEmail, $resubmitEmail] as &$email) {
            $email->assignParams([
                'authorName' => $submission->getAuthorString(),
                'submissionUrl' => $dispatcher->url($request, PKPApplication::ROUTE_PAGE, null, 'authorDashboard', 'submission', $submission->getId()),
            ]);
            $email->replaceParams();
        }

        $templateMgr->assign([
            'revisionsEmail' => $revisionsEmail->getBody(),
            'resubmitEmail' => $resubmitEmail->getBody(),
        ]);

        return parent::fetch($request, $template, $display);
    }

    /**
     * @copydoc Form::execute()
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
        $decision = $this->getDecision();
        $stageId = $this->getStageId();
        $editorAction = new EditorAction();
        $editorAction->recordDecision($request, $submission, $decision, $actionLabels, $reviewRound, $stageId);

        parent::execute(...$functionArgs);

        // Identify email key and status of round.
        switch ($decision) {
            case EditorDecisionActionsManager::SUBMISSION_EDITOR_DECISION_PENDING_REVISIONS:
                $emailKey = 'EDITOR_DECISION_REVISIONS';
                $status = ReviewRound::REVIEW_ROUND_STATUS_REVISIONS_REQUESTED;
                break;

            case EditorDecisionActionsManager::SUBMISSION_EDITOR_DECISION_RESUBMIT:
                $emailKey = 'EDITOR_DECISION_RESUBMIT';
                $status = ReviewRound::REVIEW_ROUND_STATUS_RESUBMIT_FOR_REVIEW;
                break;

            case EditorDecisionActionsManager::SUBMISSION_EDITOR_DECISION_DECLINE:
                $emailKey = 'EDITOR_DECISION_DECLINE';
                $status = ReviewRound::REVIEW_ROUND_STATUS_DECLINED;
                break;

            case EditorDecisionActionsManager::SUBMISSION_EDITOR_DECISION_INITIAL_DECLINE:
                $emailKey = 'EDITOR_DECISION_INITIAL_DECLINE';
                $status = ReviewRound::REVIEW_ROUND_STATUS_DECLINED;
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
     *
     * @return array
     */
    public function _getDecisions()
    {
        return [
            EditorDecisionActionsManager::SUBMISSION_EDITOR_DECISION_PENDING_REVISIONS,
            EditorDecisionActionsManager::SUBMISSION_EDITOR_DECISION_RESUBMIT,
            EditorDecisionActionsManager::SUBMISSION_EDITOR_DECISION_DECLINE,
            EditorDecisionActionsManager::SUBMISSION_EDITOR_DECISION_INITIAL_DECLINE
        ];
    }
}

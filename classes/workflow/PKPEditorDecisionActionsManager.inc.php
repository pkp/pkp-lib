<?php

/**
 * @file classes/workflow/PKPEditorDecisionActionsManager.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PKPEditorDecisionActionsManager
 * @ingroup classes_workflow
 *
 * @brief Wrapper class for create and assign editor decisions actions to template manager.
 */

namespace PKP\workflow;

use APP\workflow\EditorDecisionActionsManager;
use PKP\notification\PKPNotification;
use PKP\plugins\HookRegistry;

use PKP\submission\PKPSubmission;

abstract class PKPEditorDecisionActionsManager
{
    public const SUBMISSION_EDITOR_DECISION_INITIAL_DECLINE = 9;
    public const SUBMISSION_EDITOR_RECOMMEND_ACCEPT = 11;
    public const SUBMISSION_EDITOR_RECOMMEND_PENDING_REVISIONS = 12;
    public const SUBMISSION_EDITOR_RECOMMEND_RESUBMIT = 13;
    public const SUBMISSION_EDITOR_RECOMMEND_DECLINE = 14;
    public const SUBMISSION_EDITOR_DECISION_REVERT_DECLINE = 17;

    /**
     * Get the available decisions by stage ID and user making decision permissions,
     * if the user can make decisions or if it is recommendOnly user.
     *
     * @param $context Context
     * @param $submission Submission
     * @param $stageId int WORKFLOW_STAGE_ID_...
     * @param $makeDecision boolean If the user can make decisions
     */
    public function getStageDecisions($context, $submission, $stageId, $makeDecision = true)
    {
        $result = null;
        switch ($stageId) {
            case WORKFLOW_STAGE_ID_SUBMISSION:
                $result = $this->_submissionStageDecisions($submission, $stageId, $makeDecision);
                break;
            case WORKFLOW_STAGE_ID_EXTERNAL_REVIEW:
                $result = $this->_externalReviewStageDecisions($context, $submission, $makeDecision);
                break;
            case WORKFLOW_STAGE_ID_EDITING:
                $result = $this->_editorialStageDecisions($makeDecision);
                break;
            default:
                assert(false);
        }
        HookRegistry::call(
            'EditorAction::modifyDecisionOptions',
            [$context, $submission, $stageId, &$makeDecision, &$result]
        );
        return $result;
    }

    /**
     * Get an associative array matching editor recommendation codes with locale strings.
     * (Includes default '' => "Choose One" string.)
     *
     * @param $stageId integer
     *
     * @return array recommendation => localeString
     */
    public function getRecommendationOptions($stageId)
    {
        return [
            '' => 'common.chooseOne',
            self::SUBMISSION_EDITOR_RECOMMEND_PENDING_REVISIONS => 'editor.submission.decision.requestRevisions',
            self::SUBMISSION_EDITOR_RECOMMEND_RESUBMIT => 'editor.submission.decision.resubmit',
            self::SUBMISSION_EDITOR_RECOMMEND_ACCEPT => 'editor.submission.decision.accept',
            self::SUBMISSION_EDITOR_RECOMMEND_DECLINE => 'editor.submission.decision.decline',
        ];
    }

    /**
     * Define and return editor decisions for the submission stage.
     * If the user cannot make decisions i.e. if it is a recommendOnly user,
     * the user can only send the submission to the review stage, and neither
     * acept nor decline the submission.
     *
     * @param $submission Submission
     * @param $stageId int WORKFLOW_STAGE_ID_...
     * @param $makeDecision boolean If the user can make decisions
     *
     * @return array
     */
    protected function _submissionStageDecisions($submission, $stageId, $makeDecision = true)
    {
        $decisions = [
            EditorDecisionActionsManager::SUBMISSION_EDITOR_DECISION_EXTERNAL_REVIEW => [
                'operation' => 'externalReview',
                'name' => 'externalReview',
                'title' => 'editor.submission.decision.sendExternalReview',
                'toStage' => 'editor.review',
            ]
        ];
        if ($makeDecision) {
            if ($stageId == WORKFLOW_STAGE_ID_SUBMISSION) {
                $decisions = $decisions + [
                    EditorDecisionActionsManager::SUBMISSION_EDITOR_DECISION_ACCEPT => [
                        'name' => 'accept',
                        'operation' => 'promote',
                        'title' => 'editor.submission.decision.skipReview',
                        'toStage' => 'submission.copyediting',
                    ],
                ];
            }

            if ($submission->getStatus() == PKPSubmission::STATUS_QUEUED) {
                $decisions = $decisions + [
                    self::SUBMISSION_EDITOR_DECISION_INITIAL_DECLINE => [
                        'name' => 'decline',
                        'operation' => 'sendReviews',
                        'title' => 'editor.submission.decision.decline',
                    ],
                ];
            }
            if ($submission->getStatus() == PKPSubmission::STATUS_DECLINED) {
                $decisions = $decisions + [
                    self::SUBMISSION_EDITOR_DECISION_REVERT_DECLINE => [
                        'name' => 'revert',
                        'operation' => 'revertDecline',
                        'title' => 'editor.submission.decision.revertDecline',
                    ],
                ];
            }
        }
        return $decisions;
    }

    /**
     * Define and return editor decisions for the editorial stage.
     * Currently it does not matter if the user cannot make decisions
     * i.e. if it is a recommendOnly user for this stage.
     *
     * @param $makeDecision boolean If the user cannot make decisions
     *
     * @return array
     */
    protected function _editorialStageDecisions($makeDecision = true)
    {
        return [
            EditorDecisionActionsManager::SUBMISSION_EDITOR_DECISION_SEND_TO_PRODUCTION => [
                'operation' => 'promote',
                'name' => 'sendToProduction',
                'title' => 'editor.submission.decision.sendToProduction',
                'toStage' => 'submission.production',
            ],
        ];
    }

    /**
     * Get the stage-level notification type constants.
     *
     * @return array
     */
    public function getStageNotifications()
    {
        return [
            PKPNotification::NOTIFICATION_TYPE_EDITOR_ASSIGNMENT_SUBMISSION,
            PKPNotification::NOTIFICATION_TYPE_EDITOR_ASSIGNMENT_EXTERNAL_REVIEW,
            PKPNotification::NOTIFICATION_TYPE_EDITOR_ASSIGNMENT_EDITING,
            PKPNotification::NOTIFICATION_TYPE_EDITOR_ASSIGNMENT_PRODUCTION
        ];
    }
}

if (!PKP_STRICT_MODE) {
    class_alias('\PKP\workflow\PKPEditorDecisionActionsManager', '\PKPEditorDecisionActionsManager');
    foreach ([
        'SUBMISSION_EDITOR_DECISION_INITIAL_DECLINE',
        'SUBMISSION_EDITOR_RECOMMEND_ACCEPT',
        'SUBMISSION_EDITOR_RECOMMEND_PENDING_REVISIONS',
        'SUBMISSION_EDITOR_RECOMMEND_RESUBMIT',
        'SUBMISSION_EDITOR_RECOMMEND_DECLINE',
        'SUBMISSION_EDITOR_DECISION_REVERT_DECLINE',
    ] as $constantName) {
        if (!defined($constantName)) {
            define($constantName, constant('\PKPEditorDecisionActionsManager::' . $constantName));
        }
    }
}

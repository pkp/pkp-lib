<?php

/**
 * @file classes/workflow/EditorDecisionActionsManager.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class EditorDecisionActionsManager
 * @ingroup classes_workflow
 *
 * @brief Wrapper class for create and assign editor decisions actions to template manager.
 */

namespace APP\workflow;

use PKP\submission\PKPSubmission;
use PKP\workflow\PKPEditorDecisionActionsManager;

class EditorDecisionActionsManager extends PKPEditorDecisionActionsManager
{
    public const SUBMISSION_EDITOR_DECISION_EXTERNAL_REVIEW = 8;
    public const SUBMISSION_EDITOR_DECISION_ACCEPT = 1;
    public const SUBMISSION_EDITOR_DECISION_PENDING_REVISIONS = 2;
    public const SUBMISSION_EDITOR_DECISION_RESUBMIT = 3;
    public const SUBMISSION_EDITOR_DECISION_DECLINE = 4;
    public const SUBMISSION_EDITOR_DECISION_NEW_ROUND = 16;
    public const SUBMISSION_EDITOR_DECISION_SEND_TO_PRODUCTION = 7;

    /**
     * Get decision actions labels.
     *
     * @param PKPRequest $request
     * @param int $stageId
     * @param array $decisions
     *
     * @return array
     */
    public function getActionLabels($request, $submission, $stageId, $decisions)
    {
        $allDecisionsData = $this->_productionStageDecisions($submission);
        $actionLabels = [];

        foreach ($decisions as $decision) {
            if (isset($allDecisionsData[$decision]['title'])) {
                $actionLabels[$decision] = $allDecisionsData[$decision]['title'];
            }
        }

        return $actionLabels;
    }

    /**
     * @copydoc PKPEditorDecisionActionsManager::getStageDecisions()
     */
    public function getStageDecisions($request, $submission, $stageId, $makeDecision = true)
    {
        switch ($stageId) {
            case WORKFLOW_STAGE_ID_PRODUCTION:
                return $this->_productionStageDecisions($submission, $makeDecision);
        }
        return parent::getStageDecisions($request, $submission, $stageId, $makeDecision);
    }

    //
    // Private helper methods.
    //
    /**
     * Define and return editor decisions for the production stage.
     * If the user cannot make decisions i.e. if it is a recommendOnly user,
     * there will be no decisions options in the production stage.
     *
     * @param Submission $submission
     * @param bool $makeDecision If the user can make decisions
     *
     * @return array
     */
    protected function _productionStageDecisions($submission, $makeDecision = true)
    {
        $decisions = [];
        if ($makeDecision) {
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
}

if (!PKP_STRICT_MODE) {
    class_alias('\APP\workflow\EditorDecisionActionsManager', '\EditorDecisionActionsManager');
    foreach ([
        'SUBMISSION_EDITOR_DECISION_EXTERNAL_REVIEW',
        'SUBMISSION_EDITOR_DECISION_ACCEPT',
        'SUBMISSION_EDITOR_DECISION_PENDING_REVISIONS',
        'SUBMISSION_EDITOR_DECISION_RESUBMIT',
        'SUBMISSION_EDITOR_DECISION_DECLINE',
        'SUBMISSION_EDITOR_DECISION_NEW_ROUND',
        'SUBMISSION_EDITOR_DECISION_SEND_TO_PRODUCTION',
    ] as $constantName) {
        define($constantName, constant('\EditorDecisionActionsManager::' . $constantName));
    }
}

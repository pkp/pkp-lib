<?php

/**
 * @file classes/workflow/WorkflowStageDAO.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class WorkflowStageDAO
 * @ingroup workflow
 *
 * @brief class for operations involving the workflow stages.
 *
 */

namespace PKP\workflow;

use APP\core\Application;

class WorkflowStageDAO extends \PKP\db\DAO
{
    public const WORKFLOW_STAGE_PATH_SUBMISSION = 'submission';
    public const WORKFLOW_STAGE_PATH_INTERNAL_REVIEW = 'internalReview';
    public const WORKFLOW_STAGE_PATH_EXTERNAL_REVIEW = 'externalReview';
    public const WORKFLOW_STAGE_PATH_EDITING = 'editorial';
    public const WORKFLOW_STAGE_PATH_PRODUCTION = 'production';

    /**
     * Convert a stage id into a stage path
     *
     * @param int $stageId
     *
     * @return string|null
     */
    public static function getPathFromId($stageId)
    {
        static $stageMapping = [
            WORKFLOW_STAGE_ID_SUBMISSION => self::WORKFLOW_STAGE_PATH_SUBMISSION,
            WORKFLOW_STAGE_ID_INTERNAL_REVIEW => self::WORKFLOW_STAGE_PATH_INTERNAL_REVIEW,
            WORKFLOW_STAGE_ID_EXTERNAL_REVIEW => self::WORKFLOW_STAGE_PATH_EXTERNAL_REVIEW,
            WORKFLOW_STAGE_ID_EDITING => self::WORKFLOW_STAGE_PATH_EDITING,
            WORKFLOW_STAGE_ID_PRODUCTION => self::WORKFLOW_STAGE_PATH_PRODUCTION
        ];
        return $stageMapping[$stageId] ?? null;
    }

    /**
     * Convert a stage path into a stage id
     *
     * @param string $stagePath
     *
     * @return int|null
     */
    public static function getIdFromPath($stagePath)
    {
        static $stageMapping = [
            self::WORKFLOW_STAGE_PATH_SUBMISSION => WORKFLOW_STAGE_ID_SUBMISSION,
            self::WORKFLOW_STAGE_PATH_INTERNAL_REVIEW => WORKFLOW_STAGE_ID_INTERNAL_REVIEW,
            self::WORKFLOW_STAGE_PATH_EXTERNAL_REVIEW => WORKFLOW_STAGE_ID_EXTERNAL_REVIEW,
            self::WORKFLOW_STAGE_PATH_EDITING => WORKFLOW_STAGE_ID_EDITING,
            self::WORKFLOW_STAGE_PATH_PRODUCTION => WORKFLOW_STAGE_ID_PRODUCTION
        ];
        return $stageMapping[$stagePath] ?? null;
    }

    /**
     * Convert a stage id into a stage translation key
     *
     * @param int $stageId
     *
     * @return string|null
     */
    public static function getTranslationKeyFromId($stageId)
    {
        $stageMapping = self::getWorkflowStageTranslationKeys();

        assert(isset($stageMapping[$stageId]));
        return $stageMapping[$stageId];
    }

    /**
     * Return a mapping of workflow stages and its translation keys.
     *
     * @return array
     */
    public static function getWorkflowStageTranslationKeys()
    {
        static $stageMapping = [
            WORKFLOW_STAGE_ID_SUBMISSION => 'submission.submission',
            WORKFLOW_STAGE_ID_INTERNAL_REVIEW => 'workflow.review.internalReview',
            WORKFLOW_STAGE_ID_EXTERNAL_REVIEW => 'workflow.review.externalReview',
            WORKFLOW_STAGE_ID_EDITING => 'submission.editorial',
            WORKFLOW_STAGE_ID_PRODUCTION => 'submission.production'
        ];
        $applicationStages = Application::get()->getApplicationStages();
        return array_intersect_key($stageMapping, array_flip($applicationStages));
    }

    /**
     * Return a mapping of workflow stages, its translation keys and
     * paths.
     *
     * @return array
     */
    public static function getWorkflowStageKeysAndPaths()
    {
        $workflowStages = self::getWorkflowStageTranslationKeys();
        $stageMapping = [];
        foreach ($workflowStages as $stageId => $translationKey) {
            $stageMapping[$stageId] = [
                'id' => $stageId,
                'translationKey' => $translationKey,
                'path' => self::getPathFromId($stageId)
            ];
        }

        return $stageMapping;
    }

    /**
     * Returns an array containing data for rendering the stage workflow tabs
     * for a submission.
     *
     * @param Submission $submission
     * @param array $stagesWithDecisions
     * @param array $stageNotifications
     *
     * @return array
     */
    public static function getStageStatusesBySubmission($submission, $stagesWithDecisions, $stageNotifications)
    {
        $currentStageId = $submission->getStageId();
        $workflowStages = self::getWorkflowStageKeysAndPaths();

        foreach ($workflowStages as $stageId => $stageData) {
            $foundState = false;
            // If we have not found a state, and the current stage being examined is below the current submission stage, and there have been
            // decisions for this stage, but no notifications outstanding, mark it as complete.
            if (!$foundState && $stageId <= $currentStageId && (in_array($stageId, $stagesWithDecisions) || $stageId == WORKFLOW_STAGE_ID_PRODUCTION) && !$stageNotifications[$stageId]) {
                $workflowStages[$currentStageId]['statusKey'] = 'submission.complete';
            }

            // If this is an old stage with no notifications, this was a skiped/not initiated stage.
            if (!$foundState && $stageId < $currentStageId && !$stageNotifications[$stageId]) {
                $foundState = true;
                // Those are stages not initiated, that were skipped, like review stages.
            }

            // Finally, if this stage has outstanding notifications, or has no decision yet, mark it as initiated.
            if (!$foundState && $stageId <= $currentStageId && (!in_array($stageId, $stagesWithDecisions) || $stageNotifications[$stageId])) {
                $workflowStages[$currentStageId]['statusKey'] = 'submission.initiated';
                $foundState = true;
            }
        }

        return $workflowStages;
    }
}

// Expose global constants unless operating in strict mode.
if (!PKP_STRICT_MODE) {
    class_alias('\PKP\workflow\WorkflowStageDAO', '\WorkflowStageDAO');
    foreach ([
        'WORKFLOW_STAGE_PATH_SUBMISSION',
        'WORKFLOW_STAGE_PATH_INTERNAL_REVIEW',
        'WORKFLOW_STAGE_PATH_EXTERNAL_REVIEW',
        'WORKFLOW_STAGE_PATH_EDITING',
        'WORKFLOW_STAGE_PATH_PRODUCTION',
    ] as $constantName) {
        if (!defined($constantName)) {
            define($constantName, constant('\WorkflowStageDAO::' . $constantName));
        }
    }
}

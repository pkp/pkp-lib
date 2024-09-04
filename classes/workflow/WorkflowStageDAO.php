<?php

/**
 * @file classes/workflow/WorkflowStageDAO.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class WorkflowStageDAO
 *
 * @ingroup workflow
 *
 * @brief class for operations involving the workflow stages.
 *
 */

namespace PKP\workflow;

use APP\core\Application;
use APP\submission\Submission;

class WorkflowStageDAO extends \PKP\db\DAO
{
    public const WORKFLOW_STAGE_PATH_SUBMISSION = 'submission';
    public const WORKFLOW_STAGE_PATH_INTERNAL_REVIEW = 'internalReview';
    public const WORKFLOW_STAGE_PATH_EXTERNAL_REVIEW = 'externalReview';
    public const WORKFLOW_STAGE_PATH_EDITING = 'editorial';
    public const WORKFLOW_STAGE_PATH_PRODUCTION = 'production';

    /**
     * Convert a stage id into a stage path
     */
    public static function getPathFromId(int $stageId): ?string
    {
        return match($stageId) {
            WORKFLOW_STAGE_ID_SUBMISSION => self::WORKFLOW_STAGE_PATH_SUBMISSION,
            WORKFLOW_STAGE_ID_INTERNAL_REVIEW => self::WORKFLOW_STAGE_PATH_INTERNAL_REVIEW,
            WORKFLOW_STAGE_ID_EXTERNAL_REVIEW => self::WORKFLOW_STAGE_PATH_EXTERNAL_REVIEW,
            WORKFLOW_STAGE_ID_EDITING => self::WORKFLOW_STAGE_PATH_EDITING,
            WORKFLOW_STAGE_ID_PRODUCTION => self::WORKFLOW_STAGE_PATH_PRODUCTION,
            default => null
        };
    }

    /**
     * Convert a stage path into a stage id
     */
    public static function getIdFromPath(string $stagePath): ?int
    {
        return match($stagePath) {
            self::WORKFLOW_STAGE_PATH_SUBMISSION => WORKFLOW_STAGE_ID_SUBMISSION,
            self::WORKFLOW_STAGE_PATH_INTERNAL_REVIEW => WORKFLOW_STAGE_ID_INTERNAL_REVIEW,
            self::WORKFLOW_STAGE_PATH_EXTERNAL_REVIEW => WORKFLOW_STAGE_ID_EXTERNAL_REVIEW,
            self::WORKFLOW_STAGE_PATH_EDITING => WORKFLOW_STAGE_ID_EDITING,
            self::WORKFLOW_STAGE_PATH_PRODUCTION => WORKFLOW_STAGE_ID_PRODUCTION,
            default => null
        };
    }

    /**
     * Convert a stage id into a stage translation key
     */
    public static function getTranslationKeyFromId(int $stageId): ?string
    {
        $stageMapping = self::getWorkflowStageTranslationKeys(false);
        return $stageMapping[$stageId];
    }

    /**
     * Return a mapping of workflow stages and its translation keys.
     *
     * @param bool $filtered true iff only stages implemented by this application should be included.
     */
    public static function getWorkflowStageTranslationKeys(bool $filtered = true): array
    {
        static $stageMapping;
        $stageMapping ??= collect([
            WORKFLOW_STAGE_ID_SUBMISSION,
            WORKFLOW_STAGE_ID_INTERNAL_REVIEW,
            WORKFLOW_STAGE_ID_EXTERNAL_REVIEW,
            WORKFLOW_STAGE_ID_EDITING,
            WORKFLOW_STAGE_ID_PRODUCTION
        ])
            ->mapWithKeys(fn (int $stageId) => [$stageId => Application::getWorkflowStageName($stageId)])
            ->toArray();
        if ($filtered) {
            $applicationStages = Application::getApplicationStages();
            return array_intersect_key($stageMapping, array_flip($applicationStages));
        } else {
            return $stageMapping;
        }
    }

    /**
     * Return a mapping of workflow stages, its translation keys and paths.
     */
    public static function getWorkflowStageKeysAndPaths(): array
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
     */
    public static function getStageStatusesBySubmission(Submission $submission, array $stagesWithDecisions, array $stageNotifications): array
    {
        $currentStageId = $submission->getData('stageId');
        $workflowStages = self::getWorkflowStageKeysAndPaths();

        foreach ($workflowStages as $stageId => $stageData) {
            $foundState = false;
            // If we have not found a state, and the current stage being examined is below the current submission stage, and there have been
            // decisions for this stage, but no notifications outstanding, mark it as complete.
            if (!$foundState && $stageId <= $currentStageId && (in_array($stageId, $stagesWithDecisions) || $stageId == WORKFLOW_STAGE_ID_PRODUCTION) && !$stageNotifications[$stageId]) {
                $workflowStages[$currentStageId]['statusKey'] = 'submission.complete';
            }

            // If this is an old stage with no notifications, this was a skipped/not initiated stage.
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

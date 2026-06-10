<?php

/**
 * @file classes/decision/types/ReturnToWorkflow.php
 *
 * Copyright (c) 2026 Simon Fraser University
 * Copyright (c) 2026 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class ReturnToWorkflow
 *
 * @brief A decision to return a Done submission to its prior workflow stage. Valid only when the submission is currently in WORKFLOW_STAGE_ID_DONE.
 *
 */

namespace PKP\decision\types;

use APP\decision\Decision;
use APP\facades\Repo;
use APP\submission\Submission;
use Illuminate\Validation\Validator;
use PKP\context\Context;
use PKP\decision\DecisionType;
use PKP\decision\Steps;
use PKP\submission\PKPSubmission;
use PKP\submission\reviewRound\ReviewRound;
use PKP\user\User;

class ReturnToWorkflow extends DecisionType
{
    public function getDecision(): int
    {
        return Decision::RETURN_TO_WORKFLOW;
    }

    public function getStageId(): int
    {
        return WORKFLOW_STAGE_ID_DONE;
    }

    /**
     * The return target is derived from the most recent decision that entered Done,
     * (either MOVE_TO_DONE or RETURN_TO_DONE), whose recorded stage_id is the stage
     * the submission occupied before entering Done.
     */
    public function getNewStageId(Submission $submission, ?int $reviewRoundId): ?int
    {
        $lastIntoDone = Repo::decision()->getCollector()
            ->filterBySubmissionIds([$submission->getId()])
            ->filterByDecisionTypes([Decision::MOVE_TO_DONE, Decision::RETURN_TO_DONE])
            ->orderBy('date_decided', 'desc')
            ->getMany()
            ->first();

        $targetStageId = $lastIntoDone ? (int) $lastIntoDone->getData('stageId') : null;

        if (!$targetStageId || $targetStageId === WORKFLOW_STAGE_ID_DONE) {
            error_log("ReturnToWorkflow: no valid prior stage recorded for submission {$submission->getId()}; falling back to Production.");
            return WORKFLOW_STAGE_ID_PRODUCTION;
        }

        return $targetStageId;
    }

    public function getNewStatus(): ?int
    {
        return PKPSubmission::STATUS_QUEUED;
    }

    public function getNewReviewRoundStatus(): ?int
    {
        return null;
    }

    public function getLabel(?string $locale = null): string
    {
        return __('editor.submission.decision.returnToWorkflow', [], $locale);
    }

    public function getDescription(?string $locale = null): string
    {
        return __('editor.submission.decision.returnToWorkflow.description', [], $locale);
    }

    public function getLog(): string
    {
        return 'editor.submission.decision.returnToWorkflow.log';
    }

    public function getCompletedLabel(): string
    {
        return __('editor.submission.decision.returnToWorkflow.completed');
    }

    public function getCompletedMessage(Submission $submission): string
    {
        return __('editor.submission.decision.returnToWorkflow.completed.description', ['title' => $submission?->getCurrentPublication()?->getLocalizedFullTitle(null, 'html') ?? '']);
    }

    public function validate(array $props, Submission $submission, Context $context, Validator $validator, ?int $reviewRoundId = null)
    {
        parent::validate($props, $submission, $context, $validator, $reviewRoundId);
    }

    public function getSteps(Submission $submission, Context $context, User $editor, ?ReviewRound $reviewRound): ?Steps
    {
        return null;
    }
}

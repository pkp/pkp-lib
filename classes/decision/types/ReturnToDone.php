<?php
/**
 * @file classes/decision/types/ReturnToDone.php
 *
 * Copyright (c) 2026 Simon Fraser University
 * Copyright (c) 2026 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class ReturnToDone
 *
 * @brief A decision to return a submission from an active workflow stage back to the Done stage (redo after a ReturnToWorkflow).
 */

namespace PKP\decision\types;

use APP\decision\Decision;
use APP\submission\Submission;
use Illuminate\Validation\Validator;
use PKP\context\Context;
use PKP\decision\DecisionType;
use PKP\decision\Steps;
use PKP\submission\PKPSubmission;
use PKP\submission\reviewRound\ReviewRound;
use PKP\user\User;

class ReturnToDone extends DecisionType
{
    public function getDecision(): int
    {
        return Decision::RETURN_TO_DONE;
    }

    public function getStageId(): int
    {
        return WORKFLOW_STAGE_ID_PRODUCTION;
    }

    public function getNewStageId(Submission $submission, ?int $reviewRoundId): int
    {
        return WORKFLOW_STAGE_ID_DONE;
    }

    public function getNewStatus(): ?int
    {
        return PKPSubmission::STATUS_PUBLISHED;
    }

    public function getNewReviewRoundStatus(): ?int
    {
        return null;
    }

    public function getLabel(?string $locale = null): string
    {
        return __('editor.submission.decision.returnToDone', [], $locale);
    }

    public function getDescription(?string $locale = null): string
    {
        return __('editor.submission.decision.returnToDone.description', [], $locale);
    }

    public function getLog(): string
    {
        return 'editor.submission.decision.returnToDone.log';
    }

    public function getCompletedLabel(): string
    {
        return __('editor.submission.decision.returnToDone.completed');
    }

    public function getCompletedMessage(Submission $submission): string
    {
        return __('editor.submission.decision.returnToDone.completed.description', ['title' => $submission?->getCurrentPublication()?->getLocalizedFullTitle(null, 'html') ?? '']);
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

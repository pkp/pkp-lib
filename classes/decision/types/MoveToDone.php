<?php
/**
 * @file classes/decision/types/MoveToDone.php
 *
 * Copyright (c) 2026 Simon Fraser University
 * Copyright (c) 2026 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class MoveToDone
 *
 * @brief A system-recorded decision that moves a submission to the Done stage when its first Version of Record is published.
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

class MoveToDone extends DecisionType
{
    public function getDecision(): int
    {
        return Decision::MOVE_TO_DONE;
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
        return __('editor.submission.decision.moveToDone', [], $locale);
    }

    public function getDescription(?string $locale = null): string
    {
        return __('editor.submission.decision.moveToDone.description', [], $locale);
    }

    public function getLog(): string
    {
        return 'editor.submission.decision.moveToDone.log';
    }

    public function getCompletedLabel(): string
    {
        return __('editor.submission.decision.moveToDone.completed');
    }

    public function getCompletedMessage(Submission $submission): string
    {
        return __('editor.submission.decision.moveToDone.completed.description', ['title' => $submission?->getCurrentPublication()?->getLocalizedFullTitle(null, 'html') ?? '']);
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

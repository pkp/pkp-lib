<?php

/**
 * @file classes/decision/types/RemoveEmptyExternalReviewRound.inc.php
 *
 * Copyright (c) 2014-2022 Simon Fraser University
 * Copyright (c) 2000-2022 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class decision
 *
 * @brief A decision to request revisions for a submission.
 */

namespace PKP\decision\types;

use APP\core\Application;
use APP\decision\Decision;
use APP\submission\Submission;
use Illuminate\Validation\Validator;
use PKP\context\Context;
use PKP\db\DAORegistry;
use PKP\decision\DecisionType;
use PKP\decision\Steps;
use PKP\decision\types\traits\InExternalReviewRound;
use PKP\submission\reviewRound\ReviewRound;
use PKP\user\User;

class RemoveEmptyExternalReviewRound extends DecisionType
{
    use InExternalReviewRound;

    public function getDecision(): int
    {
        return Decision::DELETE_EMPTY_EXTERNAL_REVIEW_ROUND;
    }

    public function getNewStageId(): ?int
    {
        return null;
    }

    public function getNewStatus(): ?int
    {
        return null;
    }

    public function getNewReviewRoundStatus(): ?int
    {
        return ReviewRound::REVIEW_ROUND_STATUS_REVIEWS_COMPLETED;
    }

    public function getLabel(?string $locale = null): string
    {
        return __('editor.submission.decision.removeEmptyExternalReviewRound', [], $locale);
    }

    public function getDescription(?string $locale = null): string
    {
        return __('editor.submission.decision.removeEmptyExternalReviewRound.description', [], $locale);
    }

    public function getLog(): string
    {
        return 'editor.submission.decision.removeEmptyExternalReviewRound.log';
    }

    public function getCompletedLabel(): string
    {
        return __('editor.submission.decision.removeEmptyExternalReviewRound.completed');
    }

    public function getCompletedMessage(Submission $submission): string
    {
        return __('editor.submission.decision.removeEmptyExternalReviewRound.completed.description', ['title' => $submission->getLocalizedFullTitle()]);
    }

    public function validate(array $props, Submission $submission, Context $context, Validator $validator, ?int $reviewRoundId = null)
    {
        // If there is no review round id, a validation error will already have been set
        if (!$reviewRoundId) {
            return;
        }

        parent::validate($props, $submission, $context, $validator, $reviewRoundId);

        if (self::isOnlyReviewRound($submission)) {
            $validator
                ->errors()
                ->add(
                    'restriction',
                    __('editor.submission.decision.removeEmptyExternalReviewRound.restriction.single.round')
                );
        }

        if (self::hasReviewerAssigned($reviewRoundId)) {
            $validator
                ->errors()
                ->add(
                    'restriction',
                    __('editor.submission.decision.removeEmptyExternalReviewRound.restriction.reviewer.assigned')
                );
        }

        if (!isset($props['actions'])) {
            return;
        }
    }

    public function runAdditionalActions(Decision $decision, Submission $submission, User $editor, Context $context, array $actions)
    {
        parent::runAdditionalActions($decision, $submission, $editor, $context, $actions);

        $request = Application::get()->getRequest();

        $this->executeDecision($request->getUserVar('reviewRoundId'));
    }

    public function getSteps(Submission $submission, Context $context, User $editor, ?ReviewRound $reviewRound): ?Steps
    {
        $steps = new Steps($this, $submission, $context, $reviewRound);

        return $steps;
    }

    /**
     * Execute the main decision responsibility after validation passes
     *
     */
    public function executeDecision(int $reviewRoundId): bool
    {
        /** @var ReviewRoundDAO $reviewRoundDao */
        $reviewRoundDao = DAORegistry::getDAO('ReviewRoundDAO');

        return $reviewRoundDao->deleteById($reviewRoundId);
    }


    /**
     * Determine if review round can be removed/deleted
     *
     *
     */
    public static function canRemove(Submission $submission, int $reviewRoundId): bool
    {
        // If this is the only review round available to the workflow
        // then removing is not the right approach
        // but need to have the option to move to Submission instead
        if (static::isOnlyReviewRound($submission)) {
            return false;
        }

        // If the review round have any reviewer assigned to it
        // that makes it a non empty review round
        // which it turns make it non removeable at this point
        if (static::hasReviewerAssigned($reviewRoundId)) {
            return false;
        }

        return true;
    }

    /**
     * Determine if submission has only one review round associated with it
     *
     */
    public static function isOnlyReviewRound(Submission $submission): bool
    {
        if ($submission->getExternalReviewRoundCount() === 1) {
            return true;
        }

        return false;
    }

    /**
     * Determine if any reviewer assigned to the review round
     *
     */
    public static function hasReviewerAssigned(int $reviewRoundId): bool
    {
        /** @var ReviewRoundDAO $reviewRoundDao */
        $reviewRoundDao = DAORegistry::getDAO('ReviewRoundDAO');

        if ($reviewRoundDao->getAssignmentCountByid($reviewRoundId) > 0) {
            return true;
        }

        return false;
    }
}

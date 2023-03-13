<?php
/**
 * @file classes/decision/types/CancelSubmission.php
 *
 * Copyright (c) 2014-2022 Simon Fraser University
 * Copyright (c) 2000-2022 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class decision
 *
 * @brief A decision to cancel a submission.
 */

namespace PKP\decision\types;

use APP\decision\Decision;
use APP\facades\Repo;
use APP\submission\Submission;
use Illuminate\Validation\Validator;
use PKP\context\Context;
use PKP\decision\DecisionType;
use PKP\decision\Steps;
use PKP\decision\steps\Email;
use PKP\decision\types\traits\NotifyAuthors;
use PKP\mail\mailables\DecisionCancelSubmissionNotifyAuthor;
use PKP\security\Role;
use PKP\submission\PKPSubmission;
use PKP\submission\reviewRound\ReviewRound;
use PKP\user\User;

class CancelSubmission extends DecisionType
{

    use NotifyAuthors;

    public function getStageId(): int
    {
        return null;
    }

    public function getDecision(): int
    {
        return null;
    }

    public function getNewStageId(Submission $submission, ?int $reviewRoundId): ?int
    {
        return null;
    }

    public function getNewStatus(): ?int
    {
        return Submission::STATUS_CANCELED;
    }

    public function getNewReviewRoundStatus(): ?int
    {
        return null;
    }

    public function getLabel(?string $locale = null): string
    {
        return __('editor.submission.decision.cancelSubmission', [], $locale);
    }

    public function getDescription(?string $locale = null): string
    {
        return __('editor.submission.decision.cancelSubmission.description', [], $locale);
    }

    public function getLog(): string
    {
        return 'editor.submission.decision.cancelSubmission.log';
    }

    public function getCompletedLabel(): string
    {
        return __('editor.submission.decision.cancelSubmission.completed');
    }

    public function getCompletedMessage(Submission $submission): string
    {
        return __('editor.submission.decision.cancelSubmission.completed.description', ['title' => $submission->getLocalizedFullTitle()]);
    }

    public function validate(array $props, Submission $submission, Context $context, Validator $validator, ?int $reviewRoundId = null)
    {
        // Canceling a submission not possible if the submission has been published before
        if (Repo::submission()->wasPublishedBefore($submission)) {
            return;
        }

        parent::validate($props, $submission, $context, $validator, $reviewRoundId);
    }

    public function runAdditionalActions(Decision $decision, Submission $submission, User $editor, Context $context, array $actions)
    {
        parent::runAdditionalActions($decision, $submission, $editor, $context, $actions);
    }

    public function getSteps(Submission $submission, Context $context, User $editor, ?ReviewRound $reviewRound): Steps
    {
        $steps = new Steps($this, $submission, $context, $reviewRound);

        $fakeDecision = $this->getFakeDecision($submission, $editor);

        $authors = $steps->getStageParticipants(Role::ROLE_ID_AUTHOR);
        if (count($authors)) {
            $mailable = new DecisionCancelSubmissionNotifyAuthor($context, $submission, $fakeDecision);
            $steps->addStep(new Email(
                $this->ACTION_NOTIFY_AUTHORS,
                __('editor.submission.decision.notifyAuthors'),
                __('editor.submission.decision.cancelSubmission.notifyAuthorsDescription'),
                $authors,
                $mailable
                    ->sender($editor)
                    ->recipients($authors),
                $context->getSupportedFormLocales()
            ));
        }

        return $steps;
    }
}

<?php
/**
 * @file classes/decision/types/Decline.php
 *
 * Copyright (c) 2014-2022 Simon Fraser University
 * Copyright (c) 2000-2022 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class decision
 *
 * @brief A decision to decline a submission for publication.
 */

namespace PKP\decision\types;

use PKP\decision\Decision;
use APP\submission\Submission;
use Illuminate\Validation\Validator;
use PKP\context\Context;
use PKP\decision\DecisionType;
use PKP\decision\Steps;
use PKP\decision\steps\Email;
use PKP\decision\types\traits\InExternalReviewRound;
use PKP\decision\types\traits\NotifyAuthors;
use PKP\decision\types\traits\NotifyReviewers;
use PKP\mail\mailables\DecisionDeclineNotifyAuthor;
use PKP\mail\mailables\DecisionNotifyReviewer;
use PKP\security\Role;
use PKP\submission\reviewRound\ReviewRound;
use PKP\user\User;

class Decline extends DecisionType
{
    use InExternalReviewRound;
    use NotifyAuthors;
    use NotifyReviewers;

    public function getDecision(): int
    {
        return Decision::DECLINE;
    }

    public function getNewStageId(Submission $submission, ?int $reviewRoundId): ?int
    {
        return null;
    }

    public function getNewStatus(): ?int
    {
        return Submission::STATUS_DECLINED;
    }

    public function getNewReviewRoundStatus(): ?int
    {
        return ReviewRound::REVIEW_ROUND_STATUS_DECLINED;
    }

    public function getLabel(?string $locale = null): string
    {
        return __('editor.submission.decision.decline', [], $locale);
    }

    public function getDescription(?string $locale = null): string
    {
        return __('editor.submission.decision.decline.description', [], $locale);
    }

    public function getLog(): string
    {
        return 'editor.submission.decision.decline.log';
    }

    public function getCompletedLabel(): string
    {
        return __('editor.submission.decision.decline.completed');
    }

    public function getCompletedMessage(Submission $submission): string
    {
        return __('editor.submission.decision.decline.completed.description', ['title' => $submission->getLocalizedFullTitle()]);
    }

    public function validate(array $props, Submission $submission, Context $context, Validator $validator, ?int $reviewRoundId = null)
    {
        // If there is no review round id, a validation error will already have been set
        if (!$reviewRoundId) {
            return;
        }

        parent::validate($props, $submission, $context, $validator, $reviewRoundId);

        if (!isset($props['actions'])) {
            return;
        }

        foreach ((array) $props['actions'] as $index => $action) {
            $actionErrorKey = 'actions.' . $index;
            switch ($action['id']) {
                case $this->ACTION_NOTIFY_AUTHORS:
                    $this->validateNotifyAuthorsAction($action, $actionErrorKey, $validator, $submission);
                    break;
                case $this->ACTION_NOTIFY_REVIEWERS:
                    $this->validateNotifyReviewersAction($action, $actionErrorKey, $validator, $submission, $reviewRoundId, self::REVIEW_ASSIGNMENT_COMPLETED);
                    break;
            }
        }
    }

    public function runAdditionalActions(Decision $decision, Submission $submission, User $editor, Context $context, array $actions)
    {
        parent::runAdditionalActions($decision, $submission, $editor, $context, $actions);

        foreach ($actions as $action) {
            switch ($action['id']) {
                case $this->ACTION_NOTIFY_AUTHORS:
                    $reviewAssignments = $this->getReviewAssignments($submission->getId(), $decision->getData('reviewRoundId'), self::REVIEW_ASSIGNMENT_COMPLETED);
                    $emailData = $this->getEmailDataFromAction($action);
                    $this->sendAuthorEmail(
                        new DecisionDeclineNotifyAuthor($context, $submission, $decision, $reviewAssignments),
                        $emailData,
                        $editor,
                        $submission,
                        $context
                    );
                    $this->shareReviewAttachmentFiles($emailData->attachments, $submission, $decision->getData('reviewRoundId'));
                    break;
                case $this->ACTION_NOTIFY_REVIEWERS:
                    $this->sendReviewersEmail(
                        new DecisionNotifyReviewer($context, $submission, $decision),
                        $this->getEmailDataFromAction($action),
                        $editor,
                        $submission
                    );
                    break;
            }
        }
    }

    public function getSteps(Submission $submission, Context $context, User $editor, ?ReviewRound $reviewRound): Steps
    {
        $steps = new Steps($this, $submission, $context, $reviewRound);

        $fakeDecision = $this->getFakeDecision($submission, $editor, $reviewRound);
        $fileAttachers = $this->getFileAttachers($submission, $context, $reviewRound);
        $reviewAssignments = $this->getReviewAssignments($submission->getId(), $reviewRound->getId(), self::REVIEW_ASSIGNMENT_COMPLETED);

        $authors = $steps->getStageParticipants(Role::ROLE_ID_AUTHOR);
        if (count($authors)) {
            $mailable = new DecisionDeclineNotifyAuthor($context, $submission, $fakeDecision, $reviewAssignments);
            $steps->addStep(new Email(
                $this->ACTION_NOTIFY_AUTHORS,
                __('editor.submission.decision.notifyAuthors'),
                __('editor.submission.decision.decline.notifyAuthorsDescription'),
                $authors,
                $mailable
                    ->sender($editor)
                    ->recipients($authors),
                $context->getSupportedFormLocales(),
                $fileAttachers
            ));
        }

        if (count($reviewAssignments)) {
            $reviewers = $steps->getReviewersFromAssignments($reviewAssignments);
            $mailable = new DecisionNotifyReviewer($context, $submission, $fakeDecision);
            $steps->addStep((new Email(
                $this->ACTION_NOTIFY_REVIEWERS,
                __('editor.submission.decision.notifyReviewers'),
                __('editor.submission.decision.notifyReviewers.description'),
                $reviewers,
                $mailable->sender($editor),
                $context->getSupportedFormLocales(),
                $fileAttachers
            ))->canChangeRecipients(true));
        }

        return $steps;
    }
}

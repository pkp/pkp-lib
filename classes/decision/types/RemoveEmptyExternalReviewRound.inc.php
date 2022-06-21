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
 * @brief A decision to remove empty external review round for a submission.
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
use PKP\decision\steps\Email;
use PKP\decision\types\contracts\DecisionRemovable;
use PKP\decision\types\traits\InExternalReviewRound;
use PKP\decision\types\traits\NotifyAuthors;
use PKP\decision\types\traits\withReviewRound;
use PKP\mail\mailables\DecisionBackToPreviousExternalReviewRoundNotifyAuthor;
use PKP\security\Role;
use PKP\submission\reviewRound\ReviewRound;
use PKP\user\User;

class RemoveEmptyExternalReviewRound extends DecisionType implements DecisionRemovable
{
    use NotifyAuthors;
    use withReviewRound;
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

        if (!isset($props['actions'])) {
            return;
        }

        if (!$this->canRemove($submission, $reviewRoundId)) {
            $validator->errors()->add('restriction', __('editor.submission.decision.removeEmptyExternalReviewRound.restriction'));
        }

        foreach ((array) $props['actions'] as $index => $action) {
            $actionErrorKey = 'actions.' . $index;
            switch ($action['id']) {
                case $this->ACTION_NOTIFY_AUTHORS:
                    $this->validateNotifyAuthorsAction($action, $actionErrorKey, $validator, $submission);
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
                    $this->sendAuthorEmail(
                        new DecisionBackToPreviousExternalReviewRoundNotifyAuthor($context, $submission, $decision),
                        $this->getEmailDataFromAction($action),
                        $editor,
                        $submission,
                        $context
                    );
                    break;
            }
        }

        $request = Application::get()->getRequest();

        /** @var ReviewRoundDAO $reviewRoundDao */
        $reviewRoundDao = DAORegistry::getDAO('ReviewRoundDAO');
        $reviewRoundDao->deleteById((int) $request->getUserVar('reviewRoundId'));
    }

    public function getSteps(Submission $submission, Context $context, User $editor, ?ReviewRound $reviewRound): ?Steps
    {
        $steps = new Steps($this, $submission, $context, $reviewRound);
        $fakeDecision = $this->getFakeDecision($submission, $editor);
        $fileAttachers = $this->getFileAttachers($submission, $context);

        $authors = $steps->getStageParticipants(Role::ROLE_ID_AUTHOR);

        if (count($authors)) {
            $mailable = new DecisionBackToPreviousExternalReviewRoundNotifyAuthor($context, $submission, $fakeDecision);
            $steps->addStep(new Email(
                $this->ACTION_NOTIFY_AUTHORS,
                __('editor.submission.decision.notifyAuthors'),
                __('editor.submission.decision.backToPreviousExternalReviewRound.notifyAuthorsDescription'),
                $authors,
                $mailable
                    ->sender($editor)
                    ->recipients($authors),
                $context->getSupportedFormLocales(),
                $fileAttachers
            ));
        }

        return $steps;
    }

    /**
     * Determine if the external review round can be removed/deleted
     */
    public function canRemove(Submission $submission, ?int $reviewRoundId): bool
    {
        if (!$reviewRoundId) {
            return false;
        }

        // If this is the only review round available to the workflow
        // then removing is not the right approach
        // but need to have the option to move to Submission instead
        if ($this->isOnlyReviewRound($submission, WORKFLOW_STAGE_ID_EXTERNAL_REVIEW)) {
            return false;
        }

        // If the review round have any reviewer assigned to it
        // that makes it a non empty review round
        // which it turns make it non removeable at this point
        if ($this->hasReviewerAssigned($reviewRoundId)) {
            return false;
        }

        return true;
    }
}

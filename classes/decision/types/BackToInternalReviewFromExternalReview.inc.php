<?php

/**
 * @file classes/decision/types/BackToInternalReviewFromExternalReview.inc.php
 *
 * Copyright (c) 2014-2022 Simon Fraser University
 * Copyright (c) 2000-2022 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class decision
 *
 * @brief A decision to back out to internal review stage from external review stage
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
use PKP\decision\types\contracts\DecisionRetractable;
use PKP\decision\types\traits\InExternalReviewRound;
use PKP\decision\types\traits\NotifyAuthors;
use PKP\decision\types\traits\NotifyReviewers;
use PKP\decision\types\traits\withReviewRound;
use PKP\mail\mailables\DecisionBackToInternalFromExternalReviewNotifyReviewer;
use PKP\mail\mailables\DecisionBackToInternalReviewNotifyAuthor;
use PKP\security\Role;
use PKP\submission\reviewAssignment\ReviewAssignmentDAO;
use PKP\submission\reviewRound\ReviewRound;
use PKP\user\User;

class BackToInternalReviewFromExternalReview extends DecisionType implements DecisionRetractable
{
    use NotifyAuthors;
    use NotifyReviewers;
    use withReviewRound;
    use InExternalReviewRound;

    public function getDecision(): int
    {
        return Decision::BACK_TO_INTERNAL_REVIEW_FROM_EXTERNAL_REVIEW;
    }

    public function getNewStageId(): ?int
    {
        return WORKFLOW_STAGE_ID_INTERNAL_REVIEW;
    }

    public function getNewStatus(): ?int
    {
        return null;
    }

    public function getNewReviewRoundStatus(): ?int
    {
        return null;
    }

    public function getLabel(?string $locale = null): string
    {
        return __('editor.submission.decision.backToInternalReviewFromExternalReviewRound', [], $locale);
    }

    public function getDescription(?string $locale = null): string
    {
        return __('editor.submission.decision.backToInternalReviewFromExternalReviewRound.description', [], $locale);
    }

    public function getLog(): string
    {
        return 'editor.submission.decision.backToInternalReviewFromExternalReviewRound.log';
    }

    public function getCompletedLabel(): string
    {
        return __('editor.submission.decision.backToInternalReviewFromExternalReviewRound.completed');
    }

    public function getCompletedMessage(Submission $submission): string
    {
        return __('editor.submission.decision.backToInternalReviewFromExternalReviewRound.completed.description', ['title' => $submission->getLocalizedFullTitle()]);
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

        if (!$this->canRetract($submission, $reviewRoundId)) {
            $validator->errors()->add('restriction', __('editor.submission.decision.backToInternalReviewFromExternalReviewRound.restriction'));
        }

        foreach ((array) $props['actions'] as $index => $action) {
            $actionErrorKey = 'actions.' . $index;
            switch ($action['id']) {
                case $this->ACTION_NOTIFY_AUTHORS:
                    $this->validateNotifyAuthorsAction($action, $actionErrorKey, $validator, $submission);
                    break;
                case $this->ACTION_NOTIFY_REVIEWERS:
                    $this->validateNotifyReviewersAction($action, $actionErrorKey, $validator, $submission, $reviewRoundId, false);
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
                        new DecisionBackToInternalReviewNotifyAuthor($context, $submission, $decision),
                        $this->getEmailDataFromAction($action),
                        $editor,
                        $submission,
                        $context
                    );
                    break;
                case $this->ACTION_NOTIFY_REVIEWERS:
                    $this->sendReviewersEmail(
                        new DecisionBackToInternalFromExternalReviewNotifyReviewer($context, $submission, $decision),
                        $this->getEmailDataFromAction($action),
                        $editor,
                        $submission
                    );
                    break;
            }
        }

        $request = Application::get()->getRequest();

        $reviewRoundDao = DAORegistry::getDAO('ReviewRoundDAO'); /** @var ReviewRoundDAO $reviewRoundDao */
        $reviewAssignmentDao = DAORegistry::getDAO('ReviewAssignmentDAO'); /** @var ReviewAssignmentDAO $reviewAssignmentDao */
        $reviewRoundId = (int)$request->getUserVar('reviewRoundId');

        $reviewAssignmentDao->deleteByReviewRoundId($reviewRoundId);
        $reviewRoundDao->deleteById($reviewRoundId);
    }

    public function getSteps(Submission $submission, Context $context, User $editor, ?ReviewRound $reviewRound): ?Steps
    {
        $steps = new Steps($this, $submission, $context, $reviewRound);

        $fakeDecision = $this->getFakeDecision($submission, $editor);
        $fileAttachers = $this->getFileAttachers($submission, $context);

        $authors = $steps->getStageParticipants(Role::ROLE_ID_AUTHOR);
        if (count($authors)) {
            $mailable = new DecisionBackToInternalReviewNotifyAuthor($context, $submission, $fakeDecision);
            $steps->addStep(new Email(
                $this->ACTION_NOTIFY_AUTHORS,
                __('editor.submission.decision.notifyAuthors'),
                __('editor.submission.decision.backToInternalReviewFromExternalReviewRound.notifyAuthorsDescription'),
                $authors,
                $mailable
                    ->sender($editor)
                    ->recipients($authors),
                $context->getSupportedFormLocales(),
                $fileAttachers
            ));
        }

        $reviewAssignments = $this->getActiveReviewAssignments($submission->getId(), $reviewRound->getId());
        if (count($reviewAssignments)) {
            $reviewers = $steps->getReviewersFromAssignments($reviewAssignments);
            $mailable = new DecisionBackToInternalFromExternalReviewNotifyReviewer($context, $submission, $fakeDecision);
            $steps->addStep((new Email(
                $this->ACTION_NOTIFY_REVIEWERS,
                __('editor.submission.decision.notifyReviewers'),
                __('editor.submission.decision.backToInternalReviewFromExternalReviewRound.notifyReviewers.description'),
                $reviewers,
                $mailable->sender($editor),
                $context->getSupportedFormLocales(),
                $fileAttachers
            ))->canChangeRecipients(true));
        }

        return $steps;
    }

    /**
     * Determine if decision can be backed out from current state
     */
    public function canRetract(Submission $submission, ?int $reviewRoundId): bool
    {
        if (!$reviewRoundId) {
            return false;
        }

        // can only back out to internal review stage from external review stage
        // only if there is only one review round availabel in the external review stage
        if ($this->hasMultipleReviewRound($submission, WORKFLOW_STAGE_ID_EXTERNAL_REVIEW)) {
            return false;
        }

        // If has any completed review assignment associated with this external review round
        // can not back out form this stage
        if ($this->hasCompletedReviewAssginment($submission, $reviewRoundId)) {
            return false;
        }

        // If has any submitted review by reviewer in this external review round
        // can not abck out from this stage
        if ($this->hasConfirmedReviewer($submission, $reviewRoundId)) {
            return false;
        }

        // Need to check if it has any internal review round available
        // before backing out to internal review stage form external review stage
        if (!$this->hasReviewRound($submission, WORKFLOW_STAGE_ID_INTERNAL_REVIEW)) {
            return false;
        }

        return true;
    }
}

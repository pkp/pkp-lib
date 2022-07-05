<?php

/**
 * @file classes/decision/types/BackToSubmissionFromExternalReview.inc.php
 *
 * Copyright (c) 2014-2022 Simon Fraser University
 * Copyright (c) 2000-2022 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class decision
 *
 * @brief A decision to back out to submission from external review stage
 */

namespace PKP\decision\types;

use APP\decision\Decision;
use APP\submission\Submission;
use Illuminate\Validation\Validator;
use PKP\context\Context;
use PKP\db\DAORegistry;
use PKP\decision\DecisionType;
use PKP\decision\Steps;
use PKP\decision\steps\Email;
use PKP\decision\types\interfaces\DecisionRetractable;
use PKP\decision\types\traits\InExternalReviewRound;
use PKP\decision\types\traits\NotifyAuthors;
use PKP\decision\types\traits\NotifyReviewers;
use PKP\mail\mailables\DecisionBackToSubmissionNotifyAuthor;
use PKP\mail\mailables\DecisionReviewerUnassignedNotifyReviewer;
use PKP\security\Role;
use PKP\submission\reviewAssignment\ReviewAssignmentDAO;
use PKP\submission\reviewRound\ReviewRound;
use PKP\submission\reviewRound\ReviewRoundDAO;
use PKP\user\User;

class BackToSubmissionFromExternalReview extends DecisionType implements DecisionRetractable
{
    use NotifyAuthors;
    use NotifyReviewers;
    use InExternalReviewRound;

    public function getDecision(): int
    {
        return Decision::BACK_TO_SUBMISSION_FROM_EXTERNAL_REVIEW;
    }

    public function getNewStageId(): ?int
    {
        return WORKFLOW_STAGE_ID_SUBMISSION;
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
        return __('editor.submission.decision.backToSubmission', [], $locale);
    }

    public function getDescription(?string $locale = null): string
    {
        return __('editor.submission.decision.backToSubmissionFromExternalReviewRound.description', [], $locale);
    }

    public function getLog(): string
    {
        return 'editor.submission.decision.backToSubmissionFromExternalReviewRound.log';
    }

    public function getCompletedLabel(): string
    {
        return __('editor.submission.decision.backToSubmissionFromExternalReviewRound.completed');
    }

    public function getCompletedMessage(Submission $submission): string
    {
        return __('editor.submission.decision.backToSubmissionFromExternalReviewRound.completed.description', ['title' => $submission->getLocalizedFullTitle()]);
    }

    public function validate(array $props, Submission $submission, Context $context, Validator $validator, ?int $reviewRoundId = null)
    {
        // If there is no review round id, a validation error will already have been set
        if (!$reviewRoundId) {
            return;
        }

        parent::validate($props, $submission, $context, $validator, $reviewRoundId);

        if (!$this->canRetract($submission, $reviewRoundId)) {
            $validator->errors()->add('restriction', __('editor.submission.decision.backToSubmissionFromExternalReviewRound.restriction'));
        }

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
                    $this->validateNotifyReviewersAction($action, $actionErrorKey, $validator, $submission, $reviewRoundId, self::REVIEW_ASSIGNMENT_ACTIVE);
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
                        new DecisionBackToSubmissionNotifyAuthor($context, $submission, $decision),
                        $this->getEmailDataFromAction($action),
                        $editor,
                        $submission,
                        $context
                    );
                    break;
                case $this->ACTION_NOTIFY_REVIEWERS:
                    $this->sendReviewersEmail(
                        new DecisionReviewerUnassignedNotifyReviewer($context, $submission, $decision),
                        $this->getEmailDataFromAction($action),
                        $editor,
                        $submission
                    );
                    break;
            }
        }

        $reviewRoundDao = DAORegistry::getDAO('ReviewRoundDAO'); /** @var ReviewRoundDAO $reviewRoundDao */
        $reviewAssignmentDao = DAORegistry::getDAO('ReviewAssignmentDAO'); /** @var ReviewAssignmentDAO $reviewAssignmentDao */
        $reviewRoundId = $decision->getData('reviewRoundId');

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
            $mailable = new DecisionBackToSubmissionNotifyAuthor($context, $submission, $fakeDecision);
            $steps->addStep(new Email(
                $this->ACTION_NOTIFY_AUTHORS,
                __('editor.submission.decision.notifyAuthors'),
                __('editor.submission.decision.backToSubmission.notifyAuthorsDescription'),
                $authors,
                $mailable
                    ->sender($editor)
                    ->recipients($authors),
                $context->getSupportedFormLocales(),
                $fileAttachers
            ));
        }

        $reviewAssignments = $this->getReviewAssignments($submission->getId(), $reviewRound->getId(), self::REVIEW_ASSIGNMENT_ACTIVE);
        if (count($reviewAssignments)) {
            $reviewers = $steps->getReviewersFromAssignments($reviewAssignments);
            $mailable = new DecisionReviewerUnassignedNotifyReviewer($context, $submission, $fakeDecision);
            $steps->addStep((new Email(
                $this->ACTION_NOTIFY_REVIEWERS,
                __('editor.submission.decision.notifyReviewers'),
                __('editor.submission.decision.reviewerUnassigned.notifyReviewers.description'),
                $reviewers,
                $mailable->sender($editor),
                $context->getSupportedFormLocales(),
                $fileAttachers
            ))->canChangeRecipients(true));
        }

        return $steps;
    }

    /**
     * Determine if can back out to submission stage from external review stage
     */
    public function canRetract(Submission $submission, ?int $reviewRoundId): bool
    {
        if (!$reviewRoundId) {
            return false;
        }

        /** @var ReviewRoundDAO $reviewRoundDao */
        $reviewRoundDao = DAORegistry::getDAO('ReviewRoundDAO');

        // If thers is multiple external review round associated
        // can not back out
        if ($reviewRoundDao->getReviewRoundCountBySubmissionId($submission->getId(), WORKFLOW_STAGE_ID_EXTERNAL_REVIEW) > 1) {
            return false;
        }

        // if there is any completed review by reviewer
        // can not back out
        $completedReviewAssignments = $this->getReviewAssignments($submission->getId(), $reviewRoundId, self::REVIEW_ASSIGNMENT_COMPLETED);
        if (count($completedReviewAssignments) > 0) {
            return false;
        }

        // if there is any submitted review by reviewer that is not cancelled
        // can not back out
        $confirmedReviewerIds = $this->getReviewerIds($submission->getId(), $reviewRoundId, self::REVIEW_ASSIGNMENT_CONFIRMED);
        if (count($confirmedReviewerIds) > 0) {
            return false;
        }

        // Only applicable for OMP
        // For OMP, need to check if it has any internal review round available
        // before backing out to submission from external review
        if ($reviewRoundDao->submissionHasReviewRound($submission->getId(), WORKFLOW_STAGE_ID_INTERNAL_REVIEW)) {
            return false;
        }

        return true;
    }
}

<?php
/**
 * @file classes/decision/types/BackToExternalReviewFromCopyediting.inc.php
 *
 * Copyright (c) 2014-2022 Simon Fraser University
 * Copyright (c) 2000-2022 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class decision
 *
 * @brief A decision to return a submission to the external review stage.
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
use PKP\decision\types\traits\InCopyeditingStage;
use PKP\decision\types\traits\NotifyAuthors;
use PKP\decision\types\traits\WithReviewAssignments;
use PKP\mail\mailables\DecisionBackToReviewNotifyAuthor;
use PKP\security\Role;
use PKP\submission\reviewRound\ReviewRound;
use PKP\submission\reviewRound\ReviewRoundDAO;
use PKP\user\User;

class BackToExternalReviewFromCopyediting extends DecisionType implements DecisionRetractable
{
    use NotifyAuthors;
    use WithReviewAssignments;
    use InCopyeditingStage;

    public function getDecision(): int
    {
        return Decision::BACK_TO_EXTERNAL_REVIEW;
    }

    public function getNewStageId(): int
    {
        return WORKFLOW_STAGE_ID_EXTERNAL_REVIEW;
    }

    public function getLabel(?string $locale = null): string
    {
        return __('editor.submission.decision.backToReview', [], $locale);
    }

    public function getDescription(?string $locale = null): string
    {
        return __('editor.submission.decision.backToReview.description', [], $locale);
    }

    public function getLog(): string
    {
        return 'editor.submission.decision.backToReview.log';
    }

    public function getCompletedLabel(): string
    {
        return __('editor.submission.decision.backToReview.completed');
    }

    public function getCompletedMessage(Submission $submission): string
    {
        return __('editor.submission.decision.backToReview.completed.description', ['title' => $submission->getLocalizedFullTitle()]);
    }

    public function validate(array $props, Submission $submission, Context $context, Validator $validator, ?int $reviewRoundId = null)
    {
        parent::validate($props, $submission, $context, $validator, $reviewRoundId);

        if (!isset($props['actions'])) {
            return;
        }

        if (!$this->canRetract($submission, $reviewRoundId)) {
            $validator->errors()->add('restriction', __('editor.submission.decision.backToReview.restriction'));
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
                        new DecisionBackToReviewNotifyAuthor($context, $submission, $decision),
                        $this->getEmailDataFromAction($action),
                        $editor,
                        $submission,
                        $context
                    );
                    break;
            }
        }
    }

    public function getSteps(Submission $submission, Context $context, User $editor, ?ReviewRound $reviewRound): Steps
    {
        $steps = new Steps($this, $submission, $context);

        $fakeDecision = $this->getFakeDecision($submission, $editor);
        $fileAttachers = $this->getFileAttachers($submission, $context);

        $authors = $steps->getStageParticipants(Role::ROLE_ID_AUTHOR);
        if (count($authors)) {
            $mailable = new DecisionBackToReviewNotifyAuthor($context, $submission, $fakeDecision);
            $steps->addStep(new Email(
                $this->ACTION_NOTIFY_AUTHORS,
                __('editor.submission.decision.notifyAuthors'),
                __('editor.submission.decision.backToReview.notifyAuthorsDescription'),
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
     * Determine if can back out to external review stage from the copy editing stage
     */
    public function canRetract(Submission $submission, ?int $reviewRoundId): bool
    {
        /** @var ReviewRoundDAO $reviewRoundDao */
        $reviewRoundDao = DAORegistry::getDAO('ReviewRoundDAO');

        // if there is no external review round associated with it
        // can not back out to external review stage
        if (!$reviewRoundDao->submissionHasReviewRound($submission->getId(), WORKFLOW_STAGE_ID_EXTERNAL_REVIEW)) {
            return false;
        }

        return true;
    }
}

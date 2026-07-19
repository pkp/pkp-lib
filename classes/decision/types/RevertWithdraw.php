<?php
/**
 * @file classes/decision/types/RevertWithdraw.php
 *
 * Copyright (c) 2014-2022 Simon Fraser University
 * Copyright (c) 2000-2022 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class RevertWithdraw
 *
 * @brief A decision to revert a withdrawal and return a submission to the
 *   active queue. Applies when the withdrawal was recorded in the submission
 *   stage (mirrors RevertInitialDecline).
 */

namespace PKP\decision\types;

use APP\decision\Decision;
use APP\submission\Submission;
use Illuminate\Validation\Validator;
use PKP\context\Context;
use PKP\decision\DecisionType;
use PKP\decision\Steps;
use PKP\decision\steps\Email;
use PKP\decision\types\traits\InSubmissionStage;
use PKP\decision\types\traits\NotifyAuthors;
use PKP\mail\mailables\DecisionRevertWithdrawNotifyAuthor;
use PKP\security\Role;
use PKP\submission\reviewRound\ReviewRound;
use PKP\user\User;

class RevertWithdraw extends DecisionType
{
    use InSubmissionStage;
    use NotifyAuthors;

    public function getDecision(): int
    {
        return Decision::REVERT_WITHDRAW;
    }

    public function getNewStageId(Submission $submission, ?int $reviewRoundId): ?int
    {
        return null;
    }

    public function getNewStatus(): int
    {
        return Submission::STATUS_QUEUED;
    }

    public function getNewReviewRoundStatus(): ?int
    {
        return null;
    }

    public function getLabel(?string $locale = null): string
    {
        return __('editor.submission.decision.revertWithdraw', [], $locale);
    }

    public function getDescription(?string $locale = null): string
    {
        return __('editor.submission.decision.revertWithdraw.description', [], $locale);
    }

    public function getLog(): string
    {
        return 'editor.submission.decision.revertWithdraw.log';
    }

    public function getCompletedLabel(): string
    {
        return __('editor.submission.decision.revertWithdraw.completed');
    }

    public function getCompletedMessage(Submission $submission): string
    {
        return __('editor.submission.decision.revertWithdraw.completed.description', [
            'title' => $submission?->getCurrentPublication()?->getLocalizedFullTitle(null, 'html') ?? '',
        ]);
    }

    public function validate(array $props, Submission $submission, Context $context, Validator $validator, ?int $reviewRoundId = null)
    {
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
                        new DecisionRevertWithdrawNotifyAuthor($context, $submission, $decision),
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
            $mailable = new DecisionRevertWithdrawNotifyAuthor($context, $submission, $fakeDecision);
            $steps->addStep(new Email(
                $this->ACTION_NOTIFY_AUTHORS,
                __('editor.submission.decision.notifyAuthors'),
                __('editor.submission.decision.revertWithdraw.notifyAuthorsDescription'),
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
}

<?php
/**
 * @file classes/decision/types/RevertInitialDecline.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class decision
 *
 * @brief A decision to revert a declined submission and return it to the active queue when it is still in the submission stage.
 */

namespace PKP\decision\types;

use APP\decision\Decision;
use APP\submission\Submission;
use Illuminate\Validation\Validator;
use PKP\context\Context;
use PKP\decision\steps\Email;
use PKP\decision\Type;
use PKP\decision\types\traits\InSubmissionStage;
use PKP\decision\types\traits\NotifyAuthors;
use PKP\decision\Workflow;
use PKP\mail\mailables\DecisionRevertInitialDeclineNotifyAuthor;
use PKP\security\Role;
use PKP\submission\reviewRound\ReviewRound;
use PKP\user\User;

class RevertInitialDecline extends Type
{
    use InSubmissionStage;
    use NotifyAuthors;

    public function getDecision(): int
    {
        return Decision::REVERT_INITIAL_DECLINE;
    }

    public function getNewStageId(): ?int
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
        return __('editor.submission.decision.revertDecline', [], $locale);
    }

    public function getDescription(?string $locale = null): string
    {
        return __('editor.submission.decision.revertDecline.description', [], $locale);
    }

    public function getLog(): string
    {
        return 'editor.submission.decision.revertDecline.log';
    }

    public function getCompletedLabel(): string
    {
        return __('editor.submission.decision.revertDecline.completed');
    }

    public function getCompletedMessage(Submission $submission): string
    {
        return __('editor.submission.decision.revertInitialDecline.completed.description', ['title' => $submission->getLocalizedFullTitle()]);
    }

    public function validate(array $props, Submission $submission, Context $context, Validator $validator, ?int $reviewRoundId = null)
    {
        parent::validate($props, $submission, $context, $validator, $reviewRoundId);

        foreach ($props['actions'] as $index => $action) {
            $actionErrorKey = 'actions.' . $index;
            switch ($action['id']) {
                case $this->ACTION_NOTIFY_AUTHORS:
                    $this->validateNotifyAuthorsAction($action, $actionErrorKey, $validator, $submission);
                    break;
            }
        }
    }

    public function callback(Decision $decision, Submission $submission, User $editor, Context $context, array $actions)
    {
        parent::callback($decision, $submission, $editor, $context, $actions);

        foreach ($actions as $action) {
            switch ($action['id']) {
                case $this->ACTION_NOTIFY_AUTHORS:
                    $this->sendAuthorEmail(
                        new DecisionRevertInitialDeclineNotifyAuthor($context, $submission, $decision),
                        $this->getEmailDataFromAction($action),
                        $editor,
                        $submission,
                        $context
                    );
                    break;
            }
        }
    }

    public function getWorkflow(Submission $submission, Context $context, User $editor, ?ReviewRound $reviewRound): Workflow
    {
        $workflow = new Workflow($this, $submission, $context);

        $fakeDecision = $this->getFakeDecision($submission, $editor);
        $fileAttachers = $this->getFileAttachers($submission, $context);

        $authors = $workflow->getStageParticipants(Role::ROLE_ID_AUTHOR);
        if (count($authors)) {
            $mailable = new DecisionRevertInitialDeclineNotifyAuthor($context, $submission, $fakeDecision);
            $workflow->addStep(new Email(
                $this->ACTION_NOTIFY_AUTHORS,
                __('editor.submission.decision.notifyAuthors'),
                __('editor.submission.decision.revertDecline.notifyAuthorsDescription'),
                $authors,
                $mailable
                    ->sender($editor)
                    ->recipients($authors),
                $context->getSupportedFormLocales(),
                $fileAttachers
            ));
        }

        return $workflow;
    }
}

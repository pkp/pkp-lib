<?php
/**
 * @file classes/decision/types/RevertDecline.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class decision
 *
 * @brief A decision to revert a declined submission and return it to the active queue.
 */

namespace PKP\decision\types;

use APP\decision\Decision;
use APP\submission\Submission;
use Illuminate\Validation\Validator;
use PKP\context\Context;
use PKP\decision\steps\Email;
use PKP\decision\Type;
use PKP\decision\types\traits\InExternalReviewRound;
use PKP\decision\types\traits\NotifyAuthors;
use PKP\decision\Workflow;
use PKP\mail\mailables\DecisionRevertDeclineNotifyAuthor;
use PKP\security\Role;
use PKP\submission\reviewRound\ReviewRound;
use PKP\user\User;

class RevertDecline extends Type
{
    use InExternalReviewRound;
    use NotifyAuthors;

    public function getDecision(): int
    {
        return Decision::REVERT_DECLINE;
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
        return __('editor.submission.decision.revertDecline.completed.description', ['title' => $submission->getLocalizedFullTitle()]);
    }

    public function validate(array $props, Submission $submission, Context $context, Validator $validator, ?int $reviewRoundId = null)
    {
        // If there is no review round id, a validation error will already have been set
        if (!$reviewRoundId) {
            return;
        }

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
                        new DecisionRevertDeclineNotifyAuthor($context, $submission, $decision),
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
        $workflow = new Workflow($this, $submission, $context, $reviewRound);

        $fakeDecision = $this->getFakeDecision($submission, $editor, $reviewRound);
        $fileAttachers = $this->getFileAttachers($submission, $context, $reviewRound);

        $authors = $workflow->getStageParticipants(Role::ROLE_ID_AUTHOR);
        if (count($authors)) {
            $mailable = new DecisionRevertDeclineNotifyAuthor($context, $submission, $fakeDecision);
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

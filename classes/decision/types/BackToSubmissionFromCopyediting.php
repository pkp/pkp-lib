<?php
/**
 * @file classes/decision/types/BackToSubmissionFromCopyediting.php
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
use PKP\components\fileAttachers\FileStage;
use PKP\components\fileAttachers\Library;
use PKP\components\fileAttachers\Upload;
use PKP\context\Context;
use PKP\db\DAORegistry;
use PKP\decision\DecisionType;
use PKP\decision\Steps;
use PKP\decision\steps\Email;
use PKP\decision\types\interfaces\DecisionRetractable;
use PKP\decision\types\traits\NotifyAuthors;
use PKP\decision\types\traits\WithReviewAssignments;
use PKP\mail\mailables\DecisionBackToSubmissionNotifyAuthor;
use PKP\security\Role;
use PKP\submission\reviewRound\ReviewRound;
use PKP\submission\reviewRound\ReviewRoundDAO;
use PKP\submissionFile\SubmissionFile;
use PKP\user\User;

class BackToSubmissionFromCopyediting extends DecisionType implements DecisionRetractable
{
    use NotifyAuthors;
    use WithReviewAssignments;

    public function getDecision(): int
    {
        return Decision::BACK_TO_SUBMISSION_FROM_COPYEDITING;
    }

    public function getStageId(): int
    {
        return WORKFLOW_STAGE_ID_EDITING;
    }

    public function getNewStageId(): int
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
        return __('editor.submission.decision.backToSubmissionFromCopyediting.description', [], $locale);
    }

    public function getLog(): string
    {
        return 'editor.submission.decision.backToSubmissionFromCopyediting.log';
    }

    public function getCompletedLabel(): string
    {
        return __('editor.submission.decision.backToSubmission.completed');
    }

    public function getCompletedMessage(Submission $submission): string
    {
        return __('editor.submission.decision.backToSubmission.completed.description', ['title' => $submission->getLocalizedFullTitle()]);
    }

    public function validate(array $props, Submission $submission, Context $context, Validator $validator, ?int $reviewRoundId = null)
    {
        parent::validate($props, $submission, $context, $validator, $reviewRoundId);

        if (!$this->canRetract($submission, $reviewRoundId)) {
            $validator->errors()->add('restriction', __('editor.submission.decision.backToSubmission.restriction'));
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

        return $steps;
    }

    /**
     * Determine if can back out to submission stage directly from copy editing stage
     */
    public function canRetract(Submission $submission, ?int $reviewRoundId): bool
    {
        /** @var ReviewRoundDAO $reviewRoundDao */
        $reviewRoundDao = DAORegistry::getDAO('ReviewRoundDAO');

        // if has any external review round asscoiated
        // can not back out to submission stage directly from copy editing stage
        if ($reviewRoundDao->submissionHasReviewRound($submission->getId(), WORKFLOW_STAGE_ID_EXTERNAL_REVIEW)) {
            return false;
        }

        // if has any internal review round asscoiated
        // can not back out to submission stage directly from copy editing stage
        if ($reviewRoundDao->submissionHasReviewRound($submission->getId(), WORKFLOW_STAGE_ID_INTERNAL_REVIEW)) {
            return false;
        }

        return true;
    }

    /**
     * Get the submission file stages that are permitted to be attached to emails
     * sent in this decision
     *
     * @return array<int>
     */
    protected function getAllowedAttachmentFileStages(): array
    {
        return [
            SubmissionFile::SUBMISSION_FILE_FINAL
        ];
    }

    /**
     * Get the file attacher components supported for emails in this decision
     */
    protected function getFileAttachers(Submission $submission, Context $context): array
    {
        $attachers = [
            new Upload(
                $context,
                __('common.upload.addFile'),
                __('common.upload.addFile.description'),
                __('common.upload.addFile')
            ),
        ];

        $attachers[] = (new FileStage(
            $context,
            $submission,
            __('submission.submit.submissionFiles'),
            __('email.addAttachment.submissionFiles.submissionDescription'),
            __('email.addAttachment.submissionFiles.attach')
        ))
            ->withFileStage(
                SubmissionFile::SUBMISSION_FILE_FINAL,
                __('submission.finalDraft')
            );

        $attachers[] = new Library(
            $context,
            $submission
        );

        return $attachers;
    }
}

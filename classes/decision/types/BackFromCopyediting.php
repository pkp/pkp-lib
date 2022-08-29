<?php
/**
 * @file classes/decision/types/BackFromCopyediting.php
 *
 * Copyright (c) 2014-2022 Simon Fraser University
 * Copyright (c) 2000-2022 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class BackFromCopyediting
 *
 * @brief A decision to return a submission back from the copyediting stage. It follows as
 *   if has any external review round, back to external review stage.
 *   if has any internal review round but no external review sound, back to internal review stage.
 *   if has no internal and external review round, backt to submission stage.
 *
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
use PKP\decision\types\traits\NotifyAuthors;
use PKP\mail\mailables\DecisionBackFromCopyeditingNotifyAuthor;
use PKP\security\Role;
use PKP\submission\reviewRound\ReviewRound;
use PKP\submission\reviewRound\ReviewRoundDAO;
use PKP\submissionFile\SubmissionFile;
use PKP\user\User;

class BackFromCopyediting extends DecisionType
{
    use NotifyAuthors;

    public function getNewStatus(): ?int
    {
        return null;
    }

    public function getNewReviewRoundStatus(): ?int
    {
        return null;
    }

    public function getDecision(): int
    {
        return Decision::BACK_FROM_COPYEDITING;
    }

    public function getStageId(): int
    {
        return WORKFLOW_STAGE_ID_EDITING;
    }

    /**
     * Determine the possible new stage id for this decision
     *
     * The determining process follows as :
     *
     * If there is any external review round associated with it,
     * new stage need to be external review stage
     *
     * If there is no external review round associated with it but there is internal review round,
     * new stage need to be internal review stage
     *
     * If there is no external or internal review round associated with it
     * new stage need to submission stage
     */
    public function getNewStageId(Submission $submission, ?int $reviewRoundId): ?int
    {
        /** @var ReviewRoundDAO $reviewRoundDao */
        $reviewRoundDao = DAORegistry::getDAO('ReviewRoundDAO');

        if ($reviewRoundDao->submissionHasReviewRound($submission->getId(), WORKFLOW_STAGE_ID_EXTERNAL_REVIEW)) {
            return WORKFLOW_STAGE_ID_EXTERNAL_REVIEW;
        }

        if ($reviewRoundDao->submissionHasReviewRound($submission->getId(), WORKFLOW_STAGE_ID_INTERNAL_REVIEW)) {
            return WORKFLOW_STAGE_ID_INTERNAL_REVIEW;
        }

        return WORKFLOW_STAGE_ID_SUBMISSION;
    }

    public function getLabel(?string $locale = null): string
    {
        return __('editor.submission.decision.backFromCopyediting', [], $locale);
    }

    public function getDescription(?string $locale = null): string
    {
        return __('editor.submission.decision.backFromCopyediting.description', [], $locale);
    }

    public function getLog(): string
    {
        return __('editor.submission.decision.backFromCopyediting.log');
    }

    public function getCompletedLabel(): string
    {
        return __('editor.submission.decision.backFromCopyediting.completed');
    }

    public function getCompletedMessage(Submission $submission): string
    {
        return __('editor.submission.decision.backFromCopyediting.completed.description', ['title' => $submission->getLocalizedFullTitle()]);
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
                        new DecisionBackFromCopyeditingNotifyAuthor($context, $submission, $decision),
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
            $mailable = new DecisionBackFromCopyeditingNotifyAuthor($context, $submission, $fakeDecision);
            $steps->addStep(new Email(
                $this->ACTION_NOTIFY_AUTHORS,
                __('editor.submission.decision.notifyAuthors'),
                __('editor.submission.decision.backFromCopyediting.notifyAuthorsDescription'),
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
     * Get the submission file stages that are permitted to be attached to emails
     * sent in this decision
     *
     * @return int[]
     */
    protected function getAllowedAttachmentFileStages(): array
    {
        return [
            SubmissionFile::SUBMISSION_FILE_FINAL,
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

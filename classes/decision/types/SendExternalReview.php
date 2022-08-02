<?php
/**
 * @file classes/decision/types/SendExternalReview.php
 *
 * Copyright (c) 2014-2022 Simon Fraser University
 * Copyright (c) 2000-2022 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class decision
 *
 * @brief A decision to send a submission to the external review stage.
 */

namespace PKP\decision\types;

use APP\decision\Decision;
use APP\facades\Repo;
use APP\submission\Submission;
use Illuminate\Validation\Validator;
use PKP\context\Context;
use PKP\decision\DecisionType;
use PKP\decision\Steps;
use PKP\decision\steps\Email;
use PKP\decision\steps\PromoteFiles;
use PKP\decision\types\traits\InSubmissionStage;
use PKP\decision\types\traits\NotifyAuthors;
use PKP\mail\mailables\DecisionSendExternalReviewNotifyAuthor;
use PKP\security\Role;
use PKP\submission\reviewRound\ReviewRound;
use PKP\submissionFile\SubmissionFile;
use PKP\user\User;

class SendExternalReview extends DecisionType
{
    use InSubmissionStage;
    use NotifyAuthors;

    public function getDecision(): int
    {
        return Decision::EXTERNAL_REVIEW;
    }

    public function getNewStageId(): int
    {
        return WORKFLOW_STAGE_ID_EXTERNAL_REVIEW;
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
        return __('editor.submission.decision.sendExternalReview', [], $locale);
    }

    public function getDescription(?string $locale = null): string
    {
        return __('editor.submission.decision.sendExternalReview.description', [], $locale);
    }

    public function getLog(): string
    {
        return 'editor.submission.decision.sendExternalReview.log';
    }

    public function getCompletedLabel(): string
    {
        return __('editor.submission.decision.sendExternalReview.completed');
    }

    public function getCompletedMessage(Submission $submission): string
    {
        return __('editor.submission.decision.sendExternalReview.completed.description', ['title' => $submission->getLocalizedFullTitle()]);
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
                        new DecisionSendExternalReviewNotifyAuthor($context, $submission, $decision),
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
            $mailable = new DecisionSendExternalReviewNotifyAuthor($context, $submission, $fakeDecision);
            $steps->addStep(new Email(
                $this->ACTION_NOTIFY_AUTHORS,
                __('editor.submission.decision.notifyAuthors'),
                __('editor.submission.decision.sendExternalReview.notifyAuthorsDescription'),
                $authors,
                $mailable
                    ->sender($editor)
                    ->recipients($authors),
                $context->getSupportedFormLocales(),
                $fileAttachers
            ));
        }

        $promoteFilesStep = new PromoteFiles(
            'promoteFilesToReview',
            __('editor.submission.selectFiles'),
            __('editor.submission.decision.promoteFiles.externalReview'),
            SubmissionFile::SUBMISSION_FILE_REVIEW_FILE,
            $submission,
            $this->getFileGenres($context->getId())
        );

        $steps->addStep($this->withFilePromotionLists($submission, $promoteFilesStep));

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
            SubmissionFile::SUBMISSION_FILE_SUBMISSION,
        ];
    }

    /**
     * Get the file promotion step with file promotion lists
     * added to it
     */
    protected function withFilePromotionLists(Submission $submission, PromoteFiles $step): PromoteFiles
    {
        return $step->addFileList(
            __('submission.submit.submissionFiles'),
            Repo::submissionFile()
                ->getCollector()
                ->filterBySubmissionIds([$submission->getId()])
                ->filterByFileStages([SubmissionFile::SUBMISSION_FILE_SUBMISSION])
        );
    }
}

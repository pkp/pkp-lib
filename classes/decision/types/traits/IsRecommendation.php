<?php
/**
 * @file classes/decision/types/traits/IsRecommendation.php
 *
 * Copyright (c) 2014-2024 Simon Fraser University
 * Copyright (c) 2000-2024 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class decision
 *
 * @brief Helper functions for decisions that are recommendations
 */

namespace PKP\decision\types\traits;

use APP\core\Application;
use APP\decision\Decision;
use APP\facades\Repo;
use APP\submission\Submission;
use Exception;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Validator;
use PKP\context\Context;
use PKP\db\DAORegistry;
use PKP\decision\DecisionType;
use PKP\decision\Steps;
use PKP\decision\steps\Email;
use PKP\facades\Locale;
use PKP\file\TemporaryFileManager;
use PKP\mail\EmailData;
use PKP\mail\Mailable;
use PKP\mail\mailables\RecommendationNotifyEditors;
use PKP\note\Note;
use PKP\query\Query;
use PKP\security\Role;
use PKP\stageAssignment\StageAssignment;
use PKP\submission\reviewRound\ReviewRound;
use PKP\submissionFile\SubmissionFile;
use PKP\user\User;

trait IsRecommendation
{
    protected string $ACTION_DISCUSSION = 'discussion';

    /**
     * Get a short label describing this recommendation
     *
     * eg - Accept Submission
     */
    abstract public function getRecommendationLabel(): string;

    /**
     * Validate the action to create a discussion with this recommendation
     */
    public function validate(array $props, Submission $submission, Context $context, Validator $validator, ?int $reviewRoundId = null)
    {
        foreach ((array) $props['actions'] as $index => $action) {
            switch ($action['id']) {
                case $this->ACTION_DISCUSSION:
                    $errors = $this->validateEmailAction($action, $submission, $this->getAllowedAttachmentFileStages());
                    if (count($errors)) {
                        foreach ($errors as $key => $error) {
                            $validator->errors()->add('actions.' . $index . '.' . $key, $error);
                        }
                    }
                    break;
            }
        }
    }

    public function runAdditionalActions(Decision $decision, Submission $submission, User $editor, Context $context, array $actions)
    {
        parent::runAdditionalActions($decision, $submission, $editor, $context, $actions);

        foreach ($actions as $action) {
            switch ($action['id']) {
                case $this->ACTION_DISCUSSION:
                    $this->addRecommendationQuery(
                        $this->getEmailDataFromAction($action),
                        $submission,
                        $editor,
                        $context
                    );
                    break;
            }
        }
    }

    public function getSteps(Submission $submission, Context $context, User $editor, ?ReviewRound $reviewRound): Steps
    {
        $steps = new Steps($this, $submission, $context, $reviewRound);

        $fakeDecision = $this->getFakeDecision($submission, $editor);
        $fileAttachers = $this->getFileAttachers($submission, $context, $reviewRound);
        $editors = $steps->getDecidingEditors();
        $reviewAssignments = $this->getReviewAssignments($submission->getId(), $reviewRound->getId(), DecisionType::REVIEW_ASSIGNMENT_COMPLETED);
        $mailable = new RecommendationNotifyEditors($context, $submission, $fakeDecision, $reviewAssignments);

        $steps->addStep((new Email(
            $this->ACTION_DISCUSSION,
            __('editor.submissionReview.recordRecommendation.notifyEditors'),
            __('editor.submission.recommend.notifyEditors.description'),
            $editors,
            $mailable
                ->sender($editor)
                ->recipients($editors),
            $context->getSupportedFormLocales(),
            $fileAttachers
        ))->canSkip(false));

        return $steps;
    }

    /**
     * Create a query (discussion) among deciding editors
     * add attachments to the head note and send email
     */
    protected function addRecommendationQuery(EmailData $email, Submission $submission, User $editor, Context $context): void
    {
        $queryParticipantIds = [];
        // Replaces StageAssignmentDAO::getEditorsAssignedToStage
        $editorsStageAssignments = StageAssignment::withSubmissionIds([$submission->getId()])
            ->withStageIds([$this->getStageId()])
            ->withRoleIds([Role::ROLE_ID_MANAGER, Role::ROLE_ID_SUB_EDITOR])
            ->get();

        foreach ($editorsStageAssignments as $editorsStageAssignment) {
            if (!$editorsStageAssignment->recommendOnly) {
                if (!in_array($editorsStageAssignment->userId, $queryParticipantIds)) {
                    $queryParticipantIds[] = $editorsStageAssignment->userId;
                }
            }
        }

        $queryId = Repo::query()->addQuery(
            $submission->getId(),
            $this->getStageId(),
            $email->subject,
            $email->body,
            $editor,
            $queryParticipantIds,
            $context->getId(),
            false
        );

        $query = Query::find($queryId);
        $note = Repo::note()->getHeadNote($query->id);
        $mailable = new Mailable();
        foreach ($email->attachments as $attachment) {
            if (isset($attachment[Mailable::ATTACHMENT_TEMPORARY_FILE])) {
                $temporaryFileManager = new TemporaryFileManager();
                $temporaryFile = $temporaryFileManager->getFile($attachment[Mailable::ATTACHMENT_TEMPORARY_FILE], $editor->getId());
                if (!$temporaryFile) {
                    throw new Exception('Could not find temporary file ' . $attachment[Mailable::ATTACHMENT_TEMPORARY_FILE] . ' to attach to the query note.');
                }
                $this->addSubmissionFileToNoteFromFilePath(
                    $temporaryFile->getFilePath(),
                    $attachment['name'],
                    $note,
                    $editor,
                    $submission,
                    $context
                );
                $mailable->attachTemporaryFile($attachment[Mailable::ATTACHMENT_TEMPORARY_FILE], $attachment['name'], $editor->getId());
            } elseif (isset($attachment[Mailable::ATTACHMENT_SUBMISSION_FILE])) {
                $submissionFile = Repo::submissionFile()->get($attachment[Mailable::ATTACHMENT_SUBMISSION_FILE]);
                if (!$submissionFile || $submissionFile->getData('submissionId') !== $submission->getId()) {
                    throw new Exception('Could not find submission file ' . $attachment[Mailable::ATTACHMENT_SUBMISSION_FILE] . ' to attach to the query note.');
                }
                $newSubmissionFile = clone $submissionFile;
                $newSubmissionFile->setData('fileStage', SubmissionFile::SUBMISSION_FILE_QUERY);
                $newSubmissionFile->setData('sourceSubmissionFileId', $submissionFile->getId());
                $newSubmissionFile->setData('assocType', Application::ASSOC_TYPE_NOTE);
                $newSubmissionFile->setData('assocId', $note->id);
                Repo::submissionFile()->add($newSubmissionFile);
                $mailable->attachSubmissionFile($newSubmissionFile->getId(), $newSubmissionFile->getLocalizedData('name'));
            } elseif (isset($attachment[Mailable::ATTACHMENT_LIBRARY_FILE])) {
                /** @var \PKP\context\LibraryFileDAO $libraryFileDao */
                $libraryFileDao = DAORegistry::getDAO('LibraryFileDAO');
                /** @var \PKP\context\LibraryFile $file */
                $libraryFile = $libraryFileDao->getById($attachment[Mailable::ATTACHMENT_LIBRARY_FILE]);
                if (!$libraryFile) {
                    throw new Exception('Could not find library file ' . $attachment[Mailable::ATTACHMENT_LIBRARY_FILE] . ' to attach to the query note.');
                }
                $this->addSubmissionFileToNoteFromFilePath(
                    $libraryFile->getFilePath(),
                    $attachment['name'],
                    $note,
                    $editor,
                    $submission,
                    $context
                );
                $mailable->attachLibraryFile($attachment[Mailable::ATTACHMENT_LIBRARY_FILE], $attachment['name']);
            }
        }

        $this->sendEditorsEmail($mailable, $email, $editor, $queryParticipantIds);
    }

    /**
     * Helper function to save a file to the file system and then
     * use that in a new submission file attached to the query note
     */
    protected function addSubmissionFileToNoteFromFilePath(string $filepath, string $filename, Note $note, User $uploader, Submission $submission, Context $context)
    {
        $extension = pathinfo($filename, PATHINFO_EXTENSION);
        $submissionDir = Repo::submissionFile()->getSubmissionDir($context->getId(), $submission->getId());
        $fileId = app()->get('file')->add(
            $filepath,
            $submissionDir . '/' . uniqid() . '.' . $extension
        );
        $submissionFile = Repo::submissionFile()->newDataObject([
            'fileId' => $fileId,
            'name' => [
                Locale::getLocale() => $filename
            ],
            'fileStage' => SubmissionFile::SUBMISSION_FILE_QUERY,
            'submissionId' => $submission->getId(),
            'uploaderUserId' => $uploader->getId(),
            'assocType' => Application::ASSOC_TYPE_NOTE,
            'assocId' => $note->id,
        ]);
        Repo::submissionFile()->add($submissionFile);
    }

    /**
     * Sends email to editors with the recommendation
     */
    protected function sendEditorsEmail(Mailable $mailable, EmailData $email, User $editor, array $recipientIds)
    {
        $recipients = Repo::user()->getCollector()->filterByUserIds($recipientIds)->getMany();

        $mailable
            ->from($editor->getEmail(), $editor->getFullName())
            ->to($recipients->map(fn (User $recipient) => ['email' => $recipient->getEmail(), 'name' => $recipient->getFullName()])->toArray())
            ->cc($email->cc)
            ->bcc($email->bcc)
            ->subject($email->subject)
            ->body($email->body);

        Mail::send($mailable);
    }
}

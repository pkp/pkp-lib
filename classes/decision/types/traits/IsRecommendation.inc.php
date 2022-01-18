<?php
/**
 * @file classes/decision/types/traits/IsRecommendation.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class decision
 *
 * @brief Helper functions for decisions that are recommendations
 */

namespace PKP\decision\types\traits;

use APP\core\Application;
use APP\core\Services;
use APP\decision\Decision;
use APP\facades\Repo;
use APP\i18n\AppLocale;
use APP\submission\Submission;
use APP\submission\SubmissionFileDAO;
use Exception;
use Illuminate\Validation\Validator;
use PKP\context\Context;
use PKP\db\DAORegistry;
use PKP\decision\steps\Email;
use PKP\decision\Workflow;
use PKP\file\TemporaryFileManager;
use PKP\mail\EmailData;
use PKP\mail\Mailable;
use PKP\mail\mailables\RecommendationNotifyEditors;
use PKP\note\Note;
use PKP\query\QueryDAO;
use PKP\submission\reviewRound\ReviewRound;
use PKP\submissionFile\SubmissionFile;
use PKP\user\User;

trait IsRecommendation
{
    protected string $ACTION_DISCUSSION = 'discussion';

    /**
     * Validate the action to create a discussion with this recommendation
     */
    public function validate(array $props, Submission $submission, Context $context, Validator $validator, ?int $reviewRoundId = null)
    {
        foreach ($props['actions'] as $index => $action) {
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

    public function callback(Decision $decision, Submission $submission, User $editor, Context $context, array $actions)
    {
        parent::callback($decision, $submission, $editor, $context, $actions);

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

    public function getWorkflow(Submission $submission, Context $context, User $editor, ?ReviewRound $reviewRound): Workflow
    {
        $workflow = new Workflow($this, $submission, $context, $reviewRound);

        $fakeDecision = $this->getFakeDecision($submission, $editor);
        $fileAttachers = $this->getFileAttachers($submission, $context, $reviewRound);
        $editors = $workflow->getDecidingEditors();
        $reviewAssignments = $this->getCompletedReviewAssignments($submission->getId(), $reviewRound->getId());
        $mailable = new RecommendationNotifyEditors($context, $submission, $fakeDecision, $reviewAssignments);

        $workflow->addStep((new Email(
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

        return $workflow;
    }

    /**
     * Create a query (discussion) among deciding editors
     * and add attachments to the head note
     *
     * @return array<int>
     */
    protected function addRecommendationQuery(EmailData $email, Submission $submission, User $editor, Context $context)
    {
        /** @var QueryDAO $queryDao */
        $queryDao = DAORegistry::getDAO('QueryDAO');
        $queryId = $queryDao->addRecommendationQuery(
            $editor->getId(),
            $submission->getId(),
            $this->getStageId(),
            $email->subject,
            $email->body
        );

        $query = $queryDao->getById($queryId);
        $note = $query->getHeadNote();
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
            } elseif (isset($attachment[Mailable::ATTACHMENT_SUBMISSION_FILE])) {
                $submissionFile = Repo::submissionFile()->get($attachment[Mailable::ATTACHMENT_SUBMISSION_FILE]);
                if (!$submissionFile || $submissionFile->getData('submissionId') !== $submission->getId()) {
                    throw new Exception('Could not find submission file ' . $attachment[Mailable::ATTACHMENT_SUBMISSION_FILE] . ' to attach to the query note.');
                }
                $newSubmissionFile = clone $submissionFile;
                $newSubmissionFile->setData('fileStage', SubmissionFile::SUBMISSION_FILE_QUERY);
                $newSubmissionFile->setData('sourceSubmissionFileId', $submissionFile->getId());
                $newSubmissionFile->setData('assocType', Application::ASSOC_TYPE_NOTE);
                $newSubmissionFile->setData('assocId', $note->getId());
                Repo::submissionFile()->add($newSubmissionFile);
            } elseif (isset($attachment[Mailable::ATTACHMENT_LIBRARY_FILE])) {
                /** @var LibraryFileDAO $libraryFileDao */
                $libraryFileDao = DAORegistry::getDAO('LibraryFileDAO');
                /** @var LibraryFile $file */
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
            }
        }
    }

    /**
     * Helper function to save a file to the file system and then
     * use that in a new submission file attached to the query note
     */
    protected function addSubmissionFileToNoteFromFilePath(string $filepath, string $filename, Note $note, User $uploader, Submission $submission, Context $context)
    {
        $extension = pathinfo($filename, PATHINFO_EXTENSION);
        $submissionDir = Repo::submissionFile()->getSubmissionDir($context->getId(), $submission->getId());
        $fileId = Services::get('file')->add(
            $filepath,
            $submissionDir . '/' . uniqid() . '.' . $extension
        );
        /** @var SubmissionFileDAO $submissionFileDao */
        $submissionFileDao = DAORegistry::getDao('SubmissionFileDAO');
        $submissionFile = $submissionFileDao->newDataObject();
        $submissionFile->setAllData([
            'fileId' => $fileId,
            'name' => [
                AppLocale::getLocale() => $filename
            ],
            'fileStage' => SubmissionFile::SUBMISSION_FILE_QUERY,
            'submissionId' => $submission->getId(),
            'uploaderUserId' => $uploader->getId(),
            'assocType' => Application::ASSOC_TYPE_NOTE,
            'assocId' => $note->getId(),
        ]);
        Repo::submissionFile()->add($submissionFile);
    }
}

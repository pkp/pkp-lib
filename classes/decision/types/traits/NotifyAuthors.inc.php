<?php
/**
 * @file classes/decision/types/traits/NotifyAuthors.inc.php
 *
 * Copyright (c) 2014-2022 Simon Fraser University
 * Copyright (c) 2000-2022 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class decision
 *
 * @brief Helper functions for decisions that may request a payment
 */

namespace PKP\decision\types\traits;

use APP\core\Application;
use APP\facades\Repo;
use APP\submission\Submission;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Validator;
use PKP\context\Context;
use PKP\db\DAORegistry;
use PKP\log\SubmissionEmailLogDAO;
use PKP\log\SubmissionEmailLogEntry;
use PKP\mail\EmailData;
use PKP\mail\Mailable;
use PKP\mail\mailables\DecisionNotifyOtherAuthors;
use PKP\submissionFile\SubmissionFile;
use PKP\user\User;

trait NotifyAuthors
{
    protected string $ACTION_NOTIFY_AUTHORS = 'notifyAuthors';

    /** @copydoc DecisionType::getStageId() */
    abstract public function getStageId(): int;

    /** @copydoc DecisionType::addEmailDataToMailable() */
    abstract protected function addEmailDataToMailable(Mailable $mailable, User $user, EmailData $email): Mailable;

    /** @copydoc DecisionType::getAssignedAuthorIds() */
    abstract protected function getAssignedAuthorIds(Submission $submission): array;

    /**
     * Validate the decision action to notify authors
     */
    protected function validateNotifyAuthorsAction(array $action, string $actionErrorKey, Validator $validator, Submission $submission)
    {
        $errors = $this->validateEmailAction($action, $submission, $this->getAllowedAttachmentFileStages());
        foreach ($errors as $key => $propErrors) {
            foreach ($propErrors as $propError) {
                $validator->errors()->add($actionErrorKey . '.' . $key, $propError);
            }
        }
    }

    /**
     * Send the email to the author(s)
     */
    protected function sendAuthorEmail(Mailable $mailable, EmailData $email, User $editor, Submission $submission, Context $context)
    {
        $recipients = array_map(function ($userId) {
            return Repo::user()->get($userId);
        }, $this->getAssignedAuthorIds($submission));

        $mailable = $this->addEmailDataToMailable($mailable, $editor, $email);

        Mail::send($mailable->recipients($recipients, $email->locale));

        /** @var SubmissionEmailLogDAO $submissionEmailLogDao */
        $submissionEmailLogDao = DAORegistry::getDAO('SubmissionEmailLogDAO');
        $submissionEmailLogDao->logMailable(
            SubmissionEmailLogEntry::SUBMISSION_EMAIL_EDITOR_NOTIFY_AUTHOR,
            $mailable,
            $submission,
            $editor
        );

        if ($context->getData('notifyAllAuthors')) {
            $authors = $submission->getCurrentPublication()->getData('authors');
            $assignedAuthorEmails = array_map(function (User $user) {
                return $user->getEmail();
            }, $recipients);
            $mailable = new DecisionNotifyOtherAuthors($context, $submission);
            $emailTemplate = Repo::emailTemplate()->getByKey($context->getId(), $mailable::getEmailTemplateKey());
            $mailable
                ->sender($editor)
                ->subject($email->subject)
                ->body($emailTemplate->getLocalizedData('body'))
                ->addData([
                    $mailable::MESSAGE_TO_SUBMITTING_AUTHOR => $email->body,
                ]);
            foreach ($authors as $author) {
                if (!$author->getEmail() || in_array($author->getEmail(), $assignedAuthorEmails)) {
                    continue;
                }
                $mailable->to($author->getEmail(), $author->getFullName());
                Mail::send($mailable);
            }
        }
    }

    /**
     * Share reviewer file attachments with author
     *
     * This method looks in the email attachments for any files in the
     * SubmissionFile::SUBMISSION_FILE_REVIEW_ATTACHMENT stage and sets
     * their viewable flag to true. This flag makes the file visible to
     * the author from the author submission dashboard.
     */
    protected function shareReviewAttachmentFiles(array $attachments, Submission $submission, int $reviewRoundId)
    {
        if (!in_array($this->getStageId(), [WORKFLOW_STAGE_ID_INTERNAL_REVIEW, WORKFLOW_STAGE_ID_EXTERNAL_REVIEW])) {
            return;
        }

        $submissionFileIds = [];
        foreach ($attachments as $attachment) {
            if (!isset($attachment['submissionFileId'])) {
                continue;
            }
            $submissionFileIds[] = (int) $attachment['submissionFileId'];
        }

        if (empty($submissionFileIds)) {
            return;
        }

        $reviewAttachmentIds = Repo::submissionFile()->getIds(
            Repo::submissionFile()
                ->getCollector()
                ->filterBySubmissionIds([$submission->getId()])
                ->filterByReviewRoundIds([$reviewRoundId])
                ->filterByFileStages([SubmissionFile::SUBMISSION_FILE_REVIEW_ATTACHMENT])
        );

        foreach ($reviewAttachmentIds->intersect($submissionFileIds) as $sharedFileId) {
            $submissionFile = Repo::submissionFile()->get($sharedFileId);
            Repo::submissionFile()->edit(
                $submissionFile,
                ['viewable' => true],
                Application::get()->getRequest()
            );
        }
    }
}

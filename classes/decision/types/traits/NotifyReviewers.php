<?php
/**
 * @file classes/decision/types/traits/NotifyReviewers.php
 *
 * Copyright (c) 2014-2022 Simon Fraser University
 * Copyright (c) 2000-2022 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class decision
 *
 * @brief Helper functions for decisions that send a notification to reviewers.
 */

namespace PKP\decision\types\traits;

use APP\core\Application;
use APP\facades\Repo;
use APP\log\event\SubmissionEventLogEntry;
use APP\submission\Submission;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Validator;
use PKP\core\Core;
use PKP\core\PKPApplication;
use PKP\db\DAORegistry;
use PKP\log\event\PKPSubmissionEventLogEntry;
use PKP\log\SubmissionLog;
use PKP\mail\EmailData;
use PKP\mail\mailables\DecisionNotifyReviewer;
use PKP\mail\mailables\ReviewerUnassign;
use PKP\security\Validation;
use PKP\submission\reviewAssignment\ReviewAssignment;
use PKP\submission\reviewAssignment\ReviewAssignmentDAO;
use PKP\user\User;

trait NotifyReviewers
{
    protected string $ACTION_NOTIFY_REVIEWERS = 'notifyReviewers';

    /**
     * Send the email to the reviewers
     */
    protected function sendReviewersEmail(DecisionNotifyReviewer|ReviewerUnassign $mailable, EmailData $email, User $editor, Submission $submission)
    {
        /** @var DecisionNotifyReviewer $mailable */
        $mailable = $this->addEmailDataToMailable($mailable, $editor, $email);

        /** @var User[] $recipients */
        $recipients = array_map(function ($userId) {
            return Repo::user()->get($userId);
        }, $email->recipients);

        foreach ($recipients as $recipient) {
            Mail::send($mailable->recipients([$recipient], $email->locale));

            // Update the ReviewAssignment to indicate the reviewer has been acknowledged
            if (is_a($mailable, DecisionNotifyReviewer::class)) {
                /** @var ReviewAssignmentDAO $reviewAssignmentDao */
                $reviewAssignmentDao = DAORegistry::getDAO('ReviewAssignmentDAO');
                $reviewAssignment = $reviewAssignmentDao->getReviewAssignment($mailable->getDecision()->getData('reviewRoundId'), $recipient->getId());
                if ($reviewAssignment) {
                    $reviewAssignment->setDateAcknowledged(Core::getCurrentDate());
                    $reviewAssignment->stampModified();
                    $reviewAssignmentDao->updateObject($reviewAssignment);
                }
            }
        }

        $eventLog = Repo::eventLog()->newDataObject([
            'assocType' => PKPApplication::ASSOC_TYPE_SUBMISSION,
            'assocId' => $submission->getId(),
            'eventType' => PKPSubmissionEventLogEntry::SUBMISSION_LOG_DECISION_EMAIL_SENT,
            'userId' => Validation::loggedInAs() ?? Application::get()->getRequest()->getUser()?->getId(),
            'message' => 'submission.event.decisionReviewerEmailSent',
            'isTranslated' => 0,
            'dateLogged' => Core::getCurrentDate(),
            'recipientCount' => count($recipients),
            'subject' => $email->subject,
        ]);
        Repo::eventLog()->add($eventLog);
    }

    /**
     * Validate the decision action to notify reviewers
     */
    protected function validateNotifyReviewersAction(array $action, string $actionErrorKey, Validator $validator, Submission $submission, int $reviewRoundId, string $reviewAssignmentStatus)
    {
        $errors = $this->validateEmailAction($action, $submission, $this->getAllowedAttachmentFileStages());

        foreach ($errors as $key => $propErrors) {
            foreach ($propErrors as $propError) {
                $validator->errors()->add($actionErrorKey . '.' . $key, $propError);
            }
        }

        if (empty($action['recipients'])) {
            $validator->errors()->add($actionErrorKey . '.recipients', __('validator.required'));
            return;
        }

        $reviewerIds = $this->getReviewerIds($submission->getId(), $reviewRoundId, $reviewAssignmentStatus);
        $invalidRecipients = array_diff($action['recipients'], $reviewerIds);

        if (count($invalidRecipients)) {
            $this->setRecipientError($actionErrorKey, $invalidRecipients, $validator);
        }
    }
}

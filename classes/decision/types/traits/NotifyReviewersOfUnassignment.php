<?php

/**
 * @file classes/decision/types/traits/NotifyReviewersOfUnassignment.inc.php
 *
 * Copyright (c) 2014-2022 Simon Fraser University
 * Copyright (c) 2000-2022 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class decision
 *
 * @brief Helper functions for decisions that notify reviewers of the unassignment of review assignment because of a decision
 */

namespace PKP\decision\types\traits;

use APP\core\Application;
use APP\facades\Repo;
use APP\log\SubmissionEventLogEntry;
use APP\submission\Submission;
use Illuminate\Support\Facades\Mail;
use PKP\log\SubmissionLog;
use PKP\mail\EmailData;
use PKP\mail\mailables\ReviewerUnassign;
use PKP\user\User;

trait NotifyReviewersOfUnassignment
{
    use ToNotifyReviewers;

    /**
     * Send the email to the reviewers
     */
    protected function sendReviewersEmail(ReviewerUnassign $mailable, EmailData $email, User $editor, Submission $submission)
    {
        /** @var ReviewerUnassign $mailable */
        $mailable = $this->addEmailDataToMailable($mailable, $editor, $email);

        /** @var User[] $recipients */
        $recipients = array_map(function ($userId) {
            return Repo::user()->get($userId);
        }, $email->recipients);

        foreach ($recipients as $recipient) {
            Mail::send($mailable->recipients([$recipient], $email->locale));
        }

        SubmissionLog::logEvent(
            Application::get()->getRequest(),
            $submission,
            SubmissionEventLogEntry::SUBMISSION_LOG_DECISION_EMAIL_SENT,
            'submission.event.decisionReviewerEmailSent',
            [
                'recipientCount' => count($recipients),
                'subject' => $email->subject,
            ]
        );
    }
}

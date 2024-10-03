<?php

/**
 * @file jobs/email/ReviewReminder.php
 *
 * Copyright (c) 2014-2024 Simon Fraser University
 * Copyright (c) 2000-2024 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class ReviewReminder
 *
 * @ingroup jobs
 *
 * @brief Class to handle a job to send an review reminder
 */

namespace PKP\jobs\email;

use APP\facades\Repo;
use Illuminate\Support\Facades\Mail;
use PKP\log\event\PKPSubmissionEventLogEntry;
use PKP\core\PKPApplication;
use PKP\core\Core;
use PKP\invitation\invitations\reviewerAccess\ReviewerAccessInvite;
use PKP\log\SubmissionEmailLogEventType;
use PKP\mail\mailables\ReviewResponseRemindAuto;
use PKP\mail\mailables\ReviewRemindAuto;
use PKP\jobs\BaseJob;

class ReviewReminder extends BaseJob
{
    public function __construct(
        public int $contextId,
        public int $reviewAssignmentId,
        public string $mailableClass
    )
    {
        parent::__construct();
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $reviewAssignment = Repo::reviewAssignment()->get($this->reviewAssignmentId);
        $reviewer = Repo::user()->get($reviewAssignment->getReviewerId());

        if (!isset($reviewer)) {
            return;
        }

        $submission = Repo::submission()->get($reviewAssignment->getData('submissionId'));

        $contextService = app()->get('context');
        $context = $contextService->get($this->contextId);

        /** @var ReviewRemindAuto|ReviewResponseRemindAuto $mailable */
        $mailable = new $this->mailableClass($context, $submission, $reviewAssignment);

        $primaryLocale = $context->getPrimaryLocale();
        $emailTemplate = Repo::emailTemplate()->getByKey(
            $context->getId(),
            $mailable::getEmailTemplateKey()
        );

        $mailable->subject($emailTemplate->getLocalizedData('subject', $primaryLocale))
            ->body($emailTemplate->getLocalizedData('body', $primaryLocale))
            ->from($context->getData('contactEmail'), $context->getData('contactName'))
            ->recipients([$reviewer]);

        $mailable->setData($primaryLocale);

        $reviewerAccessKeysEnabled = $context->getData('reviewerAccessKeysEnabled');

        if ($reviewerAccessKeysEnabled) { // Give one-click access if enabled
            $reviewInvitation = new ReviewerAccessInvite();
            $reviewInvitation->initialize($reviewAssignment->getReviewerId(), $context->getId(), null);

            $reviewInvitation->reviewAssignmentId = $reviewAssignment->getId();
            $reviewInvitation->updatePayload();

            $reviewInvitation->invite();
            $reviewInvitation->updateMailableWithUrl($mailable);
        }

        // deprecated template variables OJS 2.x
        $mailable->addData([
            'messageToReviewer' => __('reviewer.step1.requestBoilerplate'),
            'abstractTermIfEnabled' => ($submission->getCurrentPublication()->getLocalizedData('abstract') == '' ? '' : __('common.abstract')),
        ]);

        Mail::send($mailable);

        Repo::reviewAssignment()->edit($reviewAssignment, [
            'dateReminded' => Core::getCurrentDate(),
            'reminderWasAutomatic' => 1
        ]);

        Repo::emailLogEntry()->logMailable(SubmissionEmailLogEventType::REVIEW_REMIND_AUTO, $mailable, $submission);

        $eventLog = Repo::eventLog()->newDataObject([
            'assocType' => PKPApplication::ASSOC_TYPE_SUBMISSION,
            'assocId' => $submission->getId(),
            'eventType' => PKPSubmissionEventLogEntry::SUBMISSION_LOG_REVIEW_REMIND_AUTO,
            'userId' => null,
            'message' => 'submission.event.reviewer.reviewerRemindedAuto',
            'isTranslated' => false,
            'dateLogged' => Core::getCurrentDate(),
            'recipientId' => $reviewer->getId(),
            'recipientName' => $reviewer->getFullName(),
        ]);
        Repo::eventLog()->add($eventLog);
    }
}

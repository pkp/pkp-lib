<?php

/**
 * @file classes/task/ReviewReminder.php
 *
 * Copyright (c) 2013-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class ReviewReminder
 *
 * @ingroup tasks
 *
 * @brief Class to perform automated reminders for reviewers.
 */

namespace PKP\task;

use APP\core\Application;
use APP\facades\Repo;
use Illuminate\Support\Facades\Mail;
use PKP\context\Context;
use PKP\core\Core;
use PKP\core\PKPApplication;
use PKP\invitation\invitations\ReviewerAccessInvite;
use PKP\log\event\PKPSubmissionEventLogEntry;
use PKP\mail\mailables\ReviewRemindAuto;
use PKP\mail\mailables\ReviewResponseRemindAuto;
use PKP\scheduledTask\ScheduledTask;
use PKP\submission\PKPSubmission;
use PKP\submission\reviewAssignment\ReviewAssignment;

class ReviewReminder extends ScheduledTask
{
    /**
     * @copydoc ScheduledTask::getName()
     */
    public function getName()
    {
        return __('admin.scheduledTask.reviewReminder');
    }

    /**
     * Send the automatic review reminder to the reviewer.
     */
    public function sendReminder(
        ReviewAssignment $reviewAssignment,
        PKPSubmission $submission,
        Context $context,
        ReviewRemindAuto|ReviewResponseRemindAuto $mailable
    ): void {

        $reviewer = Repo::user()->get($reviewAssignment->getReviewerId());
        if (!isset($reviewer)) {
            return;
        }

        $primaryLocale = $context->getPrimaryLocale();
        $emailTemplate = Repo::emailTemplate()->getByKey($context->getId(), $mailable::getEmailTemplateKey());
        $mailable->subject($emailTemplate->getLocalizedData('subject', $primaryLocale))
            ->body($emailTemplate->getLocalizedData('body', $primaryLocale))
            ->from($context->getData('contactEmail'), $context->getData('contactName'))
            ->recipients([$reviewer]);

        $mailable->setData($primaryLocale);

        $reviewerAccessKeysEnabled = $context->getData('reviewerAccessKeysEnabled');
        if ($reviewerAccessKeysEnabled) { // Give one-click access if enabled
            $reviewInvitation = new ReviewerAccessInvite(
                $reviewAssignment->getReviewerId(),
                $context->getId(),
                $reviewAssignment->getId()
            );
            $reviewInvitation->setMailable($mailable);
            $reviewInvitation->dispatch();
        }

        if ($mailable instanceof ReviewRemindAuto) {
            $occurrence = $reviewAssignment->getCountSubmitReminder() + 1;
        }
        else {
            $occurrence = $reviewAssignment->getCountInviteReminder() + 1;
        }

        // deprecated template variables OJS 2.x
        $mailable->addData([
            'messageToReviewer' => __('reviewer.step1.requestBoilerplate'),
            'abstractTermIfEnabled' => ($submission->getLocalizedAbstract() == '' ? '' : __('common.abstract')),
            'occurrence' => $occurrence,
        ]);

        Mail::send($mailable);

        if ($mailable instanceof ReviewRemindAuto) {
            $dateFieldToUpdate = 'dateSubmitReminded';
            $countFieldToUpdate = 'countSubmitReminder';
        }
        else {
            $dateFieldToUpdate = 'dateInviteReminded';
            $countFieldToUpdate = 'countInviteReminder';
        }
        Repo::reviewAssignment()->edit($reviewAssignment, [
            $dateFieldToUpdate => Core::getCurrentDate(),
            $countFieldToUpdate => $occurrence,
            'reminderWasAutomatic' => 1
        ]);

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

    /**
     * @copydoc ScheduledTask::executeActions()
     */
    public function executeActions()
    {
        $submission = null;
        $context = null;

        $contextDao = Application::getContextDAO();

        $incompleteAssignments = Repo::reviewAssignment()->getCollector()->filterByIsIncomplete(true)->getMany();
        $inviteReminderDays = $submitReminderDays = null;
        $occurrencesInviteReminder = $occurrencesSubmitReminder = null;
        foreach ($incompleteAssignments as $reviewAssignment) {
            // Fetch the submission
            if ($submission == null || $submission->getId() != $reviewAssignment->getSubmissionId()) {
                unset($submission);
                $submission = Repo::submission()->get($reviewAssignment->getSubmissionId());
                // Avoid review assignments without submission in database.
                if (!$submission) {
                    continue;
                }
            }

            if ($submission->getStatus() != PKPSubmission::STATUS_QUEUED) {
                continue;
            }

            // Fetch the context
            if ($context == null || $context->getId() != $submission->getContextId()) {
                unset($context);
                $context = $contextDao->getById($submission->getContextId());

                $inviteReminderDays = $context->getData('numDaysBeforeInviteReminder');
                $submitReminderDays = $context->getData('numDaysBeforeSubmitReminder');
                $occurrencesInviteReminder = $context->getData('numOccurrencesForInviteReminder');
                $occurrencesSubmitReminder = $context->getData('numOccurrencesForSubmitReminder');
            }

            $mailable = null;

            $countSubmitReminder = $reviewAssignment->getCountSubmitReminder();
            if ($countSubmitReminder == 0 || !$occurrencesSubmitReminder || $countSubmitReminder < $occurrencesSubmitReminder) {
                $dateDue = $reviewAssignment->getDateDue();
                if ($submitReminderDays >= 1 && $dateDue) {
                    $dateSubmitReminded = $reviewAssignment->getDateSubmitReminded();
                    $time = $dateSubmitReminded ? strtotime($dateSubmitReminded) : strtotime($dateDue);
                    $checkDate = $time + (60 * 60 * 24 * $submitReminderDays);
                    if (time() > $checkDate) {
                        $mailable = new ReviewRemindAuto($context, $submission, $reviewAssignment);
                    }
                }
            }

            $countInviteReminder = $reviewAssignment->getCountInviteReminder();
            if ($countInviteReminder == 0 || !$occurrencesInviteReminder || $countInviteReminder < $occurrencesInviteReminder) {
                $dateConfirmed = $reviewAssignment->getDateConfirmed();
                if ($inviteReminderDays >= 1 && !$dateConfirmed) {
                    $dateResponseDue = $reviewAssignment->getDateResponseDue();
                    $dateInviteReminded = $reviewAssignment->getDateInviteReminded();
                    $time = $dateInviteReminded ? strtotime($dateInviteReminded) : strtotime($dateResponseDue);
                    $checkDate = $time + (60 * 60 * 24 * $inviteReminderDays);
                    if (time() > $checkDate) {
                        $mailable = new ReviewResponseRemindAuto($context, $submission, $reviewAssignment);
                    }
                }
            }

            if ($mailable) {
                $this->sendReminder($reviewAssignment, $submission, $context, $mailable);
            }
        }

        return true;
    }
}

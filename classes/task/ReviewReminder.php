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
use PKP\db\DAORegistry;
use PKP\log\event\PKPSubmissionEventLogEntry;
use PKP\mail\mailables\ReviewRemindAuto;
use PKP\mail\mailables\ReviewResponseRemindAuto;
use PKP\scheduledTask\ScheduledTask;
use PKP\security\AccessKeyManager;
use PKP\submission\PKPSubmission;
use PKP\submission\reviewAssignment\ReviewAssignment;
use PKP\submission\reviewAssignment\ReviewAssignmentDAO;

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
        $reviewAssignmentDao = DAORegistry::getDAO('ReviewAssignmentDAO'); /** @var ReviewAssignmentDAO $reviewAssignmentDao */
        $reviewId = $reviewAssignment->getId();

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

        $application = Application::get();
        $request = $application->getRequest();
        $dispatcher = $application->getDispatcher();
        $reviewerAccessKeysEnabled = $context->getData('reviewerAccessKeysEnabled');
        if ($reviewerAccessKeysEnabled) { // Give one-click access if enabled
            $accessKeyManager = new AccessKeyManager();

            // Key lifetime is the typical review period plus four weeks
            $keyLifetime = ($context->getData('numWeeksPerReview') + 4) * 7;
            $accessKey = $accessKeyManager->createKey($context->getId(), $reviewer->getId(), $reviewId, $keyLifetime);

            $reviewUrlArgs = ['submissionId' => $reviewAssignment->getSubmissionId(), 'reviewId' => $reviewId, 'key' => $accessKey];
            $submissionReviewUrl = $dispatcher->url($request, PKPApplication::ROUTE_PAGE, $context->getPath(), 'reviewer', 'submission', null, $reviewUrlArgs);
            $mailable->addData(['submissionReviewUrl' => $submissionReviewUrl]);
        }

        // deprecated template variables OJS 2.x
        $mailable->addData([
            'messageToReviewer' => __('reviewer.step1.requestBoilerplate'),
            'abstractTermIfEnabled' => ($submission->getLocalizedAbstract() == '' ? '' : __('common.abstract')),
        ]);

        Mail::send($mailable);

        $reviewAssignment->setDateReminded(Core::getCurrentDate());
        $reviewAssignment->setReminderWasAutomatic(1);
        $reviewAssignmentDao->updateObject($reviewAssignment);

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

        $reviewAssignmentDao = DAORegistry::getDAO('ReviewAssignmentDAO'); /** @var ReviewAssignmentDAO $reviewAssignmentDao */
        $contextDao = Application::getContextDAO();

        $incompleteAssignments = $reviewAssignmentDao->getIncompleteReviewAssignments();
        $inviteReminderDays = $submitReminderDays = null;
        foreach ($incompleteAssignments as $reviewAssignment) {
            // Avoid review assignments that a reminder exists for.
            if ($reviewAssignment->getDateReminded() !== null) {
                continue;
            }

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
            }

            $mailable = null;
            if ($submitReminderDays >= 1 && $reviewAssignment->getDateDue() != null) {
                $checkDate = strtotime($reviewAssignment->getDateDue());
                if (time() - $checkDate > 60 * 60 * 24 * $submitReminderDays) {
                    $mailable = new ReviewRemindAuto($context, $submission, $reviewAssignment);
                }
            }
            if ($inviteReminderDays >= 1 && $reviewAssignment->getDateConfirmed() == null) {
                $checkDate = strtotime($reviewAssignment->getDateResponseDue());
                if (time() - $checkDate > 60 * 60 * 24 * $inviteReminderDays) {
                    $mailable = new ReviewResponseRemindAuto($context, $submission, $reviewAssignment);
                }
            }

            if ($mailable) {
                $this->sendReminder($reviewAssignment, $submission, $context, $mailable);
            }
        }

        return true;
    }
}

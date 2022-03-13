<?php

/**
 * @file classes/task/ReviewReminder.inc.php
 *
 * Copyright (c) 2013-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class ReviewReminder
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
use PKP\core\PKPString;
use PKP\db\DAORegistry;
use PKP\mail\Mailable;
use PKP\mail\mailables\ReviewRemindAuto;
use PKP\mail\mailables\ReviewResponseRemindAuto;
use PKP\scheduledTask\ScheduledTask;
use PKP\security\AccessKeyManager;
use PKP\security\Validation;
use PKP\submission\PKPSubmission;
use PKP\submission\reviewAssignment\ReviewAssignment;
use PKP\submission\reviewAssignment\ReviewAssignmentDAO;
use Exception;

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
     * @return bool|void
     */
    public function sendReminder(
        ReviewAssignment $reviewAssignment,
        PKPSubmission $submission,
        Context $context,
        string $reminderType = 'REVIEW_RESPONSE_OVERDUE_AUTO' // REVIEW_RESPONSE_OVERDUE_AUTO or REVIEW_REMIND_AUTO
    )
    {
        $reviewAssignmentDao = DAORegistry::getDAO('ReviewAssignmentDAO'); /** @var ReviewAssignmentDAO $reviewAssignmentDao */
        $reviewId = $reviewAssignment->getId();

        $reviewer = Repo::user()->get($reviewAssignment->getReviewerId());
        if (!isset($reviewer)) {
            return false;
        }

        $mailable = $this->getMailable($reminderType, $reviewAssignment, $submission, $context);

        $primaryLocale = $context->getPrimaryLocale();
        $emailTemplate = $mailable->getTemplate($context->getId());
        $mailable->subject($emailTemplate->getData('subject', $primaryLocale))
            ->body($emailTemplate->getData('body', $primaryLocale))
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

        $mailable->addData([
            // deprecated from OJS 3.3
            'passwordResetUrl' => $dispatcher->url(
                $request,
                PKPApplication::ROUTE_PAGE,
                $context->getPath(),
                'login',
                'resetPassword',
                $reviewer->getUsername(),
                ['confirm' => Validation::generatePasswordResetHash($reviewer->getId())]
            ),

            // deprecated from OJS 2.x
            'messageToReviewer' => __('reviewer.step1.requestBoilerplate'),
            'abstractTermIfEnabled' => ($submission->getLocalizedAbstract() == '' ? '' : __('common.abstract')),
        ]);

        Mail::send($mailable);

        $reviewAssignment->setDateReminded(Core::getCurrentDate());
        $reviewAssignment->setReminderWasAutomatic(1);
        $reviewAssignmentDao->updateObject($reviewAssignment);
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

            $reminderType = false;
            if ($submitReminderDays >= 1 && $reviewAssignment->getDateDue() != null) {
                $checkDate = strtotime($reviewAssignment->getDateDue());
                if (time() - $checkDate > 60 * 60 * 24 * $submitReminderDays) {
                    $reminderType = 'REVIEW_REMIND_AUTO';
                }
            }
            if ($inviteReminderDays >= 1 && $reviewAssignment->getDateConfirmed() == null) {
                $checkDate = strtotime($reviewAssignment->getDateResponseDue());
                if (time() - $checkDate > 60 * 60 * 24 * $inviteReminderDays) {
                    $reminderType = 'REVIEW_RESPONSE_OVERDUE_AUTO';
                }
            }

            if ($reminderType) {
                $this->sendReminder($reviewAssignment, $submission, $context, $reminderType);
            }
        }

        return true;
    }

    /**
     * Initialize correct Mailable based on a reminder type and one-click review access setting
     * @throws Exception
     */
    protected function getMailable(
        string $reminderType,
        ReviewAssignment $reviewAssignment,
        PKPSubmission $submission,
        Context $context
    ): Mailable
    {
        if ($reminderType === ReviewRemindAuto::EMAIL_KEY) {
            return new ReviewRemindAuto($reviewAssignment, $submission, $context);
        } else if ($reminderType === ReviewResponseRemindAuto::EMAIL_KEY) {
            return new ReviewResponseRemindAuto($reviewAssignment, $submission, $context);
        }

        throw new Exception("Unsupported reminder type: " . $reminderType);
    }
}

if (!PKP_STRICT_MODE) {
    class_alias('\PKP\task\ReviewReminder', '\ReviewReminder');
}

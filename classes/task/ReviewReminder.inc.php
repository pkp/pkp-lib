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

use PKP\core\Core;
use PKP\core\PKPApplication;
use PKP\db\DAORegistry;
use PKP\mail\SubmissionMailTemplate;
use PKP\scheduledTask\ScheduledTask;
use PKP\security\AccessKeyManager;
use PKP\security\Validation;
use PKP\submission\PKPSubmission;

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
     *
     * @param \PKP\submission\reviewAssignment\ReviewAssignment $reviewAssignment
     * @param Submission $submission
     * @param Context $context
     * @param string $reminderType
     * 	REVIEW_REMIND_AUTO, REVIEW_RESPONSE_OVERDUE_AUTO
     */
    public function sendReminder($reviewAssignment, $submission, $context, $reminderType = 'REVIEW_RESPONSE_OVERDUE_AUTO')
    {
        $reviewAssignmentDao = DAORegistry::getDAO('ReviewAssignmentDAO'); /** @var ReviewAssignmentDAO $reviewAssignmentDao */
        $reviewId = $reviewAssignment->getId();

        $reviewer = Repo::user()->get($reviewAssignment->getReviewerId());
        if (!isset($reviewer)) {
            return false;
        }

        $emailKey = $reminderType;
        $reviewerAccessKeysEnabled = $context->getData('reviewerAccessKeysEnabled');
        switch (true) {
            case $reviewerAccessKeysEnabled && ($reminderType == 'REVIEW_REMIND_AUTO'):
                $emailKey = 'REVIEW_REMIND_AUTO_ONECLICK';
                break;
            case $reviewerAccessKeysEnabled && ($reminderType == 'REVIEW_RESPONSE_OVERDUE_AUTO'):
                $emailKey = 'REVIEW_RESPONSE_OVERDUE_AUTO_ONECLICK';
                break;
        }
        $email = new SubmissionMailTemplate($submission, $emailKey, $context->getPrimaryLocale(), $context, false);
        $email->setContext($context);
        $email->setReplyTo(null);
        $email->addRecipient($reviewer->getEmail(), $reviewer->getFullName());
        $email->setSubject($email->getSubject($context->getPrimaryLocale()));
        $email->setBody($email->getBody($context->getPrimaryLocale()));
        $email->setFrom($context->getData('contactEmail'), $context->getData('contactName'));

        $reviewUrlArgs = ['submissionId' => $reviewAssignment->getSubmissionId()];
        if ($reviewerAccessKeysEnabled) {
            $accessKeyManager = new AccessKeyManager();

            // Key lifetime is the typical review period plus four weeks
            $keyLifetime = ($context->getData('numWeeksPerReview') + 4) * 7;
            $accessKey = $accessKeyManager->createKey($context->getId(), $reviewer->getId(), $reviewId, $keyLifetime);
            $reviewUrlArgs = array_merge($reviewUrlArgs, ['reviewId' => $reviewId, 'key' => $accessKey]);
        }

        $application = Application::get();
        $request = $application->getRequest();
        $dispatcher = $application->getDispatcher();
        $submissionReviewUrl = $dispatcher->url($request, PKPApplication::ROUTE_PAGE, $context->getPath(), 'reviewer', 'submission', null, $reviewUrlArgs);

        // Format the review due date
        $reviewDueDate = strtotime($reviewAssignment->getDateDue());
        $dateFormatShort = $context->getLocalizedDateFormatShort();
        if ($reviewDueDate === -1 || $reviewDueDate === false) {
            // Default to something human-readable if no date specified
            $reviewDueDate = '_____';
        } else {
            $reviewDueDate = strftime($dateFormatShort, $reviewDueDate);
        }
        // Format the review response due date
        $responseDueDate = strtotime($reviewAssignment->getDateResponseDue());
        if ($responseDueDate === -1 || $responseDueDate === false) {
            // Default to something human-readable if no date specified
            $responseDueDate = '_____';
        } else {
            $responseDueDate = strftime($dateFormatShort, $responseDueDate);
        }
        $paramArray = [
            'recipientName' => $reviewer->getFullName(),
            'recipientUsername' => $reviewer->getUsername(),
            'reviewDueDate' => $reviewDueDate,
            'responseDueDate' => $responseDueDate,
            'signature' => $context->getData('contactName') . "\n" . $context->getLocalizedName(),
            'passwordResetUrl' => $dispatcher->url($request, PKPApplication::ROUTE_PAGE, $context->getPath(), 'login', 'resetPassword', $reviewer->getUsername(), ['confirm' => Validation::generatePasswordResetHash($reviewer->getId())]),
            'reviewAssignmentUrl' => $submissionReviewUrl,
            'messageToReviewer' => __('reviewer.step1.requestBoilerplate'),
            'abstractTermIfEnabled' => ($submission->getLocalizedAbstract() == '' ? '' : __('common.abstract')),
        ];
        $email->assignParams($paramArray);

        $email->send();

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
}

if (!PKP_STRICT_MODE) {
    class_alias('\PKP\task\ReviewReminder', '\ReviewReminder');
}

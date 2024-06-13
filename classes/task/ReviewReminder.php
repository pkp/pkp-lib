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
use Carbon\Carbon;
use PKP\mail\mailables\ReviewRemindAuto;
use PKP\mail\mailables\ReviewResponseRemindAuto;
use PKP\scheduledTask\ScheduledTask;
use PKP\submission\PKPSubmission;
use PKP\jobs\email\ReviewReminder as ReviewReminderJob;

class ReviewReminder extends ScheduledTask
{
    /**
     * @copydoc ScheduledTask::getName()
     */
    public function getName(): string
    {
        return __('admin.scheduledTask.reviewReminder');
    }

    /**
     * @copydoc ScheduledTask::executeActions()
     */
    public function executeActions(): bool
    {
        $submission = null;
        $context = null;

        $contextDao = Application::getContextDAO();
        $incompleteAssignments = Repo::reviewAssignment()
            ->getCollector()
            ->filterByIsIncomplete(true)
            ->orderByContextId()
            ->orderBySubmissionId()
            ->getMany();

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

            if ($submission->getData('status') != PKPSubmission::STATUS_QUEUED) {
                continue;
            }

            // Fetch the context
            if ($context == null || $context->getId() != $submission->getData('contextId')) {
                unset($context);
                $context = $contextDao->getById($submission->getData('contextId'));

                $numDaysBeforeReviewResponseReminderDue = (int) $context->getData('numDaysBeforeReviewResponseReminderDue');
                $numDaysAfterReviewResponseReminderDue  = (int) $context->getData('numDaysAfterReviewResponseReminderDue');
                $numDaysBeforeReviewSubmitReminderDue   = (int) $context->getData('numDaysBeforeReviewSubmitReminderDue');
                $numDaysAfterReviewSubmitReminderDue    = (int) $context->getData('numDaysAfterReviewSubmitReminderDue');
            }

            $mailable = null;
            $currentDate = Carbon::today();

            $dateResponseDue = Carbon::parse($reviewAssignment->getDateResponseDue());
            $dateDue = Carbon::parse($reviewAssignment->getDateDue());

            // after a REVIEW REQUEST has been responded, the value of `dateReminded` and `reminderWasAutomatic`
            // get reset, see \PKP\submission\reviewer\ReviewerAction::confirmReview. 
            if ($reviewAssignment->getDateConfirmed() === null) {
                // REVIEW REQUEST has not been responded
                // only need to concern with BEFORE/AFTER REVIEW REQUEST RESPONSE reminder
                
                if ($reviewAssignment->getDateReminded() === null) {
                    // There has not been any reminder sent yet
                    // need to check should we sent a BEFORE REVIEW REQUEST RESPONSE reminder
                    if ($numDaysBeforeReviewResponseReminderDue > 0 &&
                        $dateResponseDue->gt($currentDate) &&
                        $dateResponseDue->diffInDays($currentDate) <= $numDaysBeforeReviewResponseReminderDue) {
                    
                        // ACTION:-> we need to send BEFORE REVIEW REQUEST RESPONSE reminder
                        $mailable = ReviewResponseRemindAuto::class;
                    }
                } else {
                    // There has been a reminder already sent
                    // need to check should we sent a AFTER REVIEW REQUEST RESPONSE reminder

                    $dateReminded = Carbon::parse($reviewAssignment->getDateReminded());

                    if ($numDaysAfterReviewResponseReminderDue > 0 &&
                        $currentDate->gt($dateResponseDue) &&
                        $dateReminded->lt($dateResponseDue) &&
                        $currentDate->diffInDays($dateResponseDue) >= $numDaysAfterReviewResponseReminderDue) {
                    
                        // ACTION:-> we need to send AFTER REVIEW REQUEST RESPONSE reminder
                        $mailable = ReviewResponseRemindAuto::class;
                    }
                }
            } else {
                // REVIEW REQUEST has been responded
                // only need to concern with BEFORE/AFTER REVIEW SUBMIT reminder

                if ($reviewAssignment->getDateReminded() === null) {
                    // There has not been any reminder sent after responding to REVIEW REQUEST
                    // no REVIEW SUBMIT reminder has been sent
                    if ($numDaysBeforeReviewSubmitReminderDue > 0 &&
                        $currentDate->lt($dateDue) &&
                        $dateDue->diffInDays($currentDate) <= $numDaysBeforeReviewSubmitReminderDue) {

                        // ACTION:-> we need to sent a BEFORE REVIEW SUBMIT reminder
                        $mailable = ReviewRemindAuto::class;
                    }
                } else {
                    // There has been already sent a reminder after responding to REVIEW REQUEST
                    // need to check should we sent a AFTER REVIEW SUBMIT reminder

                    $dateReminded = Carbon::parse($reviewAssignment->getDateReminded());

                    if ($numDaysAfterReviewSubmitReminderDue > 0 &&
                        $currentDate->gt($dateDue) &&
                        $dateReminded->lt($dateDue) &&
                        $currentDate->diffInDays($dateDue) >= $numDaysAfterReviewSubmitReminderDue) {
                    
                        // ACTION:-> we need to send AFTER REVIEW SUBMIT reminder
                        $mailable = ReviewRemindAuto::class;
                    }
                }
            }

            if ($mailable) {
                ReviewReminderJob::dispatch($context->getId(), $reviewAssignment->getId(), $mailable);
            }
        }

        return true;
    }
}

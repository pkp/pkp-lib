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
use PKP\jobs\email\ReviewRemainder as ReviewRemainderJob;

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

                $numDaysBeforeReviewResponseReminderDue = $context->getData('numDaysBeforeReviewResponseReminderDue');
                $numDaysAfterReviewResponseReminderDue = $context->getData('numDaysAfterReviewResponseReminderDue');

                $numDaysBeforeReviewSubmitReminderDue = $context->getData('numDaysBeforeReviewSubmitReminderDue');
                $numDaysAfterReviewSubmitReminderDue = $context->getData('numDaysAfterReviewSubmitReminderDue');
            }

            $mailable = null;
            $currentDate = Carbon::today();

            $dateResponseDue = Carbon::parse($reviewAssignment->getDateResponseDue())->startOfDay();
            $dateDue = Carbon::parse($reviewAssignment->getDateDue())->startOfDay();

            if ($reviewAssignment->getDateReminded() !== null) {
                // we have a remainder sent previously

                $dateReminded = Carbon::parse($reviewAssignment->getDateReminded())->startOfDay();

                if ($reviewAssignment->getDateConfirmed() === null) {
                    // review request has not been responded
                    // previous remainder was a BEFORE REVIEW REQUEST RESPONSE remainder

                    if ($dateReminded->lt($dateResponseDue) &&
                        $currentDate->gte($dateResponseDue) && 
                        $currentDate->diffInDays($dateResponseDue) >= $numDaysAfterReviewResponseReminderDue) {

                        // ACTION:-> we need to sent a AFTER REVIEW REQUEST RESPONSE remainder
                        $mailable = ReviewResponseRemindAuto::class;
                    }
                } else {

                    if ($numDaysBeforeReviewSubmitReminderDue && 
                        $dateReminded->lt($dateDue) && 
                        $currentDate->lt($dateDue) && 
                        $dateDue->diffInDays($currentDate) <= $numDaysBeforeReviewSubmitReminderDue) {
                        
                        // no review submit remainder has been sent

                        // ACTION:-> we need to sent a BEFORE REVIEW SUBMIT remainder
                        $mailable = ReviewRemindAuto::class;

                    } else if ( $numDaysAfterReviewSubmitReminderDue &&
                                $dateReminded->lt($dateDue) && 
                                $currentDate->gt($dateDue) && 
                                $currentDate->diffInDays($dateDue) >= $numDaysAfterReviewSubmitReminderDue) {

                        // ACTION:-> we need to sent a AFTER REVIEW SUBMIT remainder
                        $mailable = ReviewRemindAuto::class;
                    }
                }
            } else if ($reviewAssignment->getDateConfirmed() != null) {
                // the review request has been responded
                // as long review request has respnded, only need to concern with BEFORE/AFTER REVIEW SUBMIT remainder
                if ($numDaysAfterReviewSubmitReminderDue && 
                    $currentDate->gt($dateDue) && 
                    $currentDate->diffInDays($dateDue) >= $numDaysAfterReviewSubmitReminderDue) {
                    
                    // ACTION:-> we need to send AFTER REVIEW SUBMIT remainder
                    $mailable = ReviewRemindAuto::class;

                } else if ( $numDaysBeforeReviewSubmitReminderDue && 
                            $dateDue->gt($currentDate) && 
                            $dateDue->diffInDays($currentDate) <= $numDaysBeforeReviewSubmitReminderDue) {
                    
                    // ACTION:-> we need to send BEFORE REVIEW SUBMIT remainder
                    $mailable = ReviewRemindAuto::class;
                }
            } else {
                // check for review response due
                if ($numDaysAfterReviewResponseReminderDue &&
                    $currentDate->gt($dateResponseDue) && 
                    $currentDate->diffInDays($dateResponseDue) >= $numDaysAfterReviewResponseReminderDue) {
                    
                    // ACTION:-> we need to send AFTER REVIEW REQUEST RESPONSE remainder
                    $mailable = ReviewResponseRemindAuto::class;

                } else if ( $numDaysBeforeReviewResponseReminderDue &&
                            $dateResponseDue->gt($currentDate) && 
                            $dateResponseDue->diffInDays($currentDate) <= $numDaysBeforeReviewResponseReminderDue) {
                    
                    // ACTION:-> we need to send BEFORE REVIEW REQUEST RESPONSE remainder
                    $mailable = ReviewResponseRemindAuto::class;
                }
            }

            if ($mailable) {
                ReviewRemainderJob::dispatch($reviewAssignment->getId(), $submission->getId(), $context->getId(), $mailable);
            }
        }

        return true;
    }
}

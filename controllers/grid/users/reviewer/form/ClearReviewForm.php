<?php

/**
 * @file controllers/grid/users/reviewer/form/ClearReviewForm.php
 *
 * Copyright (c) 2014-2026 Simon Fraser University
 * Copyright (c) 2003-2026 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class ClearReviewForm
 *
 * @ingroup controllers_grid_users_reviewer_form
 *
 * @brief Generic form to remove review assignment
 */

namespace PKP\controllers\grid\users\reviewer\form;

use APP\core\Application;
use APP\facades\Repo;
use APP\notification\NotificationManager;
use PKP\core\Core;
use PKP\core\PKPApplication;
use PKP\log\event\PKPSubmissionEventLogEntry;
use PKP\notification\Notification;
use PKP\plugins\Hook;
use PKP\security\Validation;

abstract class ClearReviewForm extends ReviewerNotifyActionForm
{
    /**
     * @copydoc Form::execute()
     *
     * @return bool whether or not the review assignment was deleted successfully
     *
     * @hook EditorAction::clearReview [[&$submission, $reviewAssignment]]
     */
    public function execute(...$functionArgs)
    {
        parent::execute(...$functionArgs);

        $request = Application::get()->getRequest();
        $submission = $this->getSubmission();
        $reviewAssignment = $this->getReviewAssignment();

        // Delete or cancel the review assignment.
        if (isset($reviewAssignment) && $reviewAssignment->getSubmissionId() == $submission->getId() && !Hook::call('EditorAction::clearReview', [&$submission, $reviewAssignment])) {
            $reviewer = Repo::user()->get($reviewAssignment->getReviewerId(), true);
            if (!isset($reviewer)) {
                return false;
            }
            if ($reviewAssignment->getDateConfirmed()) {
                // The review has been confirmed but not completed. Flag it as cancelled.
                Repo::reviewAssignment()->edit($reviewAssignment, [
                    'cancelled' => true,
                    'dateCancelled' => Core::getCurrentDate(),
                ]);
            } else {
                // The review had not been confirmed yet. Delete the assignment.
                Repo::reviewAssignment()->delete($reviewAssignment);
            }

            // Stamp the modification date
            $submission->stampModified();
            Repo::submission()->dao->update($submission);

            Notification::withAssoc(Application::ASSOC_TYPE_REVIEW_ASSIGNMENT, $reviewAssignment->getId())
                ->withUserId($reviewAssignment->getReviewerId())
                ->withType(Notification::NOTIFICATION_TYPE_REVIEW_ASSIGNMENT)
                ->delete();

            // Insert a trivial notification to indicate the reviewer was removed successfully.
            $currentUser = $request->getUser();
            $notificationMgr = new NotificationManager();
            $notificationMgr->createTrivialNotification($currentUser->getId(), Notification::NOTIFICATION_TYPE_SUCCESS, ['contents' => $reviewAssignment->getDateConfirmed() ? __('notification.cancelledReviewer') : __('notification.removedReviewer')]);

            // Add log
            $eventLog = Repo::eventLog()->newDataObject([
                'assocType' => PKPApplication::ASSOC_TYPE_SUBMISSION,
                'assocId' => $submission->getId(),
                'eventType' => PKPSubmissionEventLogEntry::SUBMISSION_LOG_REVIEW_CLEAR,
                'userId' => Validation::loggedInAs() ?? $currentUser->getId(),
                'message' => 'log.review.reviewCleared',
                'isTranslated' => false,
                'dateLogged' => Core::getCurrentDate(),
                'reviewAssignmentId' => $reviewAssignment->getId(),
                'reviewerName' => $reviewer->getFullName(),
                'submissionId' => $submission->getId(),
                'stageId' => $reviewAssignment->getStageId(),
                'round' => $reviewAssignment->getRound()
            ]);
            Repo::eventLog()->add($eventLog);

            return true;
        }
        return false;
    }
}

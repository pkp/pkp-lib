<?php
/**
 * @file controllers/grid/users/reviewer/form/UnassignReviewerForm.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class UnassignReviewerForm
 * @ingroup controllers_grid_users_reviewer_form
 *
 * @brief Allow the editor to remove a review assignment
 */

use APP\facades\Repo;

import('lib.pkp.controllers.grid.users.reviewer.form.ReviewerNotifyActionForm');

use APP\log\SubmissionEventLogEntry;
use APP\notification\NotificationManager;

use PKP\log\SubmissionLog;
use PKP\notification\PKPNotification;

class UnassignReviewerForm extends ReviewerNotifyActionForm
{
    /**
     * Constructor
     *
     * @param mixed $reviewAssignment ReviewAssignment
     * @param mixed $reviewRound ReviewRound
     * @param mixed $submission Submission
     */
    public function __construct($reviewAssignment, $reviewRound, $submission)
    {
        parent::__construct($reviewAssignment, $reviewRound, $submission, 'controllers/grid/users/reviewer/form/unassignReviewerForm.tpl');
    }

    /**
     * @copydoc ReviewerNotifyActionForm::getEmailKey()
     */
    public function getEmailKey()
    {
        return 'REVIEW_CANCEL';
    }

    /**
     * @copydoc Form::execute()
     *
     * @return bool whether or not the review assignment was deleted successfully
     */
    public function execute(...$functionArgs)
    {
        parent::execute(...$functionArgs);

        $request = Application::get()->getRequest();
        $submission = $this->getSubmission();
        $reviewAssignment = $this->getReviewAssignment();

        // Delete or cancel the review assignment.
        $reviewAssignmentDao = DAORegistry::getDAO('ReviewAssignmentDAO'); /** @var ReviewAssignmentDAO $reviewAssignmentDao */

        if (isset($reviewAssignment) && $reviewAssignment->getSubmissionId() == $submission->getId() && !HookRegistry::call('EditorAction::clearReview', [&$submission, $reviewAssignment])) {
            $reviewer = Repo::user()->get($reviewAssignment->getReviewerId(), true);
            if (!isset($reviewer)) {
                return false;
            }
            if ($reviewAssignment->getDateConfirmed()) {
                // The review has been confirmed but not completed. Flag it as cancelled.
                $reviewAssignment->setCancelled(true);
                $reviewAssignmentDao->updateObject($reviewAssignment);
            } else {
                // The review had not been confirmed yet. Delete the assignment.
                $reviewAssignmentDao->deleteById($reviewAssignment->getId());
            }

            // Stamp the modification date
            $submission->stampModified();
            Repo::submission()->dao->update($submission);

            $notificationDao = DAORegistry::getDAO('NotificationDAO'); /** @var NotificationDAO $notificationDao */
            $notificationDao->deleteByAssoc(
                ASSOC_TYPE_REVIEW_ASSIGNMENT,
                $reviewAssignment->getId(),
                $reviewAssignment->getReviewerId(),
                PKPNotification::NOTIFICATION_TYPE_REVIEW_ASSIGNMENT
            );

            // Insert a trivial notification to indicate the reviewer was removed successfully.
            $currentUser = $request->getUser();
            $notificationMgr = new NotificationManager();
            $notificationMgr->createTrivialNotification($currentUser->getId(), PKPNotification::NOTIFICATION_TYPE_SUCCESS, ['contents' => $reviewAssignment->getDateConfirmed() ? __('notification.cancelledReviewer') : __('notification.removedReviewer')]);

            // Add log
            SubmissionLog::logEvent($request, $submission, SubmissionEventLogEntry::SUBMISSION_LOG_REVIEW_CLEAR, 'log.review.reviewCleared', ['reviewAssignmentId' => $reviewAssignment->getId(), 'reviewerName' => $reviewer->getFullName(), 'submissionId' => $submission->getId(), 'stageId' => $reviewAssignment->getStageId(), 'round' => $reviewAssignment->getRound()]);

            return true;
        }
        return false;
    }
}

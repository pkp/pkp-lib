<?php
/**
 * @file controllers/grid/users/reviewer/form/ReinstateReviewerForm.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class ReinstateReviewerForm
 * @ingroup controllers_grid_users_reviewer_form
 *
 * @brief Allow the editor to reinstate a cancelled review assignment
 */

namespace PKP\controllers\grid\users\reviewer\form;

use APP\core\Application;
use APP\facades\Repo;
use APP\log\SubmissionEventLogEntry;
use APP\notification\NotificationManager;
use APP\submission\Submission;
use PKP\db\DAORegistry;
use PKP\log\SubmissionLog;
use PKP\notification\PKPNotification;
use PKP\plugins\HookRegistry;

class ReinstateReviewerForm extends ReviewerNotifyActionForm
{
    /**
     * Constructor
     *
     * @param ReviewAssignment $reviewAssignment
     * @param ReviewRound $reviewRound
     * @param Submission $submission
     */
    public function __construct($reviewAssignment, $reviewRound, $submission)
    {
        parent::__construct($reviewAssignment, $reviewRound, $submission, 'controllers/grid/users/reviewer/form/reinstateReviewerForm.tpl');
    }

    /**
     * @copydoc ReviewerNotifyActionForm::getEmailKey()
     */
    protected function getEmailKey()
    {
        return 'REVIEW_REINSTATE';
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
        $submission = $this->getSubmission(); /** @var Submission $submission */
        $reviewAssignment = $this->getReviewAssignment();

        // Reinstate the review assignment.
        $reviewAssignmentDao = DAORegistry::getDAO('ReviewAssignmentDAO'); /** @var ReviewAssignmentDAO $reviewAssignmentDao */
        if (isset($reviewAssignment) && $reviewAssignment->getSubmissionId() == $submission->getId() && !HookRegistry::call('EditorAction::reinstateReview', [&$submission, $reviewAssignment])) {
            $reviewer = Repo::user()->get($reviewAssignment->getReviewerId());
            if (!isset($reviewer)) {
                return false;
            }

            $reviewAssignment->setCancelled(false);
            $reviewAssignmentDao->updateObject($reviewAssignment);

            // Stamp the modification date
            $submission->stampModified();
            Repo::submission()->dao->update($submission);

            // Insert a trivial notification to indicate the reviewer was reinstated successfully.
            $currentUser = $request->getUser();
            $notificationMgr = new NotificationManager();
            $notificationMgr->createTrivialNotification($currentUser->getId(), PKPNotification::NOTIFICATION_TYPE_SUCCESS, ['contents' => __('notification.reinstatedReviewer')]);

            // Add log
            SubmissionLog::logEvent($request, $submission, SubmissionEventLogEntry::SUBMISSION_LOG_REVIEW_REINSTATED, 'log.review.reviewReinstated', ['reviewAssignmentId' => $reviewAssignment->getId(), 'reviewerName' => $reviewer->getFullName(), 'submissionId' => $submission->getId(), 'stageId' => $reviewAssignment->getStageId(), 'round' => $reviewAssignment->getRound()]);

            return true;
        }
        return false;
    }
}

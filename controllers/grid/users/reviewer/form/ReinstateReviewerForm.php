<?php
/**
 * @file controllers/grid/users/reviewer/form/ReinstateReviewerForm.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class ReinstateReviewerForm
 *
 * @ingroup controllers_grid_users_reviewer_form
 *
 * @brief Allow the editor to reinstate a cancelled review assignment
 */

namespace PKP\controllers\grid\users\reviewer\form;

use APP\core\Application;
use APP\facades\Repo;
use APP\notification\NotificationManager;
use APP\submission\Submission;
use PKP\context\Context;
use PKP\core\Core;
use PKP\core\PKPApplication;
use PKP\log\event\PKPSubmissionEventLogEntry;
use PKP\mail\Mailable;
use PKP\mail\mailables\ReviewerReinstate;
use PKP\notification\Notification;
use PKP\plugins\Hook;
use PKP\security\Validation;
use PKP\submission\reviewAssignment\ReviewAssignment;
use PKP\submission\reviewRound\ReviewRound;

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
     * @copydoc ReviewerNotifyActionForm::getMailable()
     */
    protected function getMailable(Context $context, Submission $submission, ReviewAssignment $reviewAssignment): Mailable
    {
        return new ReviewerReinstate($context, $submission, $reviewAssignment);
    }

    /**
     * @copydoc Form::execute()
     *
     * @return bool whether or not the review assignment was deleted successfully
     *
     * @hook EditorAction::reinstateReview [[&$submission, $reviewAssignment]]
     */
    public function execute(...$functionArgs)
    {
        parent::execute(...$functionArgs);

        $request = Application::get()->getRequest();
        $submission = $this->getSubmission(); /** @var Submission $submission */
        $reviewAssignment = $this->getReviewAssignment();

        // Reinstate the review assignment.
        if (isset($reviewAssignment) && $reviewAssignment->getSubmissionId() == $submission->getId() && !Hook::call('EditorAction::reinstateReview', [&$submission, $reviewAssignment])) {
            $reviewer = Repo::user()->get($reviewAssignment->getReviewerId());
            if (!isset($reviewer)) {
                return false;
            }

            Repo::reviewAssignment()->edit($reviewAssignment, [
                'cancelled' => false,
                'dateCancelled' => null,
            ]);

            // Stamp the modification date
            $submission->stampModified();
            Repo::submission()->dao->update($submission);

            // Insert a trivial notification to indicate the reviewer was reinstated successfully.
            $currentUser = $request->getUser();
            $notificationMgr = new NotificationManager();
            $notificationMgr->createTrivialNotification($currentUser->getId(), Notification::NOTIFICATION_TYPE_SUCCESS, ['contents' => __('notification.reinstatedReviewer')]);

            // Add log
            $eventLog = Repo::eventLog()->newDataObject([
                'assocType' => PKPApplication::ASSOC_TYPE_SUBMISSION,
                'assocId' => $submission->getId(),
                'eventType' => PKPSubmissionEventLogEntry::SUBMISSION_LOG_REVIEW_REINSTATED,
                'userId' => Validation::loggedInAs() ?? $currentUser->getId(),
                'message' => 'log.review.reviewReinstated',
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

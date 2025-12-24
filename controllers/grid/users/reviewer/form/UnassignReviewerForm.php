<?php
/**
 * @file controllers/grid/users/reviewer/form/UnassignReviewerForm.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class UnassignReviewerForm
 *
 * @ingroup controllers_grid_users_reviewer_form
 *
 * @brief Allow the editor to remove a review assignment
 */

namespace PKP\controllers\grid\users\reviewer\form;

use APP\core\Application;
use APP\facades\Repo;
use APP\notification\NotificationManager;
use APP\submission\Submission;
use PKP\context\Context;
use PKP\core\Core;
use PKP\core\PKPApplication;
use PKP\facades\Locale;
use PKP\invitation\core\enums\InvitationStatus;
use PKP\log\event\PKPSubmissionEventLogEntry;
use PKP\mail\Mailable;
use PKP\mail\mailables\ReviewerUnassign;
use PKP\notification\Notification;
use PKP\plugins\Hook;
use PKP\security\Validation;
use PKP\submission\reviewAssignment\ReviewAssignment;

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
     * @copydoc ReviewerNotifyActionForm::getMailable()
     */
    protected function getMailable(Context $context, Submission $submission, ReviewAssignment $reviewAssignment): Mailable
    {
        return new ReviewerUnassign($context, $submission, $reviewAssignment);
    }

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
            $reviewerId = $reviewAssignment->getReviewerId();

            // add temporary user because there will no user account until user accept the invitation
            $reviewer = $reviewerId
                ? Repo::user()->get($reviewerId, true)
                : (function() use ($reviewAssignment) {

                    $email = $reviewAssignment->getData('email');

                    $tempUser = Repo::user()->newDataObject();
                    $tempUser->setEmail($email);
                    $tempUser->setPreferredPublicName($email, Locale::getLocale()); //set email as preferred name for temporary user
                    return $tempUser;
                })();

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
            // update related invitation
            $invitation = Repo::invitation()->getInvitationByReviewerAssignmentId($reviewAssignment->getId());
            $invitation?->updateStatus(InvitationStatus::CANCELLED);
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

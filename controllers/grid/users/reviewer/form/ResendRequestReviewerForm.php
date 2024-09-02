<?php
/**
 * @file controllers/grid/users/reviewer/form/ResendRequestReviewerForm.php
 *
 * Copyright (c) 2014-2024 Simon Fraser University
 * Copyright (c) 2003-2024 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class ResendRequestReviewerForm
 *
 * @ingroup controllers_grid_users_reviewer_form
 *
 * @brief Allow the editor to resend request to reconsider a declined review assignment invitation
 */

namespace PKP\controllers\grid\users\reviewer\form;

use APP\core\Application;
use APP\core\Request;
use APP\facades\Repo;
use APP\notification\NotificationManager;
use APP\submission\Submission;
use PKP\context\Context;
use PKP\controllers\grid\users\reviewer\form\traits\HasReviewDueDate;
use PKP\core\Core;
use PKP\core\PKPApplication;
use PKP\log\event\PKPSubmissionEventLogEntry;
use PKP\mail\Mailable;
use PKP\mail\mailables\ReviewerResendRequest;
use PKP\notification\Notification;
use PKP\security\Validation;
use PKP\submission\reviewAssignment\ReviewAssignment;
use PKP\submission\reviewRound\ReviewRound;

class ResendRequestReviewerForm extends ReviewerNotifyActionForm
{
    use HasReviewDueDate;

    /**
     * Constructor
     */
    public function __construct(ReviewAssignment $reviewAssignment, ReviewRound $reviewRound, Submission $submission)
    {
        parent::__construct(
            $reviewAssignment,
            $reviewRound,
            $submission,
            'controllers/grid/users/reviewer/form/resendRequestReviewerForm.tpl'
        );

        // Validation checks for this form
        $this->addCheck(new \PKP\form\validation\FormValidator($this, 'responseDueDate', 'required', 'editor.review.errorAddingReviewer'));
        $this->addCheck(new \PKP\form\validation\FormValidator($this, 'reviewDueDate', 'required', 'editor.review.errorAddingReviewer'));
        $this->addCheck(
            new \PKP\form\validation\FormValidatorDateCompare(
                $this,
                'reviewDueDate',
                \Carbon\Carbon::parse(Application::get()->getRequest()->getUserVar('responseDueDate')),
                \PKP\validation\enums\DateComparisonRule::GREATER_OR_EQUAL,
                'required',
                'editor.review.errorAddingReviewer.dateValidationFailed'
            )
        );
    }

    /**
     * @copydoc \PKP\controllers\grid\users\reviewer\form\ReviewerNotifyActionForm::getMailable()
     */
    protected function getMailable(Context $context, Submission $submission, ReviewAssignment $reviewAssignment): Mailable
    {
        return new ReviewerResendRequest($context, $submission, $reviewAssignment);
    }

    /**
     * @copydoc \PKP\controllers\grid\users\reviewer\form\ReviewerNotifyActionForm::getEmailKey()
     */
    protected function getEmailKey()
    {
        return 'REVIEW_RESEND_REQUEST';
    }

    /**
     * @copydoc \PKP\controllers\grid\users\reviewer\form\ReviewerNotifyActionForm::initData()
     */
    public function initData()
    {
        parent::initData();

        [$reviewDueDate, $responseDueDate] = $this->getDueDates(Application::get()->getRequest()->getContext());

        $this->setData('responseDueDate', $responseDueDate);
        $this->setData('reviewDueDate', $reviewDueDate);
    }

    /**
     * @copydoc \PKP\controllers\grid\users\reviewer\form\ReviewerNotifyActionForm::readInputData()
     */
    public function readInputData()
    {
        parent::readInputData();

        $this->readUserVars([
            'responseDueDate',
            'reviewDueDate',
        ]);
    }

    /**
     * @copydoc Form::execute()
     *
     * @return bool whether or not the review assignment was deleted successfully
     */
    public function execute(...$functionArgs)
    {
        parent::execute(...$functionArgs);

        $request = Application::get()->getRequest(); /** @var Request $request */
        $submission = $this->getSubmission(); /** @var Submission $submission */
        $reviewAssignment = $this->getReviewAssignment(); /** @var ReviewAssignment $reviewAssignment */

        if (isset($reviewAssignment) && $reviewAssignment->getSubmissionId() == $submission->getId()) {
            $reviewer = Repo::user()->get($reviewAssignment->getReviewerId());
            if (!isset($reviewer)) {
                return false;
            }

            // $reviewAssignment->setCancelled(false);
            Repo::reviewAssignment()->edit($reviewAssignment, [
                'declined' => false,
                'requestResent' => true,
                'dateConfirmed' => null,
                'dateDue' => $this->getData('reviewDueDate'), // Set the review due date
                'dateResponseDue' => $this->getData('responseDueDate'), // Set the response due date
            ]);

            // Stamp the modification date
            $submission->stampLastActivity();
            Repo::submission()->dao->update($submission);

            // Insert a trivial notification to indicate the reviewer requested to reconsider successfully.
            $currentUser = $request->getUser();
            $notificationMgr = new NotificationManager();
            $notificationMgr->createTrivialNotification(
                $currentUser->getId(),
                Notification::NOTIFICATION_TYPE_SUCCESS,
                ['contents' => __('notification.reviewerResendRequest')]
            );

            // Add log
            $eventLog = Repo::eventLog()->newDataObject([
                'assocType' => PKPApplication::ASSOC_TYPE_SUBMISSION,
                'assocId' => $submission->getId(),
                'eventType' => PKPSubmissionEventLogEntry::SUBMISSION_LOG_REVIEW_ASSIGN,
                'userId' => Validation::loggedInAs() ?? $currentUser->getId(),
                'message' => 'log.review.reviewerResendRequest',
                'isTranslated' => false,
                'dateLogged' => Core::getCurrentDate(),
                'reviewAssignmentId' => $reviewAssignment->getId(),
                'reviewerName' => $reviewer->getFullName(),
                'submissionId' => $submission->getId(),
                'stageId' => $reviewAssignment->getStageId(),
                'round' => $reviewAssignment->getRound(),
            ]);
            Repo::eventLog()->add($eventLog);

            return true;
        }
        return false;
    }
}

<?php

/**
 * @file classes/submission/action/EditorAction.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class EditorAction
 *
 * @ingroup submission_action
 *
 * @brief Editor actions.
 */

namespace PKP\submission\action;

use APP\facades\Repo;
use APP\notification\NotificationManager;
use APP\submission\Submission;
use Illuminate\Support\Facades\Mail;
use PKP\context\Context;
use PKP\core\Core;
use PKP\core\PKPApplication;
use PKP\core\PKPRequest;
use PKP\db\DAORegistry;
use PKP\invitation\invitations\reviewerAccess\ReviewerAccessInvite;
use PKP\log\event\PKPSubmissionEventLogEntry;
use PKP\log\SubmissionEmailLogEventType;
use PKP\mail\mailables\ReviewRequest;
use PKP\mail\mailables\ReviewRequestSubsequent;
use PKP\notification\Notification;
use PKP\notification\PKPNotificationManager;
use PKP\plugins\Hook;
use PKP\security\Validation;
use PKP\submission\PKPSubmission;
use PKP\submission\reviewAssignment\ReviewAssignment;
use PKP\submission\reviewRound\ReviewRound;
use PKP\submission\reviewRound\ReviewRoundDAO;
use PKP\user\User;
use Symfony\Component\Mailer\Exception\TransportException;

class EditorAction
{
    /**
     * Constructor.
     */
    public function __construct()
    {
    }

    //
    // Actions.
    //

    /**
     * Assigns a reviewer to a submission.
     *
     * @param PKPRequest $request
     * @param object $submission
     * @param int $reviewerId
     * @param ReviewRound $reviewRound
     * @param string $reviewDueDate
     * @param string $responseDueDate
     * @param null|mixed $reviewMethod
     *
     * @hook EditorAction::addReviewer [[&$submission, $reviewerId]]
     */
    public function addReviewer($request, $submission, $reviewerId, &$reviewRound, $reviewDueDate, $responseDueDate, $reviewMethod = null)
    {
        $reviewer = Repo::user()->get($reviewerId);

        // Check to see if the requested reviewer is not already
        // assigned to review this submission.

        $assigned = (bool) Repo::reviewAssignment()->getCollector()
            ->filterByReviewRoundIds([$reviewRound->getId()])
            ->filterByReviewerIds([$reviewerId])
            ->getMany()
            ->first();

        // Only add the reviewer if he has not already
        // been assigned to review this submission.
        $stageId = $reviewRound->getStageId();
        $round = $reviewRound->getRound();
        $newData = [
            'submissionId' => $submission->getId(),
            'reviewerId' => $reviewerId,
            'dateAssigned' => Core::getCurrentDate(),
            'stageId' => $stageId,
            'round' => $round,
            'reviewRoundId' => $reviewRound->getId(),
        ];
        if (isset($reviewMethod)) {
            $newData['reviewMethod'] = $reviewMethod;
        }

        if (!$assigned && isset($reviewer) && !Hook::call('EditorAction::addReviewer', [&$submission, $reviewerId])) {
            $reviewAssignment = Repo::reviewAssignment()->newDataObject($newData);

            $reviewAssignmentId = Repo::reviewAssignment()->add($reviewAssignment);
            $reviewAssignment = Repo::reviewAssignment()->get($reviewAssignmentId);

            $this->setDueDates($request, $submission, $reviewAssignment, $reviewDueDate, $responseDueDate);

            // Add notification
            $notificationMgr = new NotificationManager();
            $notificationMgr->createNotification(
                $request,
                $reviewerId,
                Notification::NOTIFICATION_TYPE_REVIEW_ASSIGNMENT,
                $submission->getData('contextId'),
                PKPApplication::ASSOC_TYPE_REVIEW_ASSIGNMENT,
                $reviewAssignment->getId(),
                Notification::NOTIFICATION_LEVEL_TASK
            );

            // Add log
            $user = $request->getUser();
            $eventLog = Repo::eventLog()->newDataObject([
                'assocType' => PKPApplication::ASSOC_TYPE_SUBMISSION,
                'assocId' => $submission->getId(),
                'eventType' => PKPSubmissionEventLogEntry::SUBMISSION_LOG_REVIEW_ASSIGN,
                'userId' => Validation::loggedInAs() ?? $user->getId(),
                'message' => 'log.review.reviewerAssigned',
                'isTranslated' => false,
                'dateLogged' => Core::getCurrentDate(),
                'reviewAssignment' => $reviewAssignment->getId(),
                'reviewerName' => $reviewer->getFullName(),
                'submissionId' => $submission->getId(),
                'stageId' => $stageId,
                'round' => $round
            ]);
            Repo::eventLog()->add($eventLog);

            // Send mail
            if (!$request->getUserVar('skipEmail')) {
                $context = app()->get('context')->get($submission->getData('contextId'));
                $emailTemplate = Repo::emailTemplate()->getByKey($submission->getData('contextId'), $request->getUserVar('template'));
                $emailBody = $request->getUserVar('personalMessage');
                $emailSubject = $emailTemplate->getLocalizedData('subject');
                $mailable = $this->createMail($submission, $reviewAssignment, $reviewer, $user, $emailBody, $emailSubject, $context);

                try {
                    Mail::send($mailable);

                    Repo::emailLogEntry()->logMailable(
                        $round === ReviewRound::REVIEW_ROUND_STATUS_REVISIONS_REQUESTED
                            ? SubmissionEmailLogEventType::REVIEW_REQUEST
                            : SubmissionEmailLogEventType::REVIEW_REQUEST_SUBSEQUENT,
                        $mailable,
                        $submission,
                        $user
                    );
                } catch (TransportException $e) {
                    $notificationMgr = new PKPNotificationManager();
                    $notificationMgr->createTrivialNotification(
                        $user->getId(),
                        Notification::NOTIFICATION_TYPE_ERROR,
                        ['contents' => __('email.compose.error')]
                    );
                    trigger_error('Failed to send email: ' . $e->getMessage(), E_USER_WARNING);
                }
            }
        }
    }

    /**
     * Sets the due date for a review assignment.
     *
     * @param PKPRequest $request
     * @param Submission $submission
     * @param ReviewAssignment $reviewAssignment
     * @param string $reviewDueDate
     * @param string $responseDueDate
     *
     * @hook EditorAction::setDueDates [[&$reviewAssignment, &$reviewer, &$reviewDueDate, &$responseDueDate]]
     */
    public function setDueDates($request, $submission, $reviewAssignment, $reviewDueDate, $responseDueDate)
    {
        $context = $request->getContext();

        $reviewer = Repo::user()->get($reviewAssignment->getReviewerId());
        if (!isset($reviewer)) {
            return false;
        }

        if ($reviewAssignment->getSubmissionId() == $submission->getId() && !Hook::call('EditorAction::setDueDates', [&$reviewAssignment, &$reviewer, &$reviewDueDate, &$responseDueDate])) {
            Repo::reviewAssignment()->edit($reviewAssignment, [
                'dateDue' => $reviewDueDate, // Set the review due date
                'dateResponseDue' => $responseDueDate, // Set the response due date
            ]);
            $reviewAssignment->setDateDue($reviewDueDate);
            $reviewAssignment->setDateResponseDue($responseDueDate);
        }
    }

    /**
     * Create an email representation based on data entered by the editor to the ReviewerForm
     * Associated templates: REVIEW_REQUEST, REVIEW_REQUEST_SUBSEQUENT
     */
    protected function createMail(
        PKPSubmission $submission,
        ReviewAssignment $reviewAssignment,
        User $reviewer,
        User $sender,
        string $emailBody,
        string $emailSubject,
        Context $context
    ): ReviewRequest|ReviewRequestSubsequent {
        $reviewRoundDao = DAORegistry::getDAO('ReviewRoundDAO'); /** @var ReviewRoundDAO $reviewRoundDao */
        $reviewRound = $reviewRoundDao->getById($reviewAssignment->getReviewRoundId());
        $mailable = $reviewRound->getRound() == 1 ?
            new ReviewRequest($context, $submission, $reviewAssignment) :
            new ReviewRequestSubsequent($context, $submission, $reviewAssignment);

        if ($context->getData('reviewerAccessKeysEnabled')) {
            $reviewInvitation = new ReviewerAccessInvite();
            $reviewInvitation->initialize($reviewAssignment->getReviewerId(), $context->getId(), null, $sender->getId());

            $reviewInvitation->reviewAssignmentId = $reviewAssignment->getId();
            $reviewInvitation->updatePayload();

            $reviewInvitation->invite();
            $reviewInvitation->updateMailableWithUrl($mailable);
        }

        $mailable
            ->body($emailBody)
            ->subject($emailSubject)
            ->sender($sender)
            ->recipients([$reviewer]);

        // Additional template variable
        $mailable->addData([
            'reviewerName' => $mailable->viewData['userFullName'] ?? null,
            'reviewerUserName' => $mailable->viewData['username'] ?? null,
        ]);

        return $mailable;
    }
}

if (!PKP_STRICT_MODE) {
    class_alias('\PKP\submission\action\EditorAction', '\EditorAction');
}

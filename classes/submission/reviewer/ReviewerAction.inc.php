<?php

/**
 * @file classes/submission/reviewer/ReviewerAction.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class ReviewerAction
 * @ingroup submission
 *
 * @brief ReviewerAction class.
 */

namespace PKP\submission\reviewer;

use APP\log\SubmissionEventLogEntry;
use APP\notification\NotificationManager;
use PKP\core\Core;
use PKP\db\DAORegistry;
use PKP\log\SubmissionEmailLogEntry;
use PKP\log\SubmissionLog;
use PKP\mail\SubmissionMailTemplate;

use PKP\notification\PKPNotification;
use PKP\plugins\HookRegistry;

class ReviewerAction
{
    /**
     * Constructor
     */
    public function __construct()
    {
    }

    //
    // Actions.
    //
    /**
     * Records whether or not the reviewer accepts the review assignment.
     *
     * @param $request PKPRequest
     * @param $reviewAssignment ReviewAssignment
     * @param $submission Submission
     * @param $decline boolean
     * @param $emailText string optional
     */
    public function confirmReview($request, $reviewAssignment, $submission, $decline, $emailText = null)
    {
        $reviewAssignmentDao = DAORegistry::getDAO('ReviewAssignmentDAO'); /** @var ReviewAssignmentDAO $reviewAssignmentDao */
        $userDao = DAORegistry::getDAO('UserDAO'); /** @var UserDAO $userDao */

        $reviewer = $userDao->getById($reviewAssignment->getReviewerId());
        if (!isset($reviewer)) {
            return true;
        }

        // Only confirm the review for the reviewer if
        // he has not previously done so.
        if ($reviewAssignment->getDateConfirmed() == null) {
            $email = $this->getResponseEmail($submission, $reviewAssignment, $request, $decline);
            // Must explicitly set sender because we may be here on an access
            // key, in which case the user is not technically logged in
            $email->setReplyTo($reviewer->getEmail(), $reviewer->getFullName());
            HookRegistry::call('ReviewerAction::confirmReview', [$request, &$submission, &$email, $decline]);
            import('lib.pkp.classes.log.SubmissionEmailLogEntry'); // Import email event constants
            $email->setEventType($decline ? SubmissionEmailLogEntry::SUBMISSION_EMAIL_REVIEW_DECLINE : SubmissionEmailLogEntry::SUBMISSION_EMAIL_REVIEW_CONFIRM);
            if ($emailText) {
                $email->setBody($emailText);
            }
            if (!$email->send($request)) {
                $notificationMgr = new NotificationManager();
                $notificationMgr->createTrivialNotification($request->getUser()->getId(), PKPNotification::NOTIFICATION_TYPE_ERROR, ['contents' => __('email.compose.error')]);
            }

            $reviewAssignment->setDateReminded(null);
            $reviewAssignment->setReminderWasAutomatic(0);
            $reviewAssignment->setDeclined($decline);
            $reviewAssignment->setDateConfirmed(Core::getCurrentDate());
            $reviewAssignment->stampModified();
            $reviewAssignmentDao->updateObject($reviewAssignment);

            // Add log
            SubmissionLog::logEvent(
                $request,
                $submission,
                $decline ? SubmissionEventLogEntry::SUBMISSION_LOG_REVIEW_DECLINE : SubmissionEventLogEntry::SUBMISSION_LOG_REVIEW_ACCEPT,
                $decline ? 'log.review.reviewDeclined' : 'log.review.reviewAccepted',
                [
                    'reviewAssignmentId' => $reviewAssignment->getId(),
                    'reviewerName' => $reviewer->getFullName(),
                    'submissionId' => $reviewAssignment->getSubmissionId(),
                    'round' => $reviewAssignment->getRound()
                ]
            );
        }
    }

    /**
     * Get the reviewer response email template.
     */
    public function getResponseEmail($submission, $reviewAssignment, $request, $decline)
    {
        $email = new SubmissionMailTemplate($submission, $decline ? 'REVIEW_DECLINE' : 'REVIEW_CONFIRM');

        // Get reviewer
        $userDao = DAORegistry::getDAO('UserDAO'); /** @var UserDAO $userDao */
        $reviewer = $userDao->getById($reviewAssignment->getReviewerId());

        // Get editorial contact name
        $stageAssignmentDao = DAORegistry::getDAO('StageAssignmentDAO'); /** @var StageAssignmentDAO $stageAssignmentDao */
        $userGroupDao = DAORegistry::getDAO('UserGroupDAO'); /** @var UserGroupDAO $userGroupDao */
        $userDao = DAORegistry::getDAO('UserDAO'); /** @var UserDAO $userDao */
        $stageAssignments = $stageAssignmentDao->getBySubmissionAndStageId($submission->getId(), $reviewAssignment->getStageId());
        $recipient = null;
        $context = $request->getContext();
        while ($stageAssignment = $stageAssignments->next()) {
            $userGroup = $userGroupDao->getById($stageAssignment->getUserGroupId());
            if (!in_array($userGroup->getRoleId(), [ROLE_ID_MANAGER, ROLE_ID_SUB_EDITOR])) {
                continue;
            }

            $recipient = $userDao->getById($stageAssignment->getUserId());
            $email->addRecipient($recipient->getEmail(), $recipient->getFullName());
        }
        if (!$recipient) {
            $email->addRecipient($context->getData('contactEmail'), $context->getData('contactName'));
        }

        // Get due date
        $reviewDueDate = strtotime($reviewAssignment->getDateDue());
        $dateFormatShort = $context->getLocalizedDateFormatShort();
        if ($reviewDueDate == -1) {
            $reviewDueDate = $dateFormatShort;
        } // Default to something human-readable if no date specified
        else {
            $reviewDueDate = strftime($dateFormatShort, $reviewDueDate);
        }

        $email->setReplyTo($reviewer->getEmail(), $reviewer->getFullName());

        $email->assignParams([
            'reviewerName' => $reviewer->getFullName(),
            'reviewDueDate' => $reviewDueDate
        ]);
        $email->replaceParams();

        return $email;
    }
}

if (!PKP_STRICT_MODE) {
    class_alias('\PKP\submission\reviewer\ReviewerAction', '\ReviewerAction');
}

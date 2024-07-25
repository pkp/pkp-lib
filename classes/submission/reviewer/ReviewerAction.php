<?php

/**
 * @file classes/submission/reviewer/ReviewerAction.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class ReviewerAction
 *
 * @ingroup submission
 *
 * @brief ReviewerAction class.
 */

namespace PKP\submission\reviewer;

use APP\core\Application;
use APP\facades\Repo;
use APP\log\event\SubmissionEventLogEntry;
use APP\notification\NotificationManager;
use APP\submission\Submission;
use Illuminate\Support\Facades\Mail;
use PKP\context\Context;
use PKP\core\Core;
use PKP\core\PKPApplication;
use PKP\core\PKPRequest;
use PKP\log\SubmissionEmailLogEventType;
use PKP\mail\mailables\ReviewConfirm;
use PKP\mail\mailables\ReviewDecline;
use PKP\notification\Notification;
use PKP\plugins\Hook;
use PKP\security\Role;
use PKP\security\Validation;
use PKP\stageAssignment\StageAssignment;
use PKP\submission\PKPSubmission;
use PKP\submission\reviewAssignment\ReviewAssignment;
use Symfony\Component\Mailer\Exception\TransportException;

class ReviewerAction
{
    //
    // Actions.
    //
    /**
     * Records whether the reviewer accepts the review assignment.
     *
     * @hook ReviewerAction::confirmReview [[$request, $submission, $mailable, $decline]]
     */
    public function confirmReview(
        PKPRequest $request,
        ReviewAssignment $reviewAssignment,
        Submission $submission,
        bool $decline,
        ?string $emailText = null
    ): void {
        $reviewer = Repo::user()->get($reviewAssignment->getReviewerId());
        if (!isset($reviewer)) {
            return;
        }

        // Only confirm the review for the reviewer if
        // he has not previously done so.
        if ($reviewAssignment->getDateConfirmed() == null) {
            $mailable = $this->getResponseEmail($submission, $reviewAssignment, $decline, $emailText);
            Hook::call('ReviewerAction::confirmReview', [$request, $submission, $mailable, $decline]);

            if (!empty($mailable->to)) {
                try {
                    Mail::send($mailable);
                    Repo::emailLogEntry()->logMailable(
                        $decline ? SubmissionEmailLogEventType::REVIEW_DECLINE : SubmissionEmailLogEventType::REVIEW_CONFIRM,
                        $mailable,
                        $submission,
                        $mailable->getSenderUser()
                    );
                } catch (TransportException $e) {
                    $notificationMgr = new NotificationManager();
                    $notificationMgr->createTrivialNotification(
                        $request->getUser()->getId(),
                        Notification::NOTIFICATION_TYPE_ERROR,
                        ['contents' => __('email.compose.error')]
                    );
                    trigger_error($e->getMessage(), E_USER_WARNING);
                }
            }

            Repo::reviewAssignment()->edit($reviewAssignment, [
                'dateReminded' => null,
                'reminderWasAutomatic' => 0,
                'declined' => $decline,
                'dateConfirmed' => Core::getCurrentDate(),
            ]);

            // Add log
            $eventLog = Repo::eventLog()->newDataObject([
                'assocType' => PKPApplication::ASSOC_TYPE_SUBMISSION,
                'assocId' => $submission->getId(),
                'eventType' => $decline ? SubmissionEventLogEntry::SUBMISSION_LOG_REVIEW_DECLINE : SubmissionEventLogEntry::SUBMISSION_LOG_REVIEW_ACCEPT,
                'userId' => Validation::loggedInAs() ?? $request->getUser()->getId(),
                'message' => $decline ? 'log.review.reviewDeclined' : 'log.review.reviewAccepted',
                'isTranslate' => 0,
                'dateLogged' => Core::getCurrentDate(),
                'reviewAssignmentId' => $reviewAssignment->getId(),
                'reviewerName' => $reviewer->getFullName(),
                'submissionId' => $reviewAssignment->getSubmissionId(),
                'round' => $reviewAssignment->getRound()
            ]);
            Repo::eventLog()->add($eventLog);
        }
    }

    /**
     * Get the reviewer response email template.
     */
    public function getResponseEmail(
        PKPSubmission $submission,
        ReviewAssignment $reviewAssignment,
        bool $decline,
        ?string $emailText
    ): ReviewConfirm|ReviewDecline {
        $context = Application::getContextDAO()->getById($submission->getData('contextId')); /** @var Context $context */

        $mailable = $decline ?
            new ReviewDecline($submission, $reviewAssignment, $context) :
            new ReviewConfirm($submission, $reviewAssignment, $context);

        // Get reviewer
        $reviewer = Repo::user()->get($reviewAssignment->getReviewerId());
        $mailable->sender($reviewer);
        $mailable->replyTo($reviewer->getEmail(), $reviewer->getFullName());

        // Get editorial contact name
        // Replaces StageAssignmentDAO::getBySubmissionAndStageId
        $stageAssignments = StageAssignment::withSubmissionIds([$submission->getId()])
            ->withStageIds([$reviewAssignment->getStageId()])
            ->get();

        $recipients = [];
        foreach ($stageAssignments as $stageAssignment) {
            $userGroup = Repo::userGroup()->get($stageAssignment->userGroupId);
            if (!in_array($userGroup->getRoleId(), [Role::ROLE_ID_MANAGER, Role::ROLE_ID_SUB_EDITOR])) {
                continue;
            }

            $recipients[] = Repo::user()->get($stageAssignment->userId);
        }

        // Create dummy user if no one assigned
        if (empty($recipients)) {
            $contextUser = Repo::user()->getUserFromContextContact($context);
            if ($contextUser->getData('email')) {
                $recipients[] = $contextUser;
            }
        }

        $mailable->recipients($recipients);

        // Set email body and subject
        $template = Repo::emailTemplate()->getByKey($context->getId(), $mailable->getEmailTemplateKey());
        $emailText ? $mailable->body($emailText) : $mailable->body($template->getLocalizedData('body'));
        $mailable->subject($template->getLocalizedData('subject'));

        return $mailable;
    }
}

if (!PKP_STRICT_MODE) {
    class_alias('\PKP\submission\reviewer\ReviewerAction', '\ReviewerAction');
}

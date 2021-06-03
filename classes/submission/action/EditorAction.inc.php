<?php

/**
 * @file classes/submission/action/EditorAction.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class EditorAction
 * @ingroup submission_action
 *
 * @brief Editor actions.
 */

namespace PKP\submission\action;

use APP\facades\Repo;
use APP\i18n\AppLocale;
use APP\notification\Notification;
use APP\notification\NotificationManager;
use APP\submission\Submission;
use APP\workflow\EditorDecisionActionsManager;

use PKP\core\Core;
use PKP\db\DAORegistry;
use PKP\log\PKPSubmissionEventLogEntry;

// Access decision actions constants.
import('classes.workflow.EditorDecisionActionsManager');

use PKP\log\SubmissionLog;
use PKP\notification\PKPNotification;
use PKP\plugins\HookRegistry;
use PKP\submission\PKPSubmission;

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
     * Records an editor's submission decision.
     *
     * @param $request PKPRequest
     * @param $submission Submission
     * @param $decision integer
     * @param $decisionLabels array(SUBMISSION_EDITOR_DECISION_... or SUBMISSION_EDITOR_RECOMMEND_... => editor.submission.decision....)
     * @param $reviewRound ReviewRound optional Current review round that user is taking the decision, if any.
     * @param $stageId integer optional
     * @param $recommendation boolean optional
     */
    public function recordDecision($request, $submission, $decision, $decisionLabels, $reviewRound = null, $stageId = null, $recommendation = false)
    {
        $stageAssignmentDao = DAORegistry::getDAO('StageAssignmentDAO'); /** @var StageAssignmentDAO $stageAssignmentDao */

        // Define the stage and round data.
        if (!is_null($reviewRound)) {
            $stageId = $reviewRound->getStageId();
        } else {
            if ($stageId == null) {
                // We consider that the decision is being made for the current
                // submission stage.
                $stageId = $submission->getStageId();
            }
        }

        $editorAssigned = $stageAssignmentDao->editorAssignedToStage(
            $submission->getId(),
            $stageId
        );

        // Sanity checks
        if (!$editorAssigned || !isset($decisionLabels[$decision])) {
            return false;
        }

        $user = $request->getUser();
        $editorDecision = [
            'editDecisionId' => null,
            'editorId' => $user->getId(),
            'decision' => $decision,
            'dateDecided' => date(Core::getCurrentDate())
        ];

        $result = $editorDecision;
        if (!HookRegistry::call('EditorAction::recordDecision', [&$submission, &$editorDecision, &$result, &$recommendation])) {
            // Record the new decision
            $editDecisionDao = DAORegistry::getDAO('EditDecisionDAO'); /** @var EditDecisionDAO $editDecisionDao */
            $editDecisionDao->updateEditorDecision($submission->getId(), $editorDecision, $stageId, $reviewRound);

            // Set a new submission status if necessary
            if ($decision == EditorDecisionActionsManager::SUBMISSION_EDITOR_DECISION_DECLINE || $decision == EditorDecisionActionsManager::SUBMISSION_EDITOR_DECISION_INITIAL_DECLINE) {
                Repo::submission()->edit($submission, ['status' => Submission::STATUS_DECLINED]);
                $submission = Repo::submission()->get($submission->getId());
            } elseif ($submission->getStatus() == PKPSubmission::STATUS_DECLINED) {
                Repo::submission()->edit($submission, ['status' => Submission::STATUS_QUEUED]);
                $submission = Repo::submission()->get($submission->getId());
            }

            // Add log entry
            AppLocale::requireComponents(LOCALE_COMPONENT_APP_COMMON, LOCALE_COMPONENT_APP_EDITOR);
            $eventType = $recommendation ? PKPSubmissionEventLogEntry::SUBMISSION_LOG_EDITOR_RECOMMENDATION : PKPSubmissionEventLogEntry::SUBMISSION_LOG_EDITOR_DECISION;
            $logKey = $recommendation ? 'log.editor.recommendation' : 'log.editor.decision';
            SubmissionLog::logEvent($request, $submission, $eventType, $logKey, ['editorName' => $user->getFullName(), 'submissionId' => $submission->getId(), 'decision' => __($decisionLabels[$decision])]);
        }
        return $result;
    }


    /**
     * Assigns a reviewer to a submission.
     *
     * @param $request PKPRequest
     * @param $submission object
     * @param $reviewerId int
     * @param $reviewRound ReviewRound
     * @param $reviewDueDate datetime
     * @param $responseDueDate datetime
     * @param null|mixed $reviewMethod
     */
    public function addReviewer($request, $submission, $reviewerId, &$reviewRound, $reviewDueDate, $responseDueDate, $reviewMethod = null)
    {
        $reviewAssignmentDao = DAORegistry::getDAO('ReviewAssignmentDAO'); /** @var ReviewAssignmentDAO $reviewAssignmentDao */
        $userDao = DAORegistry::getDAO('UserDAO'); /** @var UserDAO $userDao */

        $reviewer = $userDao->getById($reviewerId);

        // Check to see if the requested reviewer is not already
        // assigned to review this submission.

        $assigned = $reviewAssignmentDao->reviewerExists($reviewRound->getId(), $reviewerId);

        // Only add the reviewer if he has not already
        // been assigned to review this submission.
        $stageId = $reviewRound->getStageId();
        $round = $reviewRound->getRound();
        if (!$assigned && isset($reviewer) && !HookRegistry::call('EditorAction::addReviewer', [&$submission, $reviewerId])) {
            $reviewAssignment = $reviewAssignmentDao->newDataObject();
            $reviewAssignment->setSubmissionId($submission->getId());
            $reviewAssignment->setReviewerId($reviewerId);
            $reviewAssignment->setDateAssigned(Core::getCurrentDate());
            $reviewAssignment->setStageId($stageId);
            $reviewAssignment->setRound($round);
            $reviewAssignment->setReviewRoundId($reviewRound->getId());
            if (isset($reviewMethod)) {
                $reviewAssignment->setReviewMethod($reviewMethod);
            }
            $reviewAssignmentDao->insertObject($reviewAssignment);

            $this->setDueDates($request, $submission, $reviewAssignment, $reviewDueDate, $responseDueDate);
            // Add notification
            $notificationMgr = new NotificationManager();
            $notificationMgr->createNotification(
                $request,
                $reviewerId,
                PKPNotification::NOTIFICATION_TYPE_REVIEW_ASSIGNMENT,
                $submission->getContextId(),
                ASSOC_TYPE_REVIEW_ASSIGNMENT,
                $reviewAssignment->getId(),
                Notification::NOTIFICATION_LEVEL_TASK,
                null,
                true
            );

            // Add log
            SubmissionLog::logEvent($request, $submission, PKPSubmissionEventLogEntry::SUBMISSION_LOG_REVIEW_ASSIGN, 'log.review.reviewerAssigned', ['reviewAssignmentId' => $reviewAssignment->getId(), 'reviewerName' => $reviewer->getFullName(), 'submissionId' => $submission->getId(), 'stageId' => $stageId, 'round' => $round]);
        }
    }

    /**
     * Sets the due date for a review assignment.
     *
     * @param $request PKPRequest
     * @param $submission Submission
     * @param $reviewAssignment ReviewAssignment
     * @param $reviewDueDate string
     * @param $responseDueDate string
     * @param $logEntry boolean
     */
    public function setDueDates($request, $submission, $reviewAssignment, $reviewDueDate, $responseDueDate, $logEntry = false)
    {
        $userDao = DAORegistry::getDAO('UserDAO'); /** @var UserDAO $userDao */
        $context = $request->getContext();

        $reviewer = $userDao->getById($reviewAssignment->getReviewerId());
        if (!isset($reviewer)) {
            return false;
        }

        if ($reviewAssignment->getSubmissionId() == $submission->getId() && !HookRegistry::call('EditorAction::setDueDates', [&$reviewAssignment, &$reviewer, &$reviewDueDate, &$responseDueDate])) {

            // Set the review due date
            $defaultNumWeeks = $context->getData('numWeeksPerReview');
            $reviewAssignment->setDateDue($reviewDueDate);

            // Set the response due date
            $defaultNumWeeks = $context->getData('numWeeksPerReponse');
            $reviewAssignment->setDateResponseDue($responseDueDate);

            // update the assignment (with both the new dates)
            $reviewAssignment->stampModified();
            $reviewAssignmentDao = DAORegistry::getDAO('ReviewAssignmentDAO'); /** @var ReviewAssignmentDAO $reviewAssignmentDao */
            $reviewAssignmentDao->updateObject($reviewAssignment);

            // N.B. Only logging Date Due
            if ($logEntry) {
                // Add log
                SubmissionLog::logEvent(
                    $request,
                    $submission,
                    PKPSubmissionEventLogEntry::SUBMISSION_LOG_REVIEW_SET_DUE_DATE,
                    'log.review.reviewDueDateSet',
                    [
                        'reviewAssignmentId' => $reviewAssignment->getId(),
                        'reviewerName' => $reviewer->getFullName(),
                        'dueDate' => strftime(
                            $context->getLocalizedDateFormatShort(),
                            strtotime($reviewAssignment->getDateDue())
                        ),
                        'submissionId' => $submission->getId(),
                        'stageId' => $reviewAssignment->getStageId(),
                        'round' => $reviewAssignment->getRound()
                    ]
                );
            }
        }
    }

    /**
     * Increment a submission's workflow stage.
     *
     * @param $submission Submission
     * @param $newStage integer One of the WORKFLOW_STAGE_* constants.
     */
    public function incrementWorkflowStage($submission, $newStage)
    {
        Repo::submission()->edit($submission, ['stageId' => $newStage]);
    }
}

if (!PKP_STRICT_MODE) {
    class_alias('\PKP\submission\action\EditorAction', '\EditorAction');
}

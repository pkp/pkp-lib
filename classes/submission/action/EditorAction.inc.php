<?php

/**
 * @file classes/submission/action/EditorAction.inc.php
 *
 * Copyright (c) 2014-2019 Simon Fraser University
 * Copyright (c) 2003-2019 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class EditorAction
 * @ingroup submission_action
 *
 * @brief Editor actions.
 */

// Access decision actions constants.
import('classes.workflow.EditorDecisionActionsManager');

class EditorAction {
	/**
	 * Constructor.
	 */
	function __construct() {
	}

	//
	// Actions.
	//
	/**
	 * Records an editor's submission decision.
	 * @param $request PKPRequest
	 * @param $submission Submission
	 * @param $decision integer
	 * @param $decisionLabels array(SUBMISSION_EDITOR_DECISION_... or SUBMISSION_EDITOR_RECOMMEND_... => editor.submission.decision....)
	 * @param $reviewRound ReviewRound optional Current review round that user is taking the decision, if any.
	 * @param $stageId integer optional
	 * @param $recommendation boolean optional
	 */
	function recordDecision($request, $submission, $decision, $decisionLabels, $reviewRound = null, $stageId = null, $recommendation = false) {
		$stageAssignmentDao = DAORegistry::getDAO('StageAssignmentDAO');

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
		if (!$editorAssigned || !isset($decisionLabels[$decision])) return false;

		$user = $request->getUser();
		$editorDecision = array(
			'editDecisionId' => null,
			'editorId' => $user->getId(),
			'decision' => $decision,
			'dateDecided' => date(Core::getCurrentDate())
		);

		$result = $editorDecision;
		if (!HookRegistry::call('EditorAction::recordDecision', array(&$submission, &$editorDecision, &$result, &$recommendation))) {
			// Record the new decision
			$editDecisionDao = DAORegistry::getDAO('EditDecisionDAO');
			$editDecisionDao->updateEditorDecision($submission->getId(), $editorDecision, $stageId, $reviewRound);

			// Set a new submission status if necessary
			$submissionDao = Application::getSubmissionDAO();
			if ($decision == SUBMISSION_EDITOR_DECISION_DECLINE || $decision == SUBMISSION_EDITOR_DECISION_INITIAL_DECLINE) {
				$submission->setStatus(STATUS_DECLINED);
				$submissionDao->updateObject($submission);
			} elseif ($submission->getStatus() == STATUS_DECLINED) {
				$submission->setStatus(STATUS_QUEUED);
				$submissionDao->updateObject($submission);
			}

			// Add log entry
			import('lib.pkp.classes.log.SubmissionLog');
			import('lib.pkp.classes.log.PKPSubmissionEventLogEntry');
			AppLocale::requireComponents(LOCALE_COMPONENT_APP_COMMON, LOCALE_COMPONENT_APP_EDITOR);
			$eventType = $recommendation ? SUBMISSION_LOG_EDITOR_RECOMMENDATION : SUBMISSION_LOG_EDITOR_DECISION;
			$logKey = $recommendation ? 'log.editor.recommendation' : 'log.editor.decision';
			SubmissionLog::logEvent($request, $submission, $eventType, $logKey, array('editorName' => $user->getFullName(), 'submissionId' => $submission->getId(), 'decision' => __($decisionLabels[$decision])));
		}
		return $result;
	}


	/**
	 * Assigns a reviewer to a submission.
	 * @param $request PKPRequest
	 * @param $submission object
	 * @param $reviewerId int
	 * @param $reviewRound ReviewRound
	 * @param $reviewDueDate datetime
	 * @param $responseDueDate datetime
	 */
	function addReviewer($request, $submission, $reviewerId, &$reviewRound, $reviewDueDate, $responseDueDate, $reviewMethod = null) {
		$reviewAssignmentDao = DAORegistry::getDAO('ReviewAssignmentDAO');
		$userDao = DAORegistry::getDAO('UserDAO');

		$reviewer = $userDao->getById($reviewerId);

		// Check to see if the requested reviewer is not already
		// assigned to review this submission.

		$assigned = $reviewAssignmentDao->reviewerExists($reviewRound->getId(), $reviewerId);

		// Only add the reviewer if he has not already
		// been assigned to review this submission.
		$stageId = $reviewRound->getStageId();
		$round = $reviewRound->getRound();
		if (!$assigned && isset($reviewer) && !HookRegistry::call('EditorAction::addReviewer', array(&$submission, $reviewerId))) {
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
				NOTIFICATION_TYPE_REVIEW_ASSIGNMENT,
				$submission->getContextId(),
				ASSOC_TYPE_REVIEW_ASSIGNMENT,
				$reviewAssignment->getId(),
				NOTIFICATION_LEVEL_TASK,
				null,
				true
			);

			// Add log
			import('lib.pkp.classes.log.SubmissionLog');
			import('lib.pkp.classes.log.PKPSubmissionEventLogEntry');
			SubmissionLog::logEvent($request, $submission, SUBMISSION_LOG_REVIEW_ASSIGN, 'log.review.reviewerAssigned', array('reviewAssignmentId' => $reviewAssignment->getId(), 'reviewerName' => $reviewer->getFullName(), 'submissionId' => $submission->getId(), 'stageId' => $stageId, 'round' => $round));
		}
	}

	/**
	 * Sets the due date for a review assignment.
	 * @param $request PKPRequest
	 * @param $submission Submission
	 * @param $reviewAssignment ReviewAssignment
	 * @param $reviewDueDate string
	 * @param $responseDueDate string
	 * @param $logEntry boolean
	 */
	function setDueDates($request, $submission, $reviewAssignment, $reviewDueDate, $responseDueDate, $logEntry = false) {
		$userDao = DAORegistry::getDAO('UserDAO');
		$context = $request->getContext();

		$reviewer = $userDao->getById($reviewAssignment->getReviewerId());
		if (!isset($reviewer)) return false;

		if ($reviewAssignment->getSubmissionId() == $submission->getId() && !HookRegistry::call('EditorAction::setDueDates', array(&$reviewAssignment, &$reviewer, &$reviewDueDate, &$responseDueDate))) {

			// Set the review due date
			$defaultNumWeeks = $context->getData('numWeeksPerReview');
			$reviewAssignment->setDateDue($reviewDueDate);

			// Set the response due date
			$defaultNumWeeks = $context->getData('numWeeksPerReponse');
			$reviewAssignment->setDateResponseDue($responseDueDate);

			// update the assignment (with both the new dates)
			$reviewAssignment->stampModified();
			$reviewAssignmentDao = DAORegistry::getDAO('ReviewAssignmentDAO'); /* @var $reviewAssignmentDao ReviewAssignmentDAO */
			$reviewAssignmentDao->updateObject($reviewAssignment);

			// N.B. Only logging Date Due
			if ($logEntry) {
				// Add log
				import('lib.pkp.classes.log.SubmissionLog');
				import('classes.log.SubmissionEventLogEntry');
				SubmissionLog::logEvent(
					$request,
					$submission,
					SUBMISSION_LOG_REVIEW_SET_DUE_DATE,
					'log.review.reviewDueDateSet',
					array(
						'reviewAssignmentId' => $reviewAssignment->getId(),
						'reviewerName' => $reviewer->getFullName(),
						'dueDate' => strftime(
							Config::getVar('general', 'date_format_short'),
							strtotime($reviewAssignment->getDateDue())
						),
						'submissionId' => $submission->getId(),
						'stageId' => $reviewAssignment->getStageId(),
						'round' => $reviewAssignment->getRound()
					)
				);
			}
		}
	}

	/**
	 * Increment a submission's workflow stage.
	 * @param $submission Submission
	 * @param $newStage integer One of the WORKFLOW_STAGE_* constants.
	 * @param $request Request
	 */
	function incrementWorkflowStage($submission, $newStage, $request) {
		// Change the submission's workflow stage.
		$submission->setStageId($newStage);
		$submissionDao = Application::getSubmissionDAO();
		$submissionDao->updateObject($submission);
	}
}



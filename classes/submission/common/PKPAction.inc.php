<?php

/**
 * @defgroup submission_common
 */

/**
 * @file classes/submission/common/PKPAction.inc.php
 *
 * Copyright (c) 2003-2013 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class PKPAction
 * @ingroup submission_common
 *
 * @brief Application-independent submission actions.
 */


class PKPAction {
	/**
	 * Constructor.
	 */
	function PKPAction() {
	}

	//
	// Actions.
	//
	/**
	 * Edit citations
	 * @param $request Request
	 * @param $submission Submission
	 * @return string the rendered response
	 */
	function editCitations($request, $submission) {
		$router = $request->getRouter();
		$dispatcher = $this->getDispatcher();
		$templateMgr = TemplateManager::getManager($request);

		// Add extra style sheets required for ajax components
		// FIXME: Must be removed after OMP->OJS backporting
		$templateMgr->addStyleSheet($request->getBaseUrl().'/styles/ojs.css');

		// Add extra java script required for ajax components
		// FIXME: Must be removed after OMP->OJS backporting
		$templateMgr->addJavaScript('lib/pkp/js/functions/citation.js');
		$templateMgr->addJavaScript('lib/pkp/js/lib/jquery/plugins/validate/jquery.validate.min.js');
		$templateMgr->addJavaScript('lib/pkp/js/functions/jqueryValidatorI18n.js');
		$templateMgr->addJavaScript('lib/pkp/js/lib/jquery/plugins/jquery.splitter.js');

		$citationEditorConfigurationError = null;

		// Check whether the citation editor requirements are complete.
		// 1) Citation editing must be enabled for the journal.
		if (!$citationEditorConfigurationError) {
			$context = $router->getContext($request);
			if (!$context->getSetting('metaCitations')) $citationEditorConfigurationError = 'submission.citations.editor.pleaseSetup';
		}

		// 2) At least one citation parser is available.
		$citationDao = DAORegistry::getDAO('CitationDAO'); // NB: This also loads the parser/lookup filter category constants.
		if (!$citationEditorConfigurationError) {
			$filterDao = DAORegistry::getDAO('FilterDAO'); /* @var $filterDao FilterDAO */
			$configuredCitationParsers =& $filterDao->getObjectsByGroup(CITATION_PARSER_FILTER_GROUP, $context->getId());
			if (!count($configuredCitationParsers)) $citationEditorConfigurationError = 'submission.citations.editor.pleaseAddParserFilter';
		}

		// 3) A citation output filter has been set.
		if (!$citationEditorConfigurationError && !($context->getSetting('metaCitationOutputFilterId') > 0)) {
			$citationEditorConfigurationError = 'submission.citations.editor.pleaseConfigureOutputStyle';
		}

		$templateMgr->assign('citationEditorConfigurationError', $citationEditorConfigurationError);

		// Should we display the "Introduction" tab?
		if (is_null($citationEditorConfigurationError)) {
			$user = $request->getUser();
			$introductionHide = (boolean)$user->getSetting('citation-editor-hide-intro');
		} else {
			// Always show the introduction tab if we have a configuration error.
			$introductionHide = false;
		}
		$templateMgr->assign('introductionHide', $introductionHide);

		// Display an initial help message.
		$citations =& $citationDao->getObjectsByAssocId(ASSOC_TYPE_ARTICLE, $submission->getId());
		if ($citations->getCount() > 0) {
			$initialHelpMessage = __('submission.citations.editor.details.pleaseClickOnCitationToStartEditing');
		} else {
			$articleMetadataUrl = $router->url($request, null, null, 'viewMetadata', $submission->getId());
			$initialHelpMessage = __('submission.citations.editor.pleaseImportCitationsFirst', array('articleMetadataUrl' => $articleMetadataUrl));
		}
		$templateMgr->assign('initialHelpMessage', $initialHelpMessage);

		// Find out whether all citations have been processed or not.
		$unprocessedCitations =& $citationDao->getObjectsByAssocId(ASSOC_TYPE_ARTICLE, $submission->getId(), 0, CITATION_CHECKED);
		if ($unprocessedCitations->getCount() > 0) {
			$templateMgr->assign('unprocessedCitations', $unprocessedCitations->toArray());
		} else {
			$templateMgr->assign('unprocessedCitations', false);
		}

		// Add the grid URL.
		$citationGridUrl = $dispatcher->url($request, ROUTE_COMPONENT, null, 'grid.citation.CitationGridHandler', 'fetchGrid', null, array('assocId' => $submission->getId()));
		$templateMgr->assign('citationGridUrl', $citationGridUrl);

		// Add the export URL.
		$citationGridUrl = $dispatcher->url($request, ROUTE_COMPONENT, null, 'grid.citation.CitationGridHandler', 'exportCitations', null, array('assocId' => $submission->getId()));
		$templateMgr->assign('citationExportUrl', $citationGridUrl);

		// Add the submission.
		$templateMgr->assign_by_ref('submission', $submission);
	}

	/**
	 * Records an editor's submission decision.
	 * @param $request PKPRequest
	 * @param $submission Submission
	 * @param $decision integer
	 * @param $decisionLabels array(DECISION_CONSTANT => decision.locale.key, ...)
	 * @param $reviewRound ReviewRound Current review round that user is taking the decision, if any.
	 * @param $stageId int
	 */
	function recordDecision($request, $submission, $decision, $decisionLabels, $reviewRound = null, $stageId = null) {
		$stageAssignmentDao = DAORegistry::getDAO('StageAssignmentDAO');

		// Define the stage and round data.
		if (!is_null($reviewRound)) {
			$stageId = $reviewRound->getStageId();
			$round = $reviewRound->getRound();
		} else {
			$round = REVIEW_ROUND_NONE;
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
		if (!HookRegistry::call('PKPAction::recordDecision', array(&$submission, &$editorDecision, &$result))) {
			// Record the new decision
			$editDecisionDao = DAORegistry::getDAO('EditDecisionDAO');
			$editDecisionDao->updateEditorDecision($submission->getId(), $editorDecision, $stageId, $round);

			// Stamp the submission modified
			$submission->setStatus(STATUS_QUEUED);
			$submission->stampStatusModified();
			$submissionDao = Application::getSubmissionDAO();
			$submissionDao->updateObject($submission);

			// Add log entry
			import('lib.pkp.classes.log.SubmissionLog');
			import('lib.pkp.classes.log.PKPSubmissionEventLogEntry');
			AppLocale::requireComponents(LOCALE_COMPONENT_APP_COMMON, LOCALE_COMPONENT_APP_EDITOR);
			SubmissionLog::logEvent($request, $submission, SUBMISSION_LOG_EDITOR_DECISION, 'log.editor.decision', array('editorName' => $user->getFullName(), 'submissionId' => $submission->getId(), 'decision' => __($decisionLabels[$decision])));
		}
		return $result;
	}

	/**
	 * Clears a review assignment from a submission.
	 * @param $request PKPRequest
	 * @param $submission object
	 * @param $reviewId int
	 */
	function clearReview($request, $submissionId, $reviewId) {
		$submissionDao = Application::getSubmissionDAO();
		$submission = $submissionDao->getById($submissionId);
		$reviewAssignmentDao = DAORegistry::getDAO('ReviewAssignmentDAO');
		$userDao = DAORegistry::getDAO('UserDAO');

		$reviewAssignment = $reviewAssignmentDao->getById($reviewId);

		if (isset($reviewAssignment) && $reviewAssignment->getSubmissionId() == $submission->getId() && !HookRegistry::call('PKPAction::clearReview', array(&$submission, $reviewAssignment))) {
			$reviewer = $userDao->getById($reviewAssignment->getReviewerId());
			if (!isset($reviewer)) return false;
			$reviewAssignmentDao->deleteById($reviewId);

			// Stamp the modification date
			$submission->stampModified();
			$submissionDao->updateObject($submission);

			$notificationDao = DAORegistry::getDAO('NotificationDAO');
			$notificationDao->deleteByAssoc(
				ASSOC_TYPE_REVIEW_ASSIGNMENT,
				$reviewAssignment->getId(),
				$reviewAssignment->getReviewerId(),
				NOTIFICATION_TYPE_REVIEW_ASSIGNMENT
			);

			// Insert a trivial notification to indicate the reviewer was removed successfully.
			$currentUser = $request->getUser();
			$notificationMgr = new NotificationManager();
			$notificationMgr->createTrivialNotification($currentUser->getId(), NOTIFICATION_TYPE_SUCCESS, array('contents' => __('notification.removedReviewer')));

			// Update the review round status, if needed.
			$reviewRoundDao = DAORegistry::getDAO('ReviewRoundDAO');
			$reviewRound = $reviewRoundDao->getById($reviewAssignment->getReviewRoundId());
			$reviewAssignments = $reviewAssignmentDao->getBySubmissionId($reviewRound->getSubmissionId(), $reviewRound->getRound(), $reviewRound->getStageId());
			$reviewRoundDao->updateStatus($reviewRound, $reviewAssignments);

			$notificationMgr->updateNotification(
				$request,
				array(NOTIFICATION_TYPE_ALL_REVIEWS_IN),
				null,
				ASSOC_TYPE_REVIEW_ROUND,
				$reviewRound->getId()
			);

			// Add log
			import('lib.pkp.classes.log.SubmissionLog');
			import('classes.log.SubmissionEventLogEntry');
			SubmissionLog::logEvent($request, $submission, SUBMISSION_LOG_REVIEW_CLEAR, 'log.review.reviewCleared', array('reviewerName' => $reviewer->getFullName(), 'submissionId' => $submission->getId(), 'stageId' => $reviewAssignment->getStageId(), 'round' => $reviewAssignment->getRound()));

			return true;
		} else return false;
	}

	/**
	 * Assigns a reviewer to a submission.
	 * @param $request PKPRequest
	 * @param $submission object
	 * @param $reviewerId int
	 * @param $reviewRound ReviewRound
	 * @param $reviewDueDate datetime optional
	 * @param $responseDueDate datetime optional
	 */
	function addReviewer($request, $submission, $reviewerId, &$reviewRound, $reviewDueDate = null, $responseDueDate = null, $reviewMethod = null) {
		$reviewAssignmentDao = DAORegistry::getDAO('ReviewAssignmentDAO');
		$userDao = DAORegistry::getDAO('UserDAO');

		$reviewer =& $userDao->getById($reviewerId);

		// Check to see if the requested reviewer is not already
		// assigned to review this submission.

		$assigned = $reviewAssignmentDao->reviewerExists($reviewRound->getId(), $reviewerId);

		// Only add the reviewer if he has not already
		// been assigned to review this submission.
		$stageId = $reviewRound->getStageId();
		$round = $reviewRound->getRound();
		if (!$assigned && isset($reviewer) && !HookRegistry::call('PKPAction::addReviewer', array(&$submission, $reviewerId))) {
			$reviewAssignment = new ReviewAssignment();
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

			// Stamp modification date
			$submission->stampStatusModified();
			$submissionDao = Application::getSubmissionDAO();
			$submissionDao->updateObject($submission);

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
				NOTIFICATION_LEVEL_TASK
			);

			// Insert a trivial notification to indicate the reviewer was added successfully.
			$currentUser = $request->getUser();
			$notificationMgr = new NotificationManager();
			$notificationMgr->createTrivialNotification($currentUser->getId(), NOTIFICATION_TYPE_SUCCESS, array('contents' => __('notification.addedReviewer')));

			// Update the review round status.
			$reviewRoundDao = DAORegistry::getDAO('ReviewRoundDAO'); /* @var $reviewRoundDao ReviewRoundDAO */
			$reviewAssignments = $reviewAssignmentDao->getBySubmissionId($submission->getId(), $round, $stageId);
			$reviewRoundDao->updateStatus($reviewRound, $reviewAssignments);

			$notificationMgr->updateNotification(
				$request,
				array(NOTIFICATION_TYPE_ALL_REVIEWS_IN),
				null,
				ASSOC_TYPE_REVIEW_ROUND,
				$reviewRound->getId()
			);

			// Add log
			import('lib.pkp.classes.log.SubmissionLog');
			import('lib.pkp.classes.log.PKPSubmissionEventLogEntry');
			SubmissionLog::logEvent($request, $submission, SUBMISSION_LOG_REVIEW_ASSIGN, 'log.review.reviewerAssigned', array('reviewerName' => $reviewer->getFullName(), 'submissionId' => $submission->getId(), 'stageId' => $stageId, 'round' => $round));
		}
	}

	/**
	 * Sets the due date for a review assignment.
	 * @param $request PKPRequest
	 * @param $submission Submission
	 * @param $reviewId int
	 * @param $dueDate string
	 * @param $numWeeks int
	 * @param $logEntry boolean
	 */
	function setDueDates($request, $submission, $reviewAssignment, $reviewDueDate = null, $responseDueDate = null, $logEntry = false) {
		$userDao = DAORegistry::getDAO('UserDAO');
		$context = $request->getContext();

		$reviewer = $userDao->getById($reviewAssignment->getReviewerId());
		if (!isset($reviewer)) return false;

		if ($reviewAssignment->getSubmissionId() == $submission->getId() && !HookRegistry::call('PKPAction::setDueDates', array(&$reviewAssignment, &$reviewer, &$reviewDueDate, &$responseDueDate))) {

			// Set the review due date
			$defaultNumWeeks = $context->getSetting('numWeeksPerReview');
			$reviewAssignment->setDateDue(DAO::formatDateToDB($reviewDueDate, $defaultNumWeeks, false));

			// Set the response due date
			$defaultNumWeeks = $context->getSetting('numWeeksPerReponse');
			$reviewAssignment->setDateResponseDue(DAO::formatDateToDB($responseDueDate, $defaultNumWeeks, false));

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
}

?>

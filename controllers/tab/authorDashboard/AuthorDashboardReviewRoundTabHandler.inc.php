<?php

/**
 * @file controllers/tab/authorDashboard/AuthorDashboardReviewRoundTabHandler.inc.php
 *
 * Copyright (c) 2014-2015 Simon Fraser University Library
 * Copyright (c) 2003-2015 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class AuthorDashboardReviewRoundTabHandler
 * @ingroup controllers_tab_authorDashboard
 *
 * @brief Handle AJAX operations for review round tabs on author dashboard page.
 */

// Import the base Handler.
import('pages.authorDashboard.AuthorDashboardHandler');
import('lib.pkp.classes.core.JSONMessage');

class AuthorDashboardReviewRoundTabHandler extends AuthorDashboardHandler {

	/**
	 * Constructor
	 */
	function AuthorDashboardReviewRoundTabHandler() {
		parent::Handler();
		$this->addRoleAssignment(array(ROLE_ID_AUTHOR), array('fetchReviewRoundInfo'));
	}


	//
	// Extended methods from Handler
	//
	/**
	 * @copydoc PKPHandler::authorize()
	 */
	function authorize($request, &$args, $roleAssignments) {
		$stageId = (int)$request->getUserVar('stageId');

		// Authorize stage id.
		import('lib.pkp.classes.security.authorization.internal.WorkflowStageRequiredPolicy');
		$this->addPolicy(new WorkflowStageRequiredPolicy($stageId));

		// We need a review round id in request.
		import('lib.pkp.classes.security.authorization.internal.ReviewRoundRequiredPolicy');
		$this->addPolicy(new ReviewRoundRequiredPolicy($request, $args));

		return parent::authorize($request, $args, $roleAssignments);
	}


	//
	// Public handler operations
	//
	/**
	 * Fetch information for the author on the specified review round
	 * @param $args array
	 * @param $request Request
	 * @return JSONMessage JSON object
	 */
	function fetchReviewRoundInfo($args, $request) {
		$this->setupTemplate($request);
		$templateMgr = TemplateManager::getManager($request);

		$stageId = $this->getAuthorizedContextObject(ASSOC_TYPE_WORKFLOW_STAGE);
		if ($stageId !== WORKFLOW_STAGE_ID_INTERNAL_REVIEW && $stageId !== WORKFLOW_STAGE_ID_EXTERNAL_REVIEW) {
			fatalError('Invalid Stage Id');
		}
		$templateMgr->assign('stageId', $stageId);

		$reviewRound = $this->getAuthorizedContextObject(ASSOC_TYPE_REVIEW_ROUND);
		$templateMgr->assign('reviewRoundId', $reviewRound->getId());
		$submission = $this->getAuthorizedContextObject(ASSOC_TYPE_SUBMISSION);
		$templateMgr->assign('submission', $submission);

		// Review round request notification options.
		$notificationRequestOptions = array(
			NOTIFICATION_LEVEL_NORMAL => array(
				NOTIFICATION_TYPE_REVIEW_ROUND_STATUS => array(ASSOC_TYPE_REVIEW_ROUND, $reviewRound->getId())),
			NOTIFICATION_LEVEL_TRIVIAL => array()
		);
		$templateMgr->assign('reviewRoundNotificationRequestOptions', $notificationRequestOptions);

		// Editor has taken an action and sent an email; Display the email
		import('classes.workflow.EditorDecisionActionsManager');
		if(EditorDecisionActionsManager::getEditorTakenActionInReviewRound($reviewRound)) {
			$submissionEmailLogDao = DAORegistry::getDAO('SubmissionEmailLogDAO');
			$user = $request->getUser();
			$submissionEmailFactory = $submissionEmailLogDao->getByEventType($submission->getId(), SUBMISSION_EMAIL_EDITOR_NOTIFY_AUTHOR, $user->getId());

			$templateMgr->assign('submissionEmails', $submissionEmailFactory);
			$templateMgr->assign('showReviewAttachments', true);
		}

		return $templateMgr->fetchJson('authorDashboard/reviewRoundInfo.tpl');
	}
}

?>

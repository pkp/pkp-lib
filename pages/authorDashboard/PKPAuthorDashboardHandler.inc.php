<?php

/**
 * @file pages/authorDashboard/PKPAuthorDashboardHandler.inc.php
 *
 * Copyright (c) 2014-2015 Simon Fraser University Library
 * Copyright (c) 2003-2015 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class PKPAuthorDashboardHandler
 * @ingroup pages_authorDashboard
 *
 * @brief Handle requests for the author dashboard.
 */

// Import base class
import('classes.handler.Handler');

class PKPAuthorDashboardHandler extends Handler {

	/**
	 * Constructor
	 */
	function PKPAuthorDashboardHandler() {
		parent::Handler();
		$this->addRoleAssignment($this->_getAssignmentRoles(), array('submission', 'readSubmissionEmail'));
	}


	//
	// Public handler operations
	//
	/**
	 * Displays the author dashboard.
	 * @param $args array
	 * @param $request PKPRequest
	 */
	function submission($args, $request) {
		// Pass the authorized submission on to the template.
		$this->setupTemplate($request);
		$templateMgr = TemplateManager::getManager($request);
		$submission = $this->getAuthorizedContextObject(ASSOC_TYPE_SUBMISSION);
		$templateMgr->assign('submission', $submission);

		// "View metadata" action.
		import('lib.pkp.controllers.modals.submissionMetadata.linkAction.AuthorViewMetadataLinkAction');
		$viewMetadataAction = new AuthorViewMetadataLinkAction($request, $submission->getId());
		$templateMgr->assign('viewMetadataAction', $viewMetadataAction);

		// Import submission file to define file stages.
		import('lib.pkp.classes.submission.SubmissionFile');

		// Workflow-stage specific "upload file" action.
		$currentStage = $submission->getStageId();
		$fileStage = $this->_fileStageFromWorkflowStage($currentStage);

		$templateMgr->assign('lastReviewRoundNumber', $this->_getLastReviewRoundNumbers($submission));

		$reviewRoundDao = DAORegistry::getDAO('ReviewRoundDAO');
		$externalReviewRounds = $reviewRoundDao->getBySubmissionId($submission->getId(), WORKFLOW_STAGE_ID_EXTERNAL_REVIEW);
		$templateMgr->assign('externalReviewRounds', $externalReviewRounds);

		// Get the last review round.
		$lastReviewRound = $reviewRoundDao->getLastReviewRoundBySubmissionId($submission->getId(), $currentStage);

		// Create and assign add file link action.
		if ($fileStage && is_a($lastReviewRound, 'ReviewRound')) {
			import('lib.pkp.controllers.api.file.linkAction.AddFileLinkAction');
			$uploadFileAction = new AddFileLinkAction(
				$request, $submission->getId(), $currentStage,
				array(ROLE_ID_AUTHOR), null, $fileStage, null, null, $lastReviewRound->getId());
			$templateMgr->assign('uploadFileAction', $uploadFileAction);
		}


		// If the submission is in or past the editorial stage,
		// assign the editor's copyediting emails to the template
		$submissionEmailLogDao = DAORegistry::getDAO('SubmissionEmailLogDAO');
		$user = $request->getUser();

		if ($submission->getStageId() >= WORKFLOW_STAGE_ID_EDITING) {
			$copyeditingEmails = $submissionEmailLogDao->getByEventType($submission->getId(), SUBMISSION_EMAIL_COPYEDIT_NOTIFY_AUTHOR, $user->getId());
			$templateMgr->assign('copyeditingEmails', $copyeditingEmails);
		}

		// Same for production stage.
		if ($submission->getStageId() == WORKFLOW_STAGE_ID_PRODUCTION) {
			$productionEmails = $submissionEmailLogDao->getByEventType($submission->getId(), SUBMISSION_EMAIL_PROOFREAD_NOTIFY_AUTHOR, $user->getId());
			$templateMgr->assign('productionEmails', $productionEmails);
		}

		// Define the notification options.
		$templateMgr->assign(
			'authorDashboardNotificationRequestOptions',
			$this->_getNotificationRequestOptions($submission)
		);

		$templateMgr->display('authorDashboard/authorDashboard.tpl');
	}


	/**
	 * Fetches information about a specific email and returns it.
	 * @param $args array
	 * @param $request Request
	 * @return JSONMessage JSON object
	 */
	function readSubmissionEmail($args, $request) {
		$submissionEmailLogDao = DAORegistry::getDAO('SubmissionEmailLogDAO');
		$user = $request->getUser();
		$submission = $this->getAuthorizedContextObject(ASSOC_TYPE_SUBMISSION);
		$submissionEmailId = $request->getUserVar('submissionEmailId');

		$submissionEmailFactory = $submissionEmailLogDao->getByEventType($submission->getId(), SUBMISSION_EMAIL_EDITOR_NOTIFY_AUTHOR, $user->getId());
		while ($email = $submissionEmailFactory->next()) { // validate the email id for this user.
			if ($email->getId() == $submissionEmailId) {
				$templateMgr = TemplateManager::getManager($request);
				$templateMgr->assign('submissionEmail', $email);
				return $templateMgr->fetchJson('authorDashboard/submissionEmail.tpl');
			}
		}
	}


	//
	// Protected helper methods
	//
	/**
	 * Setup common template variables.
	 */
	function setupTemplate($request) {
		parent::setupTemplate($request);
		AppLocale::requireComponents(
			LOCALE_COMPONENT_PKP_SUBMISSION,
			LOCALE_COMPONENT_APP_SUBMISSION,
			LOCALE_COMPONENT_APP_EDITOR,
			LOCALE_COMPONENT_PKP_GRID
		);
	}

	/**
	 * Get roles to assign to operations in this handler.
	 * @return array
	 */
	protected function _getAssignmentRoles() {
		return array(ROLE_ID_AUTHOR);
	}

	/**
	 * Get the SUBMISSION_FILE_... file stage based on the current
	 * WORKFLOW_STAGE_... workflow stage.
	 * @param $currentStage int WORKFLOW_STAGE_...
	 * @return int SUBMISSION_FILE_...
	 */
	protected function _fileStageFromWorkflowStage($currentStage) {
		switch ($currentStage) {
			case WORKFLOW_STAGE_ID_SUBMISSION:
				return SUBMISSION_FILE_SUBMISSION;
			case WORKFLOW_STAGE_ID_EXTERNAL_REVIEW:
				return SUBMISSION_FILE_REVIEW_REVISION;
			case WORKFLOW_STAGE_ID_EDITING:
				return SUBMISSION_FILE_FINAL;
			default:
				return null;
		}
	}

	/**
	 * Get the last review round numbers in an array by stage name.
	 * @param $submission Submission
	 * @return array(stageName => lastReviewRoundNumber, 0 iff none)
	 */
	protected function _getLastReviewRoundNumbers($submission) {
		$reviewRoundDao = DAORegistry::getDAO('ReviewRoundDAO');
		$lastExternalReviewRound = $reviewRoundDao->getLastReviewRoundBySubmissionId($submission->getId(), WORKFLOW_STAGE_ID_EXTERNAL_REVIEW);
		if ($lastExternalReviewRound) {
			$lastExternalReviewRoundNumber = $lastExternalReviewRound->getRound();
		} else {
			$lastExternalReviewRoundNumber = 0;
		}
		return array(
			'externalReview' => $lastExternalReviewRoundNumber
		);
	}

	/**
	 * Get the notification request options.
	 * @param $submission Submission
	 * @return array
	 */
	protected function _getNotificationRequestOptions($submission) {
		$submissionAssocTypeAndIdArray = array(ASSOC_TYPE_SUBMISSION, $submission->getId());
		return array(
			NOTIFICATION_LEVEL_TASK => array(
				NOTIFICATION_TYPE_SIGNOFF_COPYEDIT => $submissionAssocTypeAndIdArray,
				NOTIFICATION_TYPE_SIGNOFF_PROOF => $submissionAssocTypeAndIdArray,
				NOTIFICATION_TYPE_PENDING_EXTERNAL_REVISIONS => $submissionAssocTypeAndIdArray),
			NOTIFICATION_LEVEL_NORMAL => array(
				NOTIFICATION_TYPE_EDITOR_DECISION_ACCEPT => $submissionAssocTypeAndIdArray,
				NOTIFICATION_TYPE_EDITOR_DECISION_EXTERNAL_REVIEW => $submissionAssocTypeAndIdArray,
				NOTIFICATION_TYPE_EDITOR_DECISION_PENDING_REVISIONS => $submissionAssocTypeAndIdArray,
				NOTIFICATION_TYPE_EDITOR_DECISION_RESUBMIT => $submissionAssocTypeAndIdArray,
				NOTIFICATION_TYPE_EDITOR_DECISION_DECLINE => $submissionAssocTypeAndIdArray,
				NOTIFICATION_TYPE_EDITOR_DECISION_SEND_TO_PRODUCTION => $submissionAssocTypeAndIdArray),
			NOTIFICATION_LEVEL_TRIVIAL => array()
		);
	}
}

?>

<?php

/**
 * @file controllers/tab/workflow/PKPWorkflowTabHandler.inc.php
 *
 * Copyright (c) 2014 Simon Fraser University Library
 * Copyright (c) 2003-2014 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class PKPWorkflowTabHandler
 * @ingroup controllers_tab_workflow
 *
 * @brief Handle AJAX operations for workflow tabs.
 */

// Import the base Handler.
import('classes.handler.Handler');
import('lib.pkp.classes.core.JSONMessage');

// Access decision actions constants.
import('classes.workflow.EditorDecisionActionsManager');

class PKPWorkflowTabHandler extends Handler {

	/**
	 * Constructor
	 */
	function PKPWorkflowTabHandler() {
		parent::Handler();
		$this->addRoleAssignment(array(ROLE_ID_SUB_EDITOR, ROLE_ID_MANAGER, ROLE_ID_ASSISTANT), array('fetchTab'));
	}


	//
	// Extended methods from Handler
	//
	/**
	 * @copydoc PKPHandler::authorize()
	 */
	function authorize($request, &$args, $roleAssignments) {
		// Authorize stage id.
		import('classes.security.authorization.WorkflowStageAccessPolicy');
		$this->addPolicy(new WorkflowStageAccessPolicy($request, $args, $roleAssignments, 'submissionId', $this->_identifyStageId($request)));

		return parent::authorize($request, $args, $roleAssignments);
	}


	//
	// Public handler operations
	//
	/**
	 * Fetch the specified review round.
	 * @param $args array
	 * @param $request Request
	 * @return string
	 */
	function fetchTab($args, $request) {

		$this->setupTemplate($request);
		$templateMgr = TemplateManager::getManager($request);

		$stageId = $this->getAuthorizedContextObject(ASSOC_TYPE_WORKFLOW_STAGE);
		$templateMgr->assign('stageId', $stageId);

		$submission = $this->getAuthorizedContextObject(ASSOC_TYPE_SUBMISSION);
		$templateMgr->assign('submission', $submission);

		switch ($stageId) {
			case WORKFLOW_STAGE_ID_SUBMISSION:
				return $templateMgr->fetchJson('workflow/submission.tpl');
			case WORKFLOW_STAGE_ID_EXTERNAL_REVIEW:
				// Retrieve the authorized submission and stage id.
				$submission = $this->getAuthorizedContextObject(ASSOC_TYPE_SUBMISSION);
				$selectedStageId = $this->getAuthorizedContextObject(ASSOC_TYPE_WORKFLOW_STAGE);

				// Get all review rounds for this submission, on the current stage.
				$reviewRoundDao = DAORegistry::getDAO('ReviewRoundDAO');
				$reviewRoundsFactory = $reviewRoundDao->getBySubmissionId($submission->getId(), $selectedStageId);
				if (!$reviewRoundsFactory->wasEmpty()) {
					$reviewRoundsArray = $reviewRoundsFactory->toAssociativeArray();

					// Get the review round number of the last review round to be used
					// as the current review round tab index.
					$lastReviewRoundNumber = end($reviewRoundsArray)->getRound();
					$lastReviewRoundId = end($reviewRoundsArray)->getId();
					reset($reviewRoundsArray);

					// Add the round information to the template.
					$templateMgr->assign('reviewRounds', $reviewRoundsArray);
					$templateMgr->assign('lastReviewRoundNumber', $lastReviewRoundNumber);
					$templateMgr->assign('reviewRoundOp', 'externalReviewRound');

					if ($submission->getStageId() == $selectedStageId) {
						$dispatcher = $request->getDispatcher();
						$newRoundAction = new LinkAction(
							'newRound',
							new AjaxModal(
								$dispatcher->url(
									$request, ROUTE_COMPONENT, null,
									'modals.editorDecision.EditorDecisionHandler',
									'newReviewRound', null, array(
										'submissionId' => $submission->getId(),
										'decision' => SUBMISSION_EDITOR_DECISION_RESUBMIT,
										'stageId' => $selectedStageId,
										'reviewRoundId' => $lastReviewRoundId
									)
								),
								__('editor.submission.newRound'),
								'modal_add_item'
							),
							__('editor.submission.newRound'),
							'add_item_small'
						);
						$templateMgr->assign('newRoundAction', $newRoundAction);
					}
				}

				// Render the view.
				return $templateMgr->fetchJson('workflow/review.tpl');
			case WORKFLOW_STAGE_ID_EDITING:
				return $templateMgr->fetchJson('workflow/editorial.tpl');
			case WORKFLOW_STAGE_ID_PRODUCTION:
				$templateMgr = TemplateManager::getManager($request);
				$submission = $this->getAuthorizedContextObject(ASSOC_TYPE_SUBMISSION);
				$notificationRequestOptions = array(
					NOTIFICATION_LEVEL_NORMAL => array(
						NOTIFICATION_TYPE_VISIT_CATALOG => array(ASSOC_TYPE_SUBMISSION, $submission->getId()),
						NOTIFICATION_TYPE_APPROVE_SUBMISSION => array(ASSOC_TYPE_SUBMISSION, $submission->getId()),
					),
					NOTIFICATION_LEVEL_TRIVIAL => array()
				);

				$galleyDao = DAORegistry::getDAO('ArticleGalleyDAO');
				$galleys = $galleyDao->getBySubmissionId($submission->getId());
				$templateMgr->assign('galleys', $galleys);

				$templateMgr->assign('productionNotificationRequestOptions', $notificationRequestOptions);
				return $templateMgr->fetchJson('workflow/production.tpl');
		}
	}

	/**
	 * Setup variables for the template
	 * @param $request Request
	 */
	function setupTemplate($request) {
		parent::setupTemplate($request);
		AppLocale::requireComponents(LOCALE_COMPONENT_PKP_SUBMISSION, LOCALE_COMPONENT_APP_SUBMISSION, LOCALE_COMPONENT_APP_EDITOR, LOCALE_COMPONENT_PKP_GRID, LOCALE_COMPONENT_PKP_EDITOR);

		$router = $request->getRouter();

		$submission =& $this->getAuthorizedContextObject(ASSOC_TYPE_SUBMISSION);
		$stageId = $this->getAuthorizedContextObject(ASSOC_TYPE_WORKFLOW_STAGE);

		$templateMgr = TemplateManager::getManager($request);

		// Assign the authorized submission.
		$templateMgr->assign('submission', $submission);

		// Assign workflow stages related data.
		$templateMgr->assign('stageId', $stageId);
		$templateMgr->assign('submissionStageId', $submission->getStageId());

		// Get the right notifications type based on current stage id.
		$notificationMgr = new NotificationManager();
		$editorAssignmentNotificationType = $this->getEditorAssignmentNotificationTypeByStageId($stageId);

		// Define the workflow notification options.
		$notificationRequestOptions = array(
			NOTIFICATION_LEVEL_TASK => array(
				$editorAssignmentNotificationType => array(ASSOC_TYPE_SUBMISSION, $submission->getId())
			),
			NOTIFICATION_LEVEL_TRIVIAL => array()
		);

		$signoffNotificationType = $this->_getSignoffNotificationTypeByStageId($stageId);
		if (!is_null($signoffNotificationType)) {
			$notificationRequestOptions[NOTIFICATION_LEVEL_TASK][$signoffNotificationType] = array(ASSOC_TYPE_SUBMISSION, $submission->getId());
		}

		$templateMgr->assign('workflowNotificationRequestOptions', $notificationRequestOptions);
	}

	/**
	 * Return the editor assignment notification type based on stage id.
	 * @param $stageId int
	 * @return int
	 */
	protected function getEditorAssignmentNotificationTypeByStageId($stageId) {
		switch ($stageId) {
			case WORKFLOW_STAGE_ID_SUBMISSION:
				return NOTIFICATION_TYPE_EDITOR_ASSIGNMENT_SUBMISSION;
			case WORKFLOW_STAGE_ID_EXTERNAL_REVIEW:
				return NOTIFICATION_TYPE_EDITOR_ASSIGNMENT_EXTERNAL_REVIEW;
			case WORKFLOW_STAGE_ID_EDITING:
				return NOTIFICATION_TYPE_EDITOR_ASSIGNMENT_EDITING;
			case WORKFLOW_STAGE_ID_PRODUCTION:
				return NOTIFICATION_TYPE_EDITOR_ASSIGNMENT_PRODUCTION;
		}
		return null;
	}

	/**
	 * Return the signoff notification type based on stage id.
	 * @param $stageId
	 * @return int
	 */
	private function _getSignoffNotificationTypeByStageId($stageId) {
		switch ($stageId) {
			case WORKFLOW_STAGE_ID_EDITING:
				return NOTIFICATION_TYPE_SIGNOFF_COPYEDIT;
			case WORKFLOW_STAGE_ID_PRODUCTION:
				return NOTIFICATION_TYPE_SIGNOFF_PROOF;
		}
		return null;
	}

	/**
	 * Translate the requested operation to a stage id.
	 * @param $request Request
	 * @return integer One of the WORKFLOW_STAGE_* constants.
	 */
	protected function _identifyStageId($request) {
		if ($stageId = $request->getUserVar('stageId')) {
			return (int) $stageId;
		}

		// Retrieve the requested operation.
		$router = $request->getRouter();
		$operation = $router->getRequestedOp($request);

		// Translate the operation to a workflow stage identifier.
		$userGroupDao = DAORegistry::getDAO('UserGroupDAO');
		return $userGroupDao->getIdFromPath($operation);
	}
}

?>

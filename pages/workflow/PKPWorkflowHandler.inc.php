<?php

/**
 * @file pages/workflow/PKPWorkflowHandler.inc.php
 *
 * Copyright (c) 2014-2018 Simon Fraser University
 * Copyright (c) 2003-2018 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class WorkflowHandler
 * @ingroup pages_reviewer
 *
 * @brief Handle requests for the submssion workflow.
 */

import('classes.handler.Handler');
import('lib.pkp.classes.workflow.WorkflowStageDAO');


// import UI base classes
import('lib.pkp.classes.linkAction.LinkAction');
import('lib.pkp.classes.linkAction.request.AjaxModal');

abstract class PKPWorkflowHandler extends Handler {

	//
	// Implement template methods from PKPHandler
	//
	/**
	 * @copydoc PKPHandler::authorize()
	 */
	function authorize($request, &$args, $roleAssignments) {
		$router = $request->getRouter();
		$operation = $router->getRequestedOp($request);

		if ($operation == 'access') {
			// Authorize requested submission.
			import('lib.pkp.classes.security.authorization.internal.SubmissionRequiredPolicy');
			$this->addPolicy(new SubmissionRequiredPolicy($request, $args, 'submissionId'));

			// This policy will deny access if user has no accessible workflow stage.
			// Otherwise it will build an authorized object with all accessible
			// workflow stages and authorize user operation access.
			import('lib.pkp.classes.security.authorization.internal.UserAccessibleWorkflowStageRequiredPolicy');
			$this->addPolicy(new UserAccessibleWorkflowStageRequiredPolicy($request, WORKFLOW_TYPE_EDITORIAL));

			$this->markRoleAssignmentsChecked();
		} else {
			import('lib.pkp.classes.security.authorization.WorkflowStageAccessPolicy');
			$this->addPolicy(new WorkflowStageAccessPolicy($request, $args, $roleAssignments, 'submissionId', $this->identifyStageId($request, $args), WORKFLOW_TYPE_EDITORIAL));
		}

		return parent::authorize($request, $args, $roleAssignments);
	}

	/**
	 * @copydoc PKPHandler::initialize()
	 */
	function initialize($request) {
		$router = $request->getRouter();
		$operation = $router->getRequestedOp($request);

		if ($operation != 'access') {
			$this->setupTemplate($request);
		}

		// Call parent method.
		parent::initialize($request);
	}


	//
	// Public handler methods
	//
	/**
	 * Redirect users to their most appropriate
	 * submission workflow stage.
	 * @param $args array
	 * @param $request PKPRequest
	 */
	function access($args, $request) {
		$submission = $this->getAuthorizedContextObject(ASSOC_TYPE_SUBMISSION);
		$reviewRoundDao = DAORegistry::getDAO('ReviewRoundDAO');

		$currentStageId = $submission->getStageId();
		$accessibleWorkflowStages = $this->getAuthorizedContextObject(ASSOC_TYPE_ACCESSIBLE_WORKFLOW_STAGES);
		$workflowRoles = Application::getWorkflowTypeRoles();
		$editorialWorkflowRoles = $workflowRoles[WORKFLOW_TYPE_EDITORIAL];

		// Get the closest workflow stage that user has an assignment.
		$stagePath = null;
		$workingStageId = null;

		for ($workingStageId = $currentStageId; $workingStageId >= WORKFLOW_STAGE_ID_SUBMISSION; $workingStageId--) {
			if (isset($accessibleWorkflowStages[$workingStageId]) && array_intersect($editorialWorkflowRoles, $accessibleWorkflowStages[$workingStageId])) {
				break;
			}
		}

		// If no stage was found, user still have access to future stages of the
		// submission. Try to get the closest future workflow stage.
		if ($workingStageId == null) {
			for ($workingStageId = $currentStageId; $workingStageId <= WORKFLOW_STAGE_ID_PRODUCTION; $workingStageId++) {
				if (isset($accessibleWorkflowStages[$workingStageId]) && array_intersect($editorialWorkflowRoles, $accessibleWorkflowStages[$workingStageId])) {
					break;
				}
			}
		}

		assert(isset($workingStageId));

		$router = $request->getRouter();
		$request->redirectUrl($router->url($request, null, 'workflow', 'index', array($submission->getId(), $workingStageId)));
	}

	/**
	 * Show the workflow stage, with the stage path as an #anchor.
	 * @param $args array
	 * @param $request PKPRequest
	 */
	function index($args, $request) {
		$this->_displayWorkflow($args, $request);
	}

	/**
	 * Show the submission stage.
	 * @param $args array
	 * @param $request PKPRequest
	 */
	function submission($args, $request) {
		$this->_redirectToIndex($args, $request);
	}

	/**
	 * Show the external review stage.
	 * @param $args array
	 * @param $request PKPRequest
	 */
	function externalReview($args, $request) {
		$this->_redirectToIndex($args, $request);
	}

	/**
	 * Show the editorial stage
	 * @param $request PKPRequest
	 * @param $args array
	 */
	function editorial(&$args, $request) {
		$this->_redirectToIndex($args, $request);
	}

	/**
	 * Show the production stage
	 * @param $request PKPRequest
	 * @param $args array
	 */
	function production(&$args, $request) {
		$this->_redirectToIndex($args, $request);
	}

	/**
	 * Redirect all old stage paths to index
	 * @param $args array
	 * @param $request PKPRequest
	 */
	protected function _redirectToIndex(&$args, $request) {
		// Translate the operation to a workflow stage identifier.
		$submission = $this->getAuthorizedContextObject(ASSOC_TYPE_SUBMISSION);
		$router = $request->getRouter();
		$workflowPath = $router->getRequestedOp($request);
		$stageId = WorkflowStageDAO::getIdFromPath($workflowPath);
		$request->redirectUrl($router->url($request, null, 'workflow', 'index', array($submission->getId(), $stageId)));
	}

	/**
	 * Fetch JSON-encoded editor decision options.
	 * @param $args array
	 * @param $request Request
	 * @return JSONMessage JSON object
	 */
	function editorDecisionActions($args, $request) {
		AppLocale::requireComponents(LOCALE_COMPONENT_APP_EDITOR);
		$reviewRoundId = (int) $request->getUserVar('reviewRoundId');

		// Prepare the action arguments.
		$submission = $this->getAuthorizedContextObject(ASSOC_TYPE_SUBMISSION);
		$stageId = $this->getAuthorizedContextObject(ASSOC_TYPE_WORKFLOW_STAGE);

		$actionArgs = array(
			'submissionId' => $submission->getId(),
			'stageId' => (int) $stageId,
		);

		// If a review round was specified, include it in the args;
		// must also check that this is the last round or decisions
		// cannot be recorded.
		if ($reviewRoundId) {
			$actionArgs['reviewRoundId'] = $reviewRoundId;
			$reviewRoundDao = DAORegistry::getDAO('ReviewRoundDAO');
			$lastReviewRound = $reviewRoundDao->getLastReviewRoundBySubmissionId($submission->getId(), $stageId);
			$reviewRound = $reviewRoundDao->getById($reviewRoundId);
		} else {
			$lastReviewRound = null;
		}

		// If there is an editor assigned, retrieve stage decisions.
		$stageAssignmentDao = DAORegistry::getDAO('StageAssignmentDAO');
		$editorsStageAssignments = $stageAssignmentDao->getEditorsAssignedToStage($submission->getId(), $stageId);
		$dispatcher = $request->getDispatcher();
		$user = $request->getUser();

		$recommendOnly = $makeDecision = false;
		// if the user is assigned several times in an editorial role, check his/her assignments permissions i.e.
		// if the user is assigned with both possibilities: to only recommend as well as make decision
		foreach ($editorsStageAssignments as $editorsStageAssignment) {
			if ($editorsStageAssignment->getUserId() == $user->getId()) {
				if (!$editorsStageAssignment->getRecommendOnly()) {
					$makeDecision = true;
				} else {
					$recommendOnly = true;
				}
			}
		}

		// If user is not assigned to the submission,
		// see if the user is manager, and
		// if the group is recommendOnly
		if (!$recommendOnly && !$makeDecision) {
			$userGroupDao = DAORegistry::getDAO('UserGroupDAO');
			$userGroups = $userGroupDao->getByUserId($user->getId(), $request->getContext()->getId());
			while ($userGroup = $userGroups->next()) {
				if (in_array($userGroup->getRoleId(), array(ROLE_ID_MANAGER))) {
					if (!$userGroup->getRecommendOnly()) {
						$makeDecision = true;
					} else {
						$recommendOnly = true;
					}
				}
			}
		}

		import('lib.pkp.classes.linkAction.request.AjaxModal');
		$editorActions = array();
		$lastRecommendation = $allRecommendations = null;
		if (!empty($editorsStageAssignments) && (!$reviewRoundId || ($lastReviewRound && $reviewRoundId == $lastReviewRound->getId()))) {
			import('classes.workflow.EditorDecisionActionsManager');
			$editDecisionDao = DAORegistry::getDAO('EditDecisionDAO');
			$recommendationOptions = EditorDecisionActionsManager::getRecommendationOptions($stageId);
			// If this is a review stage and the user has "recommend only role"
			if (($stageId == WORKFLOW_STAGE_ID_EXTERNAL_REVIEW || $stageId == WORKFLOW_STAGE_ID_INTERNAL_REVIEW)) {
				if ($recommendOnly) {
					// Get the made editorial decisions from the current user
					$editorDecisions = $editDecisionDao->getEditorDecisions($submission->getId(), $stageId, $reviewRound->getRound(), $user->getId());
					// Get the last recommendation
					foreach ($editorDecisions as $editorDecision) {
						if (array_key_exists($editorDecision['decision'], $recommendationOptions)) {
							if ($lastRecommendation) {
								if ($editorDecision['dateDecided'] >= $lastRecommendation['dateDecided']) {
									$lastRecommendation = $editorDecision;
								}
							} else {
								$lastRecommendation = $editorDecision;
							}
						}
					}
					if ($lastRecommendation) {
						$lastRecommendation = __($recommendationOptions[$lastRecommendation['decision']]);
					}
					// Add the recommend link action.
					$editorActions[] =
						new LinkAction(
							'recommendation',
							new AjaxModal(
								$dispatcher->url(
									$request, ROUTE_COMPONENT, null,
									'modals.editorDecision.EditorDecisionHandler',
									'sendRecommendation', null, $actionArgs
								),
								$lastRecommendation ? __('editor.submission.changeRecommendation') : __('editor.submission.makeRecommendation'),
								'review_recommendation'
							),
							$lastRecommendation ? __('editor.submission.changeRecommendation') : __('editor.submission.makeRecommendation')
						);
				} elseif ($makeDecision) {
					// Get the made editorial decisions from all users
					$editorDecisions = $editDecisionDao->getEditorDecisions($submission->getId(), $stageId, $reviewRound->getRound());
					// Get all recommendations
					$recommendations = array();
					foreach ($editorDecisions as $editorDecision) {
						if (array_key_exists($editorDecision['decision'], $recommendationOptions)) {
							if (array_key_exists($editorDecision['editorId'], $recommendations)) {
								if ($editorDecision['dateDecided'] >= $recommendations[$editorDecision['editorId']]['dateDecided']) {
									$recommendations[$editorDecision['editorId']] = array('dateDecided' => $editorDecision['dateDecided'], 'decision' => $editorDecision['decision']);;
								}
							} else {
								$recommendations[$editorDecision['editorId']] = array('dateDecided' => $editorDecision['dateDecided'], 'decision' => $editorDecision['decision']);
							}
						}
					}
					$i = 0;
					foreach ($recommendations as $recommendation) {
						$allRecommendations .= $i == 0 ? __($recommendationOptions[$recommendation['decision']]) : ', ' . __($recommendationOptions[$recommendation['decision']]);
						$i++;
					}
				}
			}
			// Get the possible editor decisions for this stage
			$decisions = EditorDecisionActionsManager::getStageDecisions($request->getContext(), $stageId, $makeDecision);
			// Iterate through the editor decisions and create a link action
			// for each decision which as an operation associated with it.
			foreach($decisions as $decision => $action) {
				if (empty($action['operation'])) {
					continue;
				}
				$actionArgs['decision'] = $decision;
				$editorActions[] = new LinkAction(
					$action['name'],
					new AjaxModal(
						$dispatcher->url(
							$request, ROUTE_COMPONENT, null,
							'modals.editorDecision.EditorDecisionHandler',
							$action['operation'], null, $actionArgs
						),
						__($action['title'])
					),
				__($action['title'])
				);
			}
		}

		// Assign the actions to the template.
		$templateMgr = TemplateManager::getManager($request);
		$templateMgr->assign(array(
			'editorActions' => $editorActions,
			'editorsAssigned' => count($editorsStageAssignments) > 0,
			'stageId' => $stageId,
			'lastRecommendation' => $lastRecommendation,
			'allRecommendations' => $allRecommendations,
		));
		return $templateMgr->fetchJson('workflow/editorialLinkActions.tpl');
	}

	/**
	 * Fetch the JSON-encoded submission header.
	 * @param $args array
	 * @param $request Request
	 * @return JSONMessage JSON object
	 */
	function submissionHeader($args, $request) {
		$templateMgr = TemplateManager::getManager($request);
		return $templateMgr->fetchJson('workflow/submissionHeader.tpl');
	}

	/**
	 * Fetch the JSON-encoded submission progress bar.
	 * @param $args array
	 * @param $request Request
	 * @return JSONMessage JSON object
	 */
	function submissionProgressBar($args, $request) {
		$templateMgr = TemplateManager::getManager($request);
		$context = $request->getContext();

		$workflowStages = WorkflowStageDAO::getWorkflowStageKeysAndPaths();
		$stageNotifications = array();
		foreach (array_keys($workflowStages) as $stageId) {
			$stageNotifications[$stageId] = $this->notificationOptionsByStage($request->getUser(), $stageId, $context->getId());
		}

		$submission = $this->getAuthorizedContextObject(ASSOC_TYPE_SUBMISSION);

		$editDecisionDao = DAORegistry::getDAO('EditDecisionDAO'); /* @var $editDecisionDao EditDecisionDAO */
		$stageDecisions = $editDecisionDao->getEditorDecisions($submission->getId());

		$stagesWithDecisions = array();
		foreach ($stageDecisions as $decision) {
			$stagesWithDecisions[$decision['stageId']] = $decision['stageId'];
		}

		$workflowStages = WorkflowStageDAO::getStageStatusesBySubmission($submission, $stagesWithDecisions, $stageNotifications);
		$templateMgr->assign('workflowStages', $workflowStages);
		if ($this->isSubmissionReady($submission)) {
			$templateMgr->assign('submissionIsReady', true);
		}

		return $templateMgr->fetchJson('workflow/submissionProgressBar.tpl');
	}

	/**
	 * Setup variables for the template
	 * @param $request Request
	 */
	function setupTemplate($request) {
		parent::setupTemplate($request);
		AppLocale::requireComponents(LOCALE_COMPONENT_PKP_SUBMISSION, LOCALE_COMPONENT_APP_SUBMISSION, LOCALE_COMPONENT_APP_EDITOR, LOCALE_COMPONENT_PKP_GRID, LOCALE_COMPONENT_PKP_EDITOR);

		$router = $request->getRouter();

		$submission = $this->getAuthorizedContextObject(ASSOC_TYPE_SUBMISSION);
		$stageId = $this->getAuthorizedContextObject(ASSOC_TYPE_WORKFLOW_STAGE);
		$accessibleWorkflowStages = $this->getAuthorizedContextObject(ASSOC_TYPE_ACCESSIBLE_WORKFLOW_STAGES);

		// Construct array with workflow stages data.
		$workflowStages = WorkflowStageDAO::getWorkflowStageKeysAndPaths();

		$templateMgr = TemplateManager::getManager($request);

		// Assign the authorized submission.
		$templateMgr->assign('submission', $submission);

		// Assign workflow stages related data.
		$templateMgr->assign('stageId', $stageId);
		$templateMgr->assign('submissionStageId', $submission->getStageId());
		$templateMgr->assign('workflowStages', $workflowStages);

		if (isset($accessibleWorkflowStages[$stageId]) && array_intersect(array(ROLE_ID_MANAGER, ROLE_ID_SUB_EDITOR, ROLE_ID_ASSISTANT), $accessibleWorkflowStages[$stageId])) {
			import('controllers.modals.submissionMetadata.linkAction.SubmissionEntryLinkAction');
			$templateMgr->assign(
				'submissionEntryAction',
				new SubmissionEntryLinkAction($request, $submission->getId(), $stageId)
			);
		}

		if (isset($accessibleWorkflowStages[$stageId]) && array_intersect(array(ROLE_ID_MANAGER, ROLE_ID_SUB_EDITOR), $accessibleWorkflowStages[$stageId])) {
			import('lib.pkp.controllers.informationCenter.linkAction.SubmissionInfoCenterLinkAction');
			$templateMgr->assign(
				'submissionInformationCenterAction',
				new SubmissionInfoCenterLinkAction($request, $submission->getId())
			);
		}

		import('lib.pkp.controllers.modals.documentLibrary.linkAction.SubmissionLibraryLinkAction');
		$templateMgr->assign(
			'submissionLibraryAction',
			new SubmissionLibraryLinkAction($request, $submission->getId())
		);
	}

	//
	// Protected helper methods
	//

	/**
	 * Displays the workflow tab structure.
	 * @param $args array
	 * @param $request Request
	 */
	private function _displayWorkflow($args, $request) {
		$templateMgr = TemplateManager::getManager($request);
		$templateMgr->display('workflow/workflow.tpl');
	}

	/**
	 * Translate the requested operation to a stage id.
	 * @param $request Request
	 * @param $args array
	 * @return integer One of the WORKFLOW_STAGE_* constants.
	 */
	protected function identifyStageId($request, $args) {
		if ($stageId = $request->getUserVar('stageId')) {
			return (int) $stageId;
		}

		// Maintain the old check for previous path urls
		$router = $request->getRouter();
		$workflowPath = $router->getRequestedOp($request);
		$stageId = WorkflowStageDAO::getIdFromPath($workflowPath);
		if ($stageId) {
			return $stageId;
		}

		// Finally, retrieve the requested operation, if the stage id is
		// passed in via an argument in the URL, like index/submissionId/stageId
		$stageId = $args[1];

		// Translate the operation to a workflow stage identifier.
		assert(WorkflowStageDAO::getPathFromId($stageId) !== null);
		return $stageId;
	}

	/**
	 * Determine if a particular stage has a notification pending.  If so, return true.
	 * This is used to set the CSS class of the submission progress bar.
	 * @param $user PKPUser
	 * @param $stageId integer
	 * @param $contextId integer
	 * @return boolean
	 */
	protected function notificationOptionsByStage($user, $stageId, $contextId) {
		$submission = $this->getAuthorizedContextObject(ASSOC_TYPE_SUBMISSION);
		$notificationDao = DAORegistry::getDAO('NotificationDAO');

		$editorAssignmentNotificationType = $this->getEditorAssignmentNotificationTypeByStageId($stageId);

		$editorAssignments = $notificationDao->getByAssoc(ASSOC_TYPE_SUBMISSION, $submission->getId(), null, $editorAssignmentNotificationType, $contextId);

		// if the User has assigned TASKs in this stage check, return true
		if (!$editorAssignments->wasEmpty()) {
			return true;
		}

		// check for more specific notifications on those stages that have them.
		if ($stageId == WORKFLOW_STAGE_ID_PRODUCTION) {
			$submissionApprovalNotification = $notificationDao->getByAssoc(ASSOC_TYPE_SUBMISSION, $submission->getId(), null, NOTIFICATION_TYPE_APPROVE_SUBMISSION, $contextId);
			if (!$submissionApprovalNotification->wasEmpty()) {
				return true;
			}
		}

		return false;
	}


	//
	// Abstract protected methods.
	//
	/**
	* Return the editor assignment notification type based on stage id.
	* @param $stageId int
	* @return int
	*/
	abstract protected function getEditorAssignmentNotificationTypeByStageId($stageId);

	/**
	 * Checks whether or not the submission is ready to appear in catalog.
	 * @param $submission Submission
	 * @return boolean
	 */
	abstract protected function isSubmissionReady($submission);
}



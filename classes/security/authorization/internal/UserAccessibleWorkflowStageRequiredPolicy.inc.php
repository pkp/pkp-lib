<?php
/**
 * @file classes/security/authorization/internal/UserAccessibleWorkflowStageRequiredPolicy.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class UserAccessibleWorkflowStageRequiredPolicy
 * @ingroup security_authorization_internal
 *
 * @brief Policy to deny access if an user assigned workflow stage is not found.
 *
 */

import('lib.pkp.classes.security.authorization.AuthorizationPolicy');
import('lib.pkp.classes.workflow.WorkflowStageDAO');

class UserAccessibleWorkflowStageRequiredPolicy extends AuthorizationPolicy {
	/** @var PKPRequest */
	var $_request;

	/** @var string Workflow type. One of WORKFLOW_TYPE_... **/
	var $_workflowType;

	/**
	 * Constructor
	 * @param $request PKPRequest
	 * @param $workflowType string Which workflow the stage access must be granted
	 *  for. One of WORKFLOW_TYPE_*.
	 */
	function __construct($request, $workflowType = null) {
		AppLocale::requireComponents(LOCALE_COMPONENT_PKP_USER);
		parent::__construct('user.authorization.accessibleWorkflowStage');
		$this->_request = $request;
		$this->_workflowType = $workflowType;
	}

	//
	// Implement template methods from AuthorizationPolicy
	//
	/**
	 * @see AuthorizationPolicy::effect()
	 */
	function effect() {
		$request = $this->_request;
		$context = $request->getContext();
		$contextId = $context->getId();
		$user = $request->getUser();
		if (!is_a($user, 'User')) return AUTHORIZATION_DENY;

		$userId = $user->getId();
		$submission = $this->getAuthorizedContextObject(ASSOC_TYPE_SUBMISSION);

		$accessibleWorkflowStages = [];
		$workflowStages = Application::get()->getApplicationStages();
		$userService = Services::get('user');
		foreach ($workflowStages as $stageId) {
			$accessibleStageRoles = $userService->getAccessibleStageRoles($userId, $contextId, $submission, $stageId);
			if (!empty($accessibleStageRoles)) {
				$accessibleWorkflowStages[$stageId] = $accessibleStageRoles;
			}
		}

		$this->addAuthorizedContextObject(ASSOC_TYPE_ACCESSIBLE_WORKFLOW_STAGES, $accessibleWorkflowStages);

		// Does the user have a role which matches the requested workflow?
		if (!is_null($this->_workflowType)) {
			$workflowTypeRoles = Application::getWorkflowTypeRoles();
			foreach ($accessibleWorkflowStages as $stageId => $roles) {
				if (array_intersect($workflowTypeRoles[$this->_workflowType], $roles)) {
					return AUTHORIZATION_PERMIT;
				}
			}
			return AUTHORIZATION_DENY;

		// User has at least one role in any stage in any workflow
		} elseif (!empty($accessibleWorkflowStages)) {
			return AUTHORIZATION_PERMIT;
		}

		return AUTHORIZATION_DENY;
	}

}



<?php
/**
 * @file classes/security/authorization/AssignedStageRoleHandlerOperationPolicy.inc.php
 *
 * Copyright (c) 2014-2020 Simon Fraser University
 * Copyright (c) 2000-2020 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class AssignedStageRoleHandlerOperationPolicy
 * @ingroup security_authorization
 *
 * @brief Class to control access to handler operations based on assigned
 *  role(s) in a submission's workflow stage.
 */

import('lib.pkp.classes.security.authorization.RoleBasedHandlerOperationPolicy');

class AssignedStageRoleHandlerOperationPolicy extends RoleBasedHandlerOperationPolicy {

	/** @var int */
	var $_stageId;

	/**
	 * Constructor
	 * @param $request PKPRequest
	 * @param $roles array|integer either a single role ID or an array of role ids
	 * @param $operations array|string either a single operation or a list of operations that
	 *  this policy is targeting.
	 * @param $stageId int The stage ID to check for assigned roles
	 * @param $message string a message to be displayed if the authorization fails
	 * @param $allRoles boolean whether all roles must match ("all of") or whether it is
	 *  enough for only one role to match ("any of"). Default: false ("any of")
	 */
	function __construct($request, $roles, $operations, $stageId,
			$message = 'user.authorization.assignedStageRoleBasedAccessDenied',
			$allRoles = false) {
		parent::__construct($request, $roles, $operations, $message, $allRoles);

		$this->_stageId = $stageId;
	}

	//
	// Implement template methods from AuthorizationPolicy
	//
	/**
	 * @see AuthorizationPolicy::effect()
	 */
	function effect() {
		// Check whether the user has one of the allowed roles
		// assigned. If that's the case we'll permit access.
		// Get user roles grouped by context.
		$userRoles = $this->getAuthorizedContextObject(ASSOC_TYPE_ACCESSIBLE_WORKFLOW_STAGES);
		if (empty($userRoles) || empty($userRoles[$this->_stageId])) return AUTHORIZATION_DENY;

		if (!$this->_checkUserRoleAssignment($userRoles[$this->_stageId])) return AUTHORIZATION_DENY;
		if (!$this->_checkOperationWhitelist()) return AUTHORIZATION_DENY;

		return AUTHORIZATION_PERMIT;
	}
}



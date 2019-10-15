<?php
/**
 * @file classes/security/authorization/StageRolePolicy.inc.php
 *
 * Copyright (c) 2014-2019 Simon Fraser University
 * Copyright (c) 2000-2019 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class StageRolePolicy
 * @ingroup security_authorization
 *
 * @brief Class to check if the user has an assigned role on a specific
 *   submission stage.
 */

import('lib.pkp.classes.security.authorization.AuthorizationPolicy');

class StageRolePolicy extends AuthorizationPolicy {
	/** @var array */
	private $_roleIds;

	/** @var int|null */
	private $_stageId;

	/**
	 * Constructor
	 * @param array $roleIds The roles required to be authorized
	 * @param int $stageId The stage the role assignment is required on to be authorized.
	 *   Leave this null to check against the submission's currently active stage.
	 */
	function __construct($roleIds, $stageId = null) {
		parent::__construct('user.authorization.accessibleWorkflowStage');
		$this->_roleIds = $roleIds;
		$this->_stageId = $stageId;
	}

	//
	// Implement template methods from AuthorizationPolicy
	//
	/**
	 * @see AuthorizationPolicy::effect()
	 */
	function effect() {

		// Use the submission's current stage id if none is specified in policy
		if (!$this->_stageId) {
			$this->_stageId = $this->getAuthorizedContextObject(ASSOC_TYPE_SUBMISSION)->getData('stageId');
		}

		// Check whether the user has one of the allowed roles assigned in the correct stage
		$userAccessibleStages = (array) $this->getAuthorizedContextObject(ASSOC_TYPE_ACCESSIBLE_WORKFLOW_STAGES);

		if (array_key_exists($this->_stageId, $userAccessibleStages) && array_intersect($this->_roleIds, $userAccessibleStages[$this->_stageId])) {
			return AUTHORIZATION_PERMIT;
		}

		// A manager is granted access when they are not assigned in any other role
		if (empty($userAccessibleStages) && in_array(ROLE_ID_MANAGER, $this->getAuthorizedContextObject(ASSOC_TYPE_USER_ROLES))) {
			return AUTHORIZATION_PERMIT;
		}

		return AUTHORIZATION_DENY;
	}
}



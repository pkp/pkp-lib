<?php
/**
 * @file classes/security/authorization/internal/QueryAssignedToUserAccessPolicy.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class QueryAssignedToUserAccessPolicy
 * @ingroup security_authorization_internal
 *
 * @brief Class to control access to a query that is assigned to the current user
 *
 */

import('lib.pkp.classes.security.authorization.AuthorizationPolicy');

class QueryAssignedToUserAccessPolicy extends AuthorizationPolicy {
	/** @var PKPRequest */
	var $_request;

	/**
	 * Constructor
	 * @param $request PKPRequest
	 */
	function __construct($request) {
		parent::__construct('user.authorization.submissionQuery');
		$this->_request = $request;
	}

	//
	// Implement template methods from AuthorizationPolicy
	//
	/**
	 * @see AuthorizationPolicy::effect()
	 */
	function effect() {
		// A query should already be in the context.
		$query = $this->getAuthorizedContextObject(ASSOC_TYPE_QUERY);
		if (!is_a($query, 'Query')) return AUTHORIZATION_DENY;

		// Check that there is a currently logged in user.
		$user = $this->_request->getUser();
		if (!is_a($user, 'User')) return AUTHORIZATION_DENY;

		// Determine if the query is assigned to the user.
		$queryDao = DAORegistry::getDAO('QueryDAO'); /* @var $queryDao QueryDAO */
		if ($queryDao->getParticipantIds($query->getId(), $user->getId())) return AUTHORIZATION_PERMIT;

		// Managers are allowed to access discussions they are not participants in
		// as long as they have Manager-level access to the workflow stage
		$accessibleWorkflowStages = $this->getAuthorizedContextObject(ASSOC_TYPE_ACCESSIBLE_WORKFLOW_STAGES);
		$managerAssignments = array_intersect(array(ROLE_ID_MANAGER), $accessibleWorkflowStages[$query->getStageId()]);
		if (!empty($managerAssignments)) return AUTHORIZATION_PERMIT;

		// Otherwise, deny.
		return AUTHORIZATION_DENY;
	}
}



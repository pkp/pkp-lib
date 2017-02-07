<?php
/**
 * @file classes/security/authorization/UserRolesRequiredPolicy.inc.php
 *
 * Copyright (c) 2014-2017 Simon Fraser University
 * Copyright (c) 2000-2017 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class UserRolesRequiredPolicy
 * @ingroup security_authorization
 *
 * @brief Policy to build an authorized user roles object. Because we may have
 * users with no roles, we don't deny access when no user roles are found.
 */

import('lib.pkp.classes.security.authorization.AuthorizationPolicy');

class UserRolesRequiredPolicy extends AuthorizationPolicy {
	/** @var Request */
	var $_request;

	/**
	 * Constructor
	 *
	 * @param $request PKPRequest
	 */
	function __construct($request) {
		parent::__construct();
		$this->_request = $request;
	}


	//
	// Implement template methods from AuthorizationPolicy
	//
	/**
	 * @see AuthorizationPolicy::effect()
	 */
	function effect() {
		$request = $this->_request;
		$user = $request->getUser();

		if (!is_a($user, 'User')) {
			return AUTHORIZATION_DENY;
		}

		// Get all user roles.
		$roleDao = DAORegistry::getDAO('RoleDAO');
		$userRoles = $roleDao->getByUserIdGroupedByContext($user->getId());

		// Prepare an array with the context ids of the request.
		$application = PKPApplication::getApplication();
		$router = $request->getRouter();
		$context = $router->getContext($request);
		$contextRoles = $this->_getContextRoles($context?$context->getId():CONTEXT_ID_NONE, $userRoles);

		$this->addAuthorizedContextObject(ASSOC_TYPE_USER_ROLES, $contextRoles);
		return AUTHORIZATION_PERMIT;
	}

	/**
	 * Get the current context roles from all user roles.
	 * @param array $contextId
	 * @param array $userRoles
	 * @return mixed array or null
	 */
	function _getContextRoles($contextId, $userRoles) {
		// Adapt the role context based on the passed role id.
		$roleDao = DAORegistry::getDAO('RoleDAO');
		$contextRoles = array();

		// Check if user has site level or manager roles.
		if ($contextId == CONTEXT_ID_NONE &&
			array_key_exists(ROLE_ID_SITE_ADMIN, $userRoles[CONTEXT_ID_NONE])) {
			// site level role
			return array(ROLE_ID_SITE_ADMIN);
		}

		// Get the user roles related to the passed context.
		if (isset($userRoles[$contextId])) {
			// Filter the user roles to the found context id.
			$roleIds = array();
			foreach ($userRoles[$contextId] as $role) {
				$roleIds[] = $role->getRoleId();
			}
			return $roleIds;
		}

		return array();
	}
}

?>

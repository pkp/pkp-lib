<?php
/**
 * @file classes/security/authorization/UserRolesRequiredPolicy.inc.php
 *
 * Copyright (c) 2000-2011 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class UserRolesRequiredPolicy
 * @ingroup security_authorization
 *
 * @brief Policy to deny access if a context cannot be found in the request.
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
	function UserRolesRequiredPolicy(&$request) {
		parent::AuthorizationPolicy();
		$this->_request =& $request;
	}


	//
	// Implement template methods from AuthorizationPolicy
	//
	/**
	 * @see AuthorizationPolicy::effect()
	 */
	function effect() {
		$request =& $this->_request;
		$user =& $request->getUser();

		// Get all user roles.
		$roleDao =& DAORegistry::getDAO('RoleDAO');
		$userRoles = $roleDao->getByUserIdGroupedByContext($user->getId());

		if (empty($userRoles)) {
			return AUTHORIZATION_DENY;
		}

		// Prepare an array with the context ids of the request.
		$application =& PKPApplication::getApplication();
		$contextDepth = $application->getContextDepth();
		$router =& $request->getRouter();
		$roleContext = array();
		for ($contextLevel = 1; $contextLevel <= $contextDepth; $contextLevel++) {
			$context =& $router->getContext($request, $contextLevel);
			$roleContext[] = $context?$context->getId():CONTEXT_ID_NONE;
			unset($context);
		}

		$contextRoles = $this->_getContextRoles($roleContext, $contextDepth, $userRoles);

		$this->addAuthorizedContextObject(ASSOC_TYPE_USER_ROLES, $contextRoles);
		return AUTHORIZATION_PERMIT;
	}

	/**
	 * Get the current context roles from all user roles.
	 * @param array $roleContext
	 * @param int $contextDepth
	 * @param array $userRoles
	 * @return mixed array or null
	 */
	function _getContextRoles($roleContext, $contextDepth, $userRoles) {
		// Adapt the role context based on the passed role id.
		$workingRoleContext = $roleContext;
		$roleDao =& DAORegistry::getDAO('RoleDAO');
		$contextRoles = array();

		// Check if user has site level or manager roles.
		if ($contextDepth > 0 && array_key_exists(CONTEXT_ID_NONE, $userRoles)) {
			if (array_key_exists(ROLE_ID_SITE_ADMIN, $userRoles[CONTEXT_ID_NONE])) {
				// site level role
				$contextRoles[] = ROLE_ID_SITE_ADMIN;
			} elseif ($contextDepth == 2 &&
				array_key_exists(CONTEXT_ID_NONE, $userRoles[CONTEXT_ID_NONE]) &&
				array_key_exists($roleDao->getRoleIdFromPath('manager'), $userRoles[CONTEXT_ID_NONE][CONTEXT_ID_NONE])) {
				// This is a main context managerial role (i.e. conference-level).
				$contextRoles[] = $roleDao->getRoleIdFromPath('manager');
			}
		}

		// Get the user roles related to the passed context.
		for ($contextLevel = 1; $contextLevel <= $contextDepth; $contextLevel++) {
			$contextId = $workingRoleContext[$contextLevel-1];
			if (isset($userRoles[$contextId])) {
				// Filter the user roles to the found context id.
				$userRoles = $userRoles[$contextId];

				// If we reached the context depth, search for the role id.
				if ($contextLevel == $contextDepth) {
					foreach ($userRoles as $role) {
						$contextRoles[] = $role->getId();
					}
					return $contextRoles;
				}
			} else {
				// Context id not present in user roles array.
				return null;
			}
		}
	}
}

?>

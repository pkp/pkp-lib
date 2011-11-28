<?php
/**
 * @file classes/security/authorization/RoleBasedHandlerOperationPolicy.inc.php
 *
 * Copyright (c) 2000-2011 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class RoleBasedHandlerOperationPolicy
 * @ingroup security_authorization
 *
 * @brief Class to control access to handler operations via role based access
 *  control.
 */

import('lib.pkp.classes.security.authorization.HandlerOperationPolicy');

class RoleBasedHandlerOperationPolicy extends HandlerOperationPolicy {
	/** @var array the target roles */
	var $_roles = array();

	/** @var array the authorized user roles */
	var $_authorizedUserRoles = array();

	/** @var boolean */
	var $_allRoles;

	/** @var boolean */
	var $_bypassOperationCheck;

	/**
	 * Constructor
	 * @param $request PKPRequest
	 * @param $roles array|integer either a single role ID or an array of role ids
	 * @param $operations array|string either a single operation or a list of operations that
	 *  this policy is targeting.
	 * @param $message string a message to be displayed if the authorization fails
	 * @param $allRoles boolean whether all roles must match ("all of") or whether it is
	 *  enough for only one role to match ("any of").
	 * @param $bypassOperationCheck boolean only for backwards compatibility, don't use.
	 *  FIXME: remove this parameter once we've removed the HandlerValidatorRole
	 *  compatibility class, see #5868.
	 */
	function RoleBasedHandlerOperationPolicy(&$request, $roles, $operations,
			$message = 'user.authorization.roleBasedAccessDenied',
			$allRoles = false, $bypassOperationCheck = false) {
		parent::HandlerOperationPolicy($request, $operations, $message);

		// Make sure a single role doesn't have to be
		// passed in as an array.
		assert(is_integer($roles) || is_array($roles));
		if (!is_array($roles)) {
			$roles = array($roles);
		}
		$this->_roles = $roles;
		$this->_allRoles = $allRoles;
		$this->_bypassOperationCheck = $bypassOperationCheck;
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
		$request =& $this->getRequest();
		$user =& $request->getUser();
		if (!$user) return AUTHORIZATION_DENY;

		if (!$this->_checkUserRoleAssignment($user)) return AUTHORIZATION_DENY;

		// FIXME: Remove the "bypass operation check" code once we've removed the
		// HandlerValidatorRole compatibility class and make the operation
		// check unconditional, see #5868.
		if ($this->_bypassOperationCheck) {
			assert($this->getOperations() === array());
		} else {
			if (!$this->_checkOperationWhitelist()) return AUTHORIZATION_DENY;
		}

		// Set the authorized roles in the authorized context.
		$this->addAuthorizedContextObject(ASSOC_TYPE_AUTHORIZED_USER_ROLES, $this->_authorizedUserRoles);

		return AUTHORIZATION_PERMIT;
	}


	//
	// Private helper methods
	//
	/**
	 * Check whether the given user has been assigned
	 * to any of the allowed roles. If so then grant
	 * access.
	 * @param $user User
	 * @return boolean
	 */
	function _checkUserRoleAssignment(&$user) {
		// Prepare an array with the context ids of the request.
		$application =& PKPApplication::getApplication();
		$contextDepth = $application->getContextDepth();
		$request =& $this->getRequest();
		$router =& $request->getRouter();
		$roleContext = array();
		for ($contextLevel = 1; $contextLevel <= $contextDepth; $contextLevel++) {
			$context =& $router->getContext($request, $contextLevel);
			$roleContext[] = $context?$context->getId():CONTEXT_ID_NONE;
			unset($context);
		}

		// Get all user roles.
		$roleDao =& DAORegistry::getDAO('RoleDAO');
		$userRoles = $roleDao->getByUserIdGroupedByContext($user->getId());

		// Find all matching roles.
		$foundMatchingRole = false;
		foreach($this->_roles as $roleId) {
			$authorizedRole = $this->_getAuthorizedRole($roleId, $roleContext, $contextDepth, $userRoles);
			if ($authorizedRole) {
				// Add this role to the authorized roles array.
				$this->_authorizedUserRoles[$roleId] = $roleId;
			}
			unset($authorizedRole);
		}

		if ($this->_allRoles) {
			if (count($this->_roles) == count($this->_authorizedUserRoles)) {
				// When the "all roles" flag is switched on then
				// we can't have one missing role.
				return true;
			}
		} else {
			if (!empty($this->_authorizedUserRoles)) {
				// When the "all roles" flag is not set then
				// one matching role is enough to succeed.
				return true;
			}
		}

		// None of the roles matched or we needed all roles matching to succeed.
		return false;
	}

	/**
	 * Check the presence of an specific role in the
	 * user roles assignment and return it.
	 *
	 * @param $roleId integer
	 * @param $roleContext array role context ids to be used
	 * to check if an user has an specific role.
	 * @param $contextDepth integer context depth of the
	 *  current application.
	 * @param $userRoles array with all user roles, grouped by
	 * context ids.
	 * @return mixed Role or null
	 */
	function _getAuthorizedRole($roleId, $roleContext, $contextDepth, $userRoles) {
		// Adapt the role context based on the passed role id.
		$workingRoleContext = $roleContext;
		$roleDao =& DAORegistry::getDAO('RoleDAO');
		if ($contextDepth > 0) {
			// Correct context for site level or manager roles.
			if ($roleId == ROLE_ID_SITE_ADMIN) {
				// site level role
				for ($contextLevel = 1; $contextLevel <= $contextDepth; $contextLevel++) {
					$workingRoleContext[$contextLevel-1] = CONTEXT_ID_NONE;
				}
			} elseif ($roleId == $roleDao->getRoleIdFromPath('manager') && $contextDepth == 2) {
				// This is a main context managerial role (i.e. conference-level).
				$workingRoleContext[1] = CONTEXT_ID_NONE;
			}
		}

		// Check the role id in user roles.
		for ($contextLevel = 1; $contextLevel <= $contextDepth; $contextLevel++) {
			if (isset($userRoles[$workingRoleContext[$contextLevel-1]])) {
				// Filter the user roles to the found context id.
				$userRoles = $userRoles[$workingRoleContext[$contextLevel-1]];

				// If we reached the context depth, search for the role id.
				if ($contextLevel == $contextDepth) {
					if (isset($userRoles[$roleId])) {
						return $userRoles[$roleId];
					} else {
						return null;
					}
				}
			} else {
				// Context id not present in user roles array.
				return null;
			}
		}
	}
}

?>

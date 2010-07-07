<?php
/**
 * @file classes/security/authorization/HandlerOperationRolesPolicy.inc.php
 *
 * Copyright (c) 2000-2010 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class HandlerOperationRolesPolicy
 * @ingroup security_authorization
 *
 * @brief Class to control access to handler operations via role based access control.
 */

import('lib.pkp.classes.security.authorization.AuthorizationPolicy');

class HandlerOperationRolesPolicy extends AuthorizationPolicy {
	/** @var array the target roles */
	var $_roles = array();

	/** @var boolean */
	var $_allRoles;

	/** @var array the target operations */
	var $_operations = array();

	/** @var array */
	var $_roleContext = array();

	/** @var integer */
	var $_contextDepth;

	/** @var PKPUser */
	var $_user;

	/**
	 * Constructor
	 * @param $request PKPRequest
	 * @param $roles array|integer either a single role ID or an array of role ids
	 * @param $message string a message to be displayed if the authorization fails
	 * @param $operations array|string either a single operation or a list of operations that
	 *  this policy is targeting, a value of '*' means all operations of the handler are
	 *  being targeted.
	 * @param $allRoles boolean whether all roles must match ("all of") or whether it is
	 *  enough for only one role to match ("any of").
	 */
	function HandlerOperationRolesPolicy(&$request, $roles, $message = null, $operations = '*', $allRoles = false) {
		parent::AuthorizationPolicy($message);

		// 1) subject: roles

		// Make sure a single role doesn't have to be
		// passed in as an array.
		if (!is_array($roles)) {
			$roles = array($roles);
		}
		$this->_roles = $roles;
		$this->_allRoles = $allRoles;


		// 2) resource: handler operations

		// Only add handler operations if they are explicitly
		// specified. Adding no operations means that this
		// policy will match all operations.
		if ($operations !== '*') {
			// Make sure a single operation doesn't have to
			// be passed in as an array.
			if (!is_array($operations)) {
				$operations = array($operations);
			}
			$this->_operations = $operations;
		}

		// Initialize internal state.
		$sessionManager =& SessionManager::getManager();
		$session =& $sessionManager->getUserSession();
		$this->_user =& $session->getUser();
		if ($this->_user) {
			$application =& PKPApplication::getApplication();
			$router =& $request->getRouter();

			// Prepare the method call arguments for a
			// RoleDAO::roleExists() call, i.e. the context
			// ids plus the user id.
			$this->_contextDepth = $application->getContextDepth();
			for ($contextLevel = 1; $contextLevel <= $this->_contextDepth; $contextLevel++) {
				$context =& $router->getContext($request, $contextLevel);
				$this->_roleContext[] = ($context)?$context->getId():0;
				unset($context);
			}
			$this->_roleContext[] = $this->_user->getId();
		}
	}


	//
	// Implement template methods from AuthorizationPolicy
	//
	/**
	 * @see AuthorizationPolicy::applies()
	 */
	function applies() {
		// FIXME: implement check on operation
		return true;
	}

	/**
	 * @see AuthorizationPolicy::effect()
	 */
	function effect() {
		// Deny if no user is present.
		if (!$this->_user) return AUTHORIZATION_DENY;

		$foundMatchingRole = false;
		foreach($this->_roles as $roleId) {
			if ($this->_checkRole($roleId)) {
				$foundMatchingRole = true;
			} else {
				if ($this->_allRoles) {
					// We need all roles so one missing role is enough to fail.
					return AUTHORIZATION_DENY;
				}
			}
		}
		if ($foundMatchingRole) {
			return AUTHORIZATION_ALLOW;
		} else {
			return AUTHORIZATION_DENY;
		}
	}


	//
	// Private helper methods
	//
	/**
	 * Checks whether the current user has the given
	 * role assigned.
	 *
	 * @param $roleId integer
	 * @return boolean
	 */
	function _checkRole($roleId) {
		// Check the cache first.
		$cachedResponse = $this->retrieveCachedEffect($roleId);
		if (!is_null($cachedResponse)) return $cachedResponse;

		// Only continue if we don't have a cache hit.
		if ( !$this->_user ) return false;

		// Prepare the method arguments for a call to
		// RoleDAO::roleExists().
		$roleExistsArguments = $this->_roleContext;
		$roleExistsArguments[] = $roleId;

		$roleDao =& DAORegistry::getDAO('RoleDAO');
		if ($this->_contextDepth > 0) {
			// Correct context for site level or manager roles.
			if ( $roleId == ROLE_ID_SITE_ADMIN ) {
				// site level role
				for ($contextLevel = 1; $contextLevel <= $this->_contextDepth; $contextLevel++) {
					$roleExistsArguments[$contextLevel-1] = 0;
				}
			} elseif ( $roleId == $roleDao->getRoleIdFromPath('manager') && $this->_contextDepth == 2) {
				// main context managerial role (i.e. conference-level)
				$roleExistsArguments[1] = 0;
			}
		}

		// Call the role DAO.
		$response = (boolean)call_user_func_array(array($roleDao, 'roleExists'), $roleExistsArguments);

		// Cache the response then return.
		$this->cacheEffect($roleId, $response);
		return $response;
	}
}

?>

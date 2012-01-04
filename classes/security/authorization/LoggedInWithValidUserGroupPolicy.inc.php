<?php
/**
 * @file classes/security/authorization/LoggedInWithValidUserGroupPolicy.inc.php
 *
 * Copyright (c) 2000-2012 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class LoggedInWithValidUserGroupPolicy
 * @ingroup security_authorization
 *
 * @brief Class to that makes sure that a user is logged in with a valid
 *  user group and role assigned.
 */

import('lib.pkp.classes.security.authorization.AuthorizationPolicy');

class LoggedInWithValidUserGroupPolicy extends AuthorizationPolicy {
	/** @var PKPRequest */
	var $_request;

	/**
	 * Constructor
	 *
	 * @param $request PKPRequest
	 */
	function LoggedInWithValidUserGroupPolicy(&$request) {
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
		// Get the session
		$session =& $this->_request->getSession();

		// Retrieve the user from the session.
		$user =& $session->getUser();

		// Check that the user group exists and
		// that the currently logged in user has been
		// assigned to it.
		$userGroupDao = DAORegistry::getDAO('UserGroupDAO');

		// If any of the above objects is not present then
		// we deny access. This is regularly the case if the
		// user is not logged in (=no user object).
		foreach(array($session, $user, $userGroupDao) as $requiredObject) {
			if (is_null($requiredObject)) return AUTHORIZATION_DENY;
		}

		// Retrieve the acting as user group id saved
		// in the session.
		$actingAsUserGroupId = $session->getActingAsUserGroupId();

		// Get the context.
		$router =& $this->_request->getRouter();
		$context =& $router->getContext($this->_request);

		// Check whether the user still is in the group we found in the session.
		// This is necessary because the user might have switched contexts
		// also. User group assignments are per context and we have to make sure
		// that the user really has the role in the current context.
		if (is_integer($actingAsUserGroupId) && $actingAsUserGroupId > 0) {
			if (is_null($context)) {
				$application =& PKPApplication::getApplication();
				if ($application->getContextDepth() > 0) {
					// Handle site-wide user groups.
					$userInGroup = $userGroupDao->userInGroup(0, $user->getId(), $actingAsUserGroupId);
				} else {
					// Handle apps that don't use context.
					$userInGroup = $userGroupDao->userInGroup($user->getId(), $actingAsUserGroupId);
				}
			} else {
				// Handle context-specific user groups.
				$userInGroup = $userGroupDao->userInGroup($context->getId(), $user->getId(), $actingAsUserGroupId);
			}

			// Invalidate the current user group if the user is not in this
			// group for the requested context.
			if (!$userInGroup) {
				$actingAsUserGroupId = null;
			} else {
				// Retrieve the user group
				if (is_null($context)) {
					// Handle apps that don't use context or site-wide groups.
					$userGroup =& $userGroupDao->getById($actingAsUserGroupId);
				} else {
					// Handle context-specific groups.
					$userGroup =& $userGroupDao->getById($actingAsUserGroupId, $context->getId());
				}
			}
		}

		// Get the user's default group if no user group is set or
		// if the previous user group was invalid.
		if (!(is_integer($actingAsUserGroupId) && $actingAsUserGroupId > 0)) {
			// Retrieve the user's groups for the current context.
			if (is_null($context)) {
				// Handle apps that don't use context or site-wide groups.
				$userGroups =& $userGroupDao->getByUserId($user->getId());
			} else {
				// Handle context-specific groups.
				$userGroups =& $userGroupDao->getByUserId($user->getId(), $context->getId());
			}

			// We use the first user group as default user group.
			$defaultUserGroup =& $userGroups->next();
			$actingAsUserGroupId = $defaultUserGroup->getId();

			// Set the acting as user group
			$session->setActingAsUserGroupId($actingAsUserGroupId);
			$userGroup =& $defaultUserGroup;
		}

		// Deny access if we didn't find a valid user group for the user.
		if (!is_a($userGroup, 'UserGroup')) return AUTHORIZATION_DENY;

		// Add the user group to the authorization context
		$this->addAuthorizedContextObject(ASSOC_TYPE_USER_GROUP, $userGroup);
		return AUTHORIZATION_PERMIT;
	}
}

?>

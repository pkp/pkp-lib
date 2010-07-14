<?php

/**
 * @file controllers/api/user/RoleApiHandler.inc.php
 *
 * Copyright (c) 2000-2010 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class RoleApiHandler
 * @ingroup controllers_api_user
 *
 * @brief Class defining the headless AJAX API for backend role manipulation.
 */

// import the base Handler
import('lib.pkp.classes.handler.PKPHandler');

// import JSON class for API responses
import('lib.pkp.classes.core.JSON');

class RoleApiHandler extends PKPHandler {
	/**
	 * Constructor.
	 */
	function RoleApiHandler() {
		parent::PKPHandler();
	}


	//
	// Implement template methods from PKPHandler
	//
	/**
	 * @see PKPHandler::authorize()
	 */
	function authorize(&$request, &$args, $roleAssignments) {
		// FIXME: Add a user logged in policy here rather than checking the user in the operation.
		import('lib.pkp.classes.security.authorization.PublicHandlerOperationPolicy');
		$this->addPolicy(new PublicHandlerOperationPolicy($request, 'changeActingAsUserGroup'));
		return parent::authorize($request, $args, $roleAssignments);
	}


	//
	// Public handler methods
	//
	/**
	 * Change the user's current user group.
	 * @return string the serialized grid JSON message
	 */
	function changeActingAsUserGroup($args, &$request) {
		$errorMessage = '';

		// Check that the user group parameter is in the request
		if (isset($args['changedActingAsUserGroupId'])) {
			$user =& $request->getUser();
			if (is_a($user, 'User')) {
				// Check that the target user group exists and
				// that the currently logged in user has been
				// assigned to it.
				$changedActingAsUserGroupId = $args['changedActingAsUserGroupId'];
				$userGroupDao = DAORegistry::getDAO('UserGroupDAO');
				$router =& $request->getRouter();
				$context =& $router->getContext($request);
				if ($context) {
					// Handle context-specific user groups.
					$userInGroup = $userGroupDao->userInGroup($context->getId(), $user->getId(), $changedActingAsUserGroupId);
				} else {
					$application =& PKPApplication::getApplication();
					if ($application->getContextDepth() > 0) {
						// Handle site-wide user groups.
						$userInGroup = $userGroupDao->userInGroup(0, $user->getId(), $changedActingAsUserGroupId);
					} else{
						// Handle apps that don't have a context.
						$userInGroup = $userGroupDao->userInGroup($user->getId(), $changedActingAsUserGroupId);
					}
				}

				if ($userInGroup) {
					$sessionManager =& SessionManager::getManager();
					$session =& $sessionManager->getUserSession();
					$session->setActingAsUserGroupId($changedActingAsUserGroupId);
				} else {
					$errorMessage = 'common.actingAsUserGroup.userIsNotInTargetUserGroup';
				}
			} else {
				$errorMessage = 'common.actingAsUserGroup.userNotLoggedIn';
			}
		} else {
			$errorMessage = 'common.actingAsUserGroup.changedActingAsUserGroupIdNotSet';
		}

		// Return the result status.
		if (!empty($errorMessage)) {
			$json = new JSON('false', Locale::translate($errorMessage));
		} else {
			$json = new JSON('true');
		}
		return $json->getString();
	}
}
?>
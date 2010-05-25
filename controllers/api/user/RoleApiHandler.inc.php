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
	// Overridden methods from PKPHandler
	//
	/**
	 * @see PKPHandler::getRemoteOperations()
	 */
	function getRemoteOperations() {
		return array('changeActingAsUserGroup');
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
			$changedActingAsUserGroupId = $args['changedActingAsUserGroupId'];

			// Check that the target user group exists and
			// that the currently logged in user has been
			// assigned to it.
			$userGroupDao = DAORegistry::getDAO('UserGroupDAO');
			$application =& PKPApplication::getApplication();
			$user =& $request->getUser();
			if ($application->getContextDepth() > 0) {
				$router =& $request->getRouter();
				$context =& $router->getContext($request);
				if ($context) {
					$userInGroup = $userGroupDao->userInGroup($context->getId(), $user->getId(), $changedActingAsUserGroupId);
				} else {
					$errorMessage = 'common.actingAsUserGroup.missingContext';
				}
			} else {
				$userInGroup = $userGroupDao->userInGroup($user->getId(), $changedActingAsUserGroupId);
			}

			if (!$userInGroup) {
				$errorMessage = 'common.actingAsUserGroup.userIsNotInTargetUserGroup';
			} else {
				$sessionManager =& SessionManager::getManager();
				$session =& $sessionManager->getUserSession();
				$session->setActingAsUserGroupId($changedActingAsUserGroupId);
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
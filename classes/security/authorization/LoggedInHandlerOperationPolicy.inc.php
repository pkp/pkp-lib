<?php
/**
 * @file classes/security/authorization/LoggedInHandlerOperationPolicy.inc.php
 *
 * Copyright (c) 2000-2012 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class LoggedInHandlerOperationPolicy
 * @ingroup security_authorization
 *
 * @brief Class to that makes sure that a user is logged in.
 */

import('lib.pkp.classes.security.authorization.PublicHandlerOperationPolicy');

class LoggedInHandlerOperationPolicy extends PublicHandlerOperationPolicy {
	/**
	 * Constructor
	 *
	 * @param $request PKPRequest
	 * @param $operations array|string either a single operation or a list of operations that
	 *  this policy is targeting.
	 * @param $message string a message to be displayed if the authorization fails
	 */
	function LoggedInHandlerOperationPolicy(&$request, $operations, $message = null) {
		parent::PublicHandlerOperationPolicy($request, $operations, $message);
	}

	//
	// Implement template methods from AuthorizationPolicy
	//
	/**
	 * @see AuthorizationPolicy::effect()
	 */
	function effect() {
		// Retrieve the user from the session.
		$request =& $this->getRequest();
		$user =& $request->getUser();

		if (!is_a($user, 'User')) {
			return AUTHORIZATION_DENY;
		}

		// Execute handler operation checks.
		return parent::effect();
	}
}

?>

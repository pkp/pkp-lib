<?php
/**
 * @file classes/security/authorization/PKPAuthenticatedAccessPolicy.inc.php
 *
 * Copyright (c) 2000-2011 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class PKPAuthenticatedAccessPolicy
 * @ingroup security_authorization
 *
 * @brief Class to that makes sure that a user is logged in.
 */

import('lib.pkp.classes.security.authorization.PKPPublicAccessPolicy');

class PKPAuthenticatedAccessPolicy extends PKPPublicAccessPolicy {
	/**
	 * Constructor
	 *
	 * @param $request PKPRequest
	 * @param $operations array|string either a single operation or a list of operations that
	 *  this policy is targeting.
	 * @param $message string a message to be displayed if the authorization fails
	 */
	function PKPAuthenticatedAccessPolicy(&$request, $operations, $message = 'user.authorization.loginRequired') {
		parent::PKPPublicAccessPolicy($request, $operations, $message);
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

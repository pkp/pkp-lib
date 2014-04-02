<?php
/**
 * @file classes/security/authorization/PKPPublicAccessPolicy.inc.php
 *
 * Copyright (c) 2013-2014 Simon Fraser University Library
 * Copyright (c) 2000-2014 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class PKPPublicAccessPolicy
 * @ingroup security_authorization
 *
 * @brief Class to control access to handler operations based on an
 *  operation whitelist.
 */

import('lib.pkp.classes.security.authorization.HandlerOperationPolicy');

class PKPPublicAccessPolicy extends HandlerOperationPolicy {
	/**
	 * Constructor
	 * @param $request PKPRequest
	 * @param $operations array|string either a single operation or a list of operations that
	 *  this policy is targeting.
	 * @param $message string a message to be displayed if the authorization fails
	 */
	function PKPPublicAccessPolicy(&$request, $operations, $message = 'user.authorization.privateOperation') {
		parent::HandlerOperationPolicy($request, $operations, $message);
	}


	//
	// Implement template methods from AuthorizationPolicy
	//
	/**
	 * @see AuthorizationPolicy::effect()
	 */
	function effect() {
		if ($this->_checkOperationWhitelist()) {
			return AUTHORIZATION_PERMIT;
		} else {
			return AUTHORIZATION_DENY;
		}
	}
}

?>

<?php
/**
 * @file classes/security/authorization/ContextRequiredPolicy.inc.php
 *
 * Copyright (c) 2013-2015 Simon Fraser University Library
 * Copyright (c) 2000-2015 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class ContextRequiredPolicy
 * @ingroup security_authorization
 *
 * @brief Policy to deny access if a context cannot be found in the request.
 */

import('lib.pkp.classes.security.authorization.AuthorizationPolicy');

class ContextRequiredPolicy extends AuthorizationPolicy {
	/** @var PKPRouter */
	var $_request;

	/**
	 * Constructor
	 *
	 * @param $request PKPRequest
	 */
	function ContextRequiredPolicy(&$request, $message = 'user.authorization.contextRequired') {
		parent::AuthorizationPolicy($message);
		$this->_request =& $request;
	}


	//
	// Implement template methods from AuthorizationPolicy
	//
	/**
	 * @see AuthorizationPolicy::effect()
	 */
	function effect() {
		$router =& $this->_request->getRouter();
		if (is_object($router->getContext($this->_request))) {
			return AUTHORIZATION_PERMIT;
		} else {
			return AUTHORIZATION_DENY;
		}
	}
}

?>

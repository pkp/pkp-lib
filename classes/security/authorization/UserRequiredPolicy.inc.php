<?php
/**
 * @file classes/security/authorization/UserRequiredPolicy.inc.php
 *
 * Copyright (c) 2014-2019 Simon Fraser University
 * Copyright (c) 2000-2019 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class UserRequiredPolicy
 * @ingroup security_authorization
 *
 * @brief Policy to deny access if a context cannot be found in the request.
 */

import('lib.pkp.classes.security.authorization.AuthorizationPolicy');

class UserRequiredPolicy extends AuthorizationPolicy {
	/** @var PKPRouter */
	var $_request;

	/**
	 * Constructor
	 *
	 * @param $request PKPRequest
	 */
	function __construct($request, $message = 'user.authorization.loginRequired') {
		parent::__construct($message);
		$this->_request = $request;
		// Add advice
		$callOnDeny = array($this, 'checkUser', array());
		$this->setAdvice(AUTHORIZATION_ADVICE_CALL_ON_DENY, $callOnDeny);
	}

	/**
	 * Callback function to handle logged out user
	 *
	 * @param string $message
	 * @return mixed
	 */
	function checkUser() {
		import('lib.pkp.classes.core.JSONMessage');
		//Load locale
		AppLocale::requireComponents(LOCALE_COMPONENT_PKP_USER);
		$json = new JSONMessage(false, __('user.authorization.sessionExpired'));
		$httpAccepts = $_SERVER['HTTP_ACCEPT'];
		if(strpos($httpAccepts, 'application/json') !== false){
			echo $json->getString();
			exit;
		} else {
			Validation::redirectLogin();
		}
	}

	//
	// Implement template methods from AuthorizationPolicy
	//
	/**
	 * @see AuthorizationPolicy::effect()
	 */
	function effect() {
		if ($this->_request->getUser()) {
			return AUTHORIZATION_PERMIT;
		} else {
			return AUTHORIZATION_DENY;
		}
	}
}



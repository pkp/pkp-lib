<?php
/**
 * @file classes/security/authorization/AllowedHostsPolicy.inc.php
 *
 * Copyright (c) 2014-2022 Simon Fraser University
 * Copyright (c) 2000-2022 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class AllowedHostsPolicy
 * @ingroup security_authorization
 *
 * @brief Class to ensure allowed hosts, when configured, are respected. (pkp/pkp-lib#7649)
 */

import('lib.pkp.classes.security.authorization.AuthorizationPolicy');

class AllowedHostsPolicy extends AuthorizationPolicy {
	/** @var PKPRequest */
	var $_request;

	/**
	 * Constructor
	 *
	 * @param $request PKPRequest
	 */
	function __construct($request) {
		parent::__construct();
		$this->_request = $request;

		// Add advice
		$this->setAdvice(AUTHORIZATION_ADVICE_CALL_ON_DENY, [$this, 'callOnDeny', []]);
	}

	//
	// Implement template methods from AuthorizationPolicy
	//
	/**
	 * @see AuthorizationPolicy::applies()
	 */
	function applies() {
		return Config::getVar('general', 'allowed_hosts') != '';
	}

	/**
	 * @see AuthorizationPolicy::effect()
	 */
	function effect() {
		// The list of server hosts, when specified, is a JSON array. Decode it
		// and make it lowercase.
		$allowedHosts = Config::getVar('general', 'allowed_hosts');
		$allowedHosts = array_map('strtolower', json_decode($allowedHosts));
		$serverHost = $this->_request->getServerHost(null, false);
		return in_array(strtolower($serverHost), $allowedHosts) ? 
			AUTHORIZATION_PERMIT : AUTHORIZATION_DENY;
	}

	/**
	 * Handle a mismatch in the allowed hosts expectation.
	 */
	function callOnDeny() {
		http_response_code(400);
		error_log('Server host "' . $this->_request->getServerHost(null, false) . ' not allowed!');
                fatalError('400 Bad Request');
	}
}



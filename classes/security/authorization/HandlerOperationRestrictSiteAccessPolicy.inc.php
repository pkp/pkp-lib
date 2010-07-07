<?php
/**
 * @file classes/security/authorization/HandlerOperationRestrictSiteAccessPolicy.inc.php
 *
 * Copyright (c) 2000-2010 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class HandlerOperationRestrictSiteAccessPolicy
 * @ingroup security_authorization
 *
 * @brief Policy enforcing restricted site access when the context
 *  contains such a setting.
 */

import('lib.pkp.classes.security.authorization.AuthorizationPolicy');

class HandlerOperationRestrictSiteAccessPolicy extends AuthorizationPolicy {
	/** @var PKPRouter */
	var $_router;

	/**
	 * Constructor
	 *
	 * @param $request PKPRequest
	 */
	function HandlerOperationRestrictSiteAccessPolicy(&$request) {
		parent::AuthorizationPolicy();
		$this->_router =& $request->getRouter();
	}

	//
	// Implement template methods from AuthorizationPolicy
	//
	/**
	 * @see AuthorizationPolicy::applies()
	 */
	function applies() {
		$context =& $this->_router->getContext($request);
		return ( $context && $context->getSetting('restrictSiteAccess'));
	}

	/**
	 * @see AuthorizationPolicy::effect()
	 */
	function effect() {
		if (is_a($this->_router, 'PKPPageRouter')) {
			$page = $this->_router->getRequestedPage($request);
		} else {
			$page = null;
		}

		if (Validation::isLoggedIn() || in_array($page, $this->_getLoginExemptions())) {
			return AUTHORIZATION_ALLOW;
		} else {
			return AUTHORIZATION_DENY;
		}
	}

	//
	// Private helper method
	//
	/**
	 * Return the pages that can be accessed
	 * even while in restricted site mode.
	 *
	 * @return array
	 */
	function _getLoginExemptions() {
		return array('user', 'login', 'help');
	}
}

?>

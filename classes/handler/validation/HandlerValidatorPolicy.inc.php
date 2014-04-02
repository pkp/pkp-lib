<?php
/**
 * @file classes/handler/HandlerValidatorPolicy.inc.php
 *
 * Copyright (c) 2013-2014 Simon Fraser University Library
 * Copyright (c) 2003-2014 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class HandlerValidator
 * @ingroup security
 *
 * @brief Class to represent a policy based validation check.
 *
 * NB: This class is deprecated and only exists for backward compatibility.
 * Please use AuthorizationPolicy classes for authorization from now on.
 */

import('lib.pkp.classes.handler.validation.HandlerValidator');

class HandlerValidatorPolicy extends HandlerValidator {
	/** @var AuthorizationPolicy */
	var $_policy;

	/**
	 * Constructor.
	 * @param $policy AuthorizationPolicy
	 * @see HandlerValidator::HandlerValidator()
	 */
	function HandlerValidatorPolicy(&$policy, &$handler, $redirectToLogin = false, $message = null, $additionalArgs = array()) {
		$this->_policy =& $policy;
		parent::HandlerValidator($handler, $redirectToLogin, $message, $additionalArgs);
	}

	/**
	 * @see HandlerValidator::isValid()
	 */
	function isValid() {
		// Delegate to the AuthorizationPolicy
		if (!$this->_policy->applies()) return false;
		// Pass the authorized context to the police.
		$this->_policy->setAuthorizedContext($this->handler->getAuthorizedContext());
		if ($this->_policy->effect() == AUTHORIZATION_DENY) {
			return false;
		} else {
			return true;
		}
	}
}

?>

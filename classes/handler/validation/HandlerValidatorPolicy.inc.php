<?php

/**
 * @file classes/handler/validation/HandlerValidatorPolicy.inc.php
 *
 * Copyright (c) 2003-2013 John Willinsky
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
	 * @copydoc HandlerValidator::isValid()
	 */
	function isValid() {
		// Delegate to the AuthorizationPolicy
		if (!$this->_policy->applies()) return false;
		// Pass the authorized context to the police.
		$authorizedContext = $this->handler->getAuthorizedContext();
		$this->_policy->setAuthorizedContext($authorizedContext);
		if ($this->_policy->effect() == AUTHORIZATION_DENY) {
			return false;
		} else {
			return true;
		}
	}
}

?>

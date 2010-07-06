<?php
/**
 * @file classes/handler/HandlerValidatorRoles.inc.php
 *
 * Copyright (c) 2003-2010 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class HandlerValidator
 * @ingroup security
 *
 * @brief Class to represent a page validation check.
 *
 * NB: This class is deprecated in favor of the HandlerOperationPolicy.
 */

import('lib.pkp.classes.handler.validation.HandlerValidator');
import('lib.pkp.classes.security.authorization.RoleAuthorizationContextHandler');

class HandlerValidatorRoles extends HandlerValidator {
	var $_roles;

	var $_all;

	/**
	 * Constructor.
	 * @param $handler Handler the associated form
	 * @param $roles array of role id's
	 * @param $all bool flag for whether all roles must exist or just 1
	 */
	function HandlerValidatorRoles(&$handler, $redirectLogin = true, $message = null, $additionalArgs = array(), $roles, $all = false) {
		parent::HandlerValidator($handler, $redirectLogin, $message, $additionalArgs);
		$this->_roles = $roles;
		$this->_all = $all;
	}

	/**
	 * Check if field value is valid.
	 * Value is valid if it is empty and optional or validated by user-supplied function.
	 * @return boolean
	 */
	function isValid() {
		// Delegate to the new RoleAuthorizationContextHandler
		$authorizationContextHandler =& new RoleAuthorizationContextHandler();
		$isValid = false;
		foreach($this->_roles as $role) {
			$roleInContext = $authorizationContextHandler->checkAttribute($role);
			if ($roleInContext) {
				$isValid = true;
				if (!$this->_all) break;
			} else {
				if ($this->_all) {
					$isValid = false;
					break;
				}
			}
		}
		return $isValid;
	}
}

?>

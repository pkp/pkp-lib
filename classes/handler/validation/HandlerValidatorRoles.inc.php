<?php
/**
 * @file classes/handler/HandlerValidatorPress.inc.php
 *
 * Copyright (c) 2003-2010 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class HandlerValidator
 * @ingroup security
 *
 * @brief Class to represent a page validation check.
 */

import('handler.validation.HandlerValidator');

class HandlerValidatorRoles extends HandlerValidator {
	var $roles;

	var $all;

	/**
	 * Constructor.
	 * @param $handler Handler the associated form
	 * @param $roles array of role id's
	 * @param $all bool flag for whether all roles must exist or just 1
	 */
	function HandlerValidatorRoles(&$handler, $redirectLogin = true, $message = null, $additionalArgs = array(), $roles, $all = false) {
		parent::HandlerValidator($handler, $redirectLogin, $message, $additionalArgs);
		$this->roles = $roles;
		$this->all = $all;
	}

	/**
	 * Check if field value is valid.
	 * Value is valid if it is empty and optional or validated by user-supplied function.
	 * @return boolean
	 */
	function isValid() {
		$press =& Request::getPress();
		$pressId = ($press)?$press->getId():0;

		$user = Request::getUser();
		if ( !$user ) return false;

		$roleDao =& DAORegistry::getDAO('RoleDAO');
		$returner = true;
		foreach ( $this->roles as $roleId ) {
			if ( $roleId == ROLE_ID_SITE_ADMIN ) {
				$exists = $roleDao->userHasRole(0, $user->getId(), $roleId);
			} else {
				$exists = $roleDao->userHasRole($pressId, $user->getId(), $roleId);
			}
			if ( !$this->all && $exists) return true;
			$returner = $returner && $exists;
		}

		return $returner;
	}
}

?>
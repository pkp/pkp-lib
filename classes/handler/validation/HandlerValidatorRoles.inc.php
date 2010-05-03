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
 */

import('lib.pkp.classes.handler.validation.HandlerValidator');

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
		$application =& PKPApplication::getApplication();
		$request =& $application->getRequest();

		$user = $request->getUser();
		if ( !$user ) return false;

		$roleContext = array();
		$contextList = $application->getContextList();
		$router =& $request->getRouter();
		foreach($contextList as $contextName) {
			$context =& $router->getContextByName($request, $contextName);
			$roleContext[] = ($context)?$context->getId():0;
			unset($context);
		}
		$roleContext[] = $user->getId();

		$contextDepth = $application->getContextDepth();
		$roleDao =& DAORegistry::getDAO('RoleDAO');
		$roleExistsCall = array($roleDao, 'roleExists');

		$returner = true;
		foreach ( $this->roles as $roleId ) {
			$roleExistsArguments = $roleContext;
			$roleExistsArguments[] = $roleId;

			if ($contextDepth > 0) {
				// Correct context for site level or manager
				// roles.
				if ( $roleId == ROLE_ID_SITE_ADMIN ) {
					// site level role
					for ($contextLevel = 1; $contextLevel <= $contextDepth; $contextLevel++) {
						$roleExistsArguments[$contextLevel-1] = 0;
					}
				} elseif ( $roleId == $roleDao->getRoleIdFromPath('manager') && $contextDepth == 2) {
					// main context managerial role (i.e. conference-level)
					$roleExistsArguments[1] = 0;
				}
			}

			$exists = call_user_func_array($roleExistsCall, $roleExistsArguments);
			if ( !$this->all && $exists) return true;
			$returner = $returner && $exists;
		}

		return $returner;
	}
}

?>

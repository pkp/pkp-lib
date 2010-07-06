<?php
/**
 * @file classes/security/authorization/HandlerOperationPolicy.inc.php
 *
 * Copyright (c) 2000-2010 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class HandlerOperationPolicy
 * @ingroup security_authorization
 *
 * @brief Class to control access to handler operations.
 */

import('lib.pkp.classes.security.authorization.AuthorizationPolicy');

class HandlerOperationPolicy extends AuthorizationPolicy {
	/**
	 * Constructor
	 */
	function HandlerOperationPolicy($roles, $message = null, $operations = '*', $allRoles = false) {
		parent::AuthorizationPolicy($message);

		// 1) subject attribute: roles

		// Make sure a single role doesn't have to be
		// passed in as an array.
		if (!is_array($roles)) {
			$roles = array($roles);
		}

		// Should all roles be present?
		if ($allRoles) {
			// Add the roles as "all of" target.
			foreach($roles as $role) {
				$this->addTargetAttribute('role', $role);
			}
		} else {
			// Add the roles as "any of" target.
			$this->addTargetAttribute('role', $roles);
		}


		// 2) resource attribute: handler operations

		// Only add handler operations if they are explicitly
		// specified. Adding no operations means that this
		// policy will match all operations.
		if ($operations !== '*') {
			// Make sure a single operation doesn't have to
			// be passed in as an array.
			if (!is_array($operations)) {
				$operations = array($operations);
			}

			// Add the operations as "any of" target.
			$this->addTargetAttribute('operation', $operations);
		}
	}
}

?>

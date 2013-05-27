<?php
/**
 * @file classes/security/authorization/internal/SignoffAssignedToUserAccessPolicy.inc.php
 *
 * Copyright (c) 2000-2013 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class SignoffAssignedToUserAccessPolicy
 * @ingroup security_authorization_internal
 *
 * @brief Class to control access to a signoff that is assigned to the current user
 *
 */

import('lib.pkp.classes.security.authorization.AuthorizationPolicy');

class SignoffAssignedToUserAccessPolicy extends AuthorizationPolicy {
	/** @var PKPRequest */
	var $_request;

	/**
	 * Constructor
	 * @param $request PKPRequest
	 */
	function SignoffAssignedToUserAccessPolicy($request) {
		parent::AuthorizationPolicy('user.authorization.submissionSignoff');
		$this->_request =& $request;
	}

	//
	// Implement template methods from AuthorizationPolicy
	//
	/**
	 * @see AuthorizationPolicy::effect()
	 */
	function effect() {
		// A signoff should already be in the context.
		$signoff = $this->getAuthorizedContextObject(ASSOC_TYPE_SIGNOFF);
		if (!is_a($signoff, 'Signoff')) return AUTHORIZATION_DENY;

		// Check that there is a currently logged in user.
		$user = $this->_request->getUser();
		if (!is_a($user, 'User')) return AUTHORIZATION_DENY;

		// Check if the signoff is assigned to the user.
		if ($signoff->getUserId() == $user->getId()) return AUTHORIZATION_PERMIT;

		// Otherwise, deny.
		return AUTHORIZATION_DENY;
	}
}

?>

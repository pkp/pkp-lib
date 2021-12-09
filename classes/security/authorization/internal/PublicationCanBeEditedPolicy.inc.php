<?php
/**
 * @file classes/security/authorization/internal/PublicationCanBeEditedPolicy.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PublicationCanBeEditedPolicy
 * @ingroup security_authorization_internal
 *
 * @brief Policy to ensure the authorized publication is editable by the given user
 *
 */

import('lib.pkp.classes.security.authorization.AuthorizationPolicy');

class PublicationCanBeEditedPolicy extends AuthorizationPolicy
{
	/** @var \User */
	private $_currentUser;

	public function __construct($request, $message)
	{
		parent::__construct($message);

		$currentUser = $request->getUser();
		$this->_currentUser = $currentUser;
	}

	/**
	 * @see AuthorizationPolicy::effect()
	 */
	public function effect()
	{
		$submission = $this->getAuthorizedContextObject(ASSOC_TYPE_SUBMISSION); /* @var $submission Submission */

		// Prevent users from editing publications if they do not have permission. Except for admins.
		$userRoles = $this->getAuthorizedContextObject(ASSOC_TYPE_USER_ROLES);
		if (in_array(ROLE_ID_SITE_ADMIN, $userRoles) || Services::get('submission')->canEditPublication($submission->getId(), $this->_currentUser->getId())) {
			return AUTHORIZATION_PERMIT;
		}

		return AUTHORIZATION_DENY;
	}
}



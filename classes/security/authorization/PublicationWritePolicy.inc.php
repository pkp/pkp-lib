<?php
/**
 * @file classes/security/authorization/PublicationWritePolicy.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PublicationWritePolicy
 * @ingroup security_authorization
 *
 * @brief Class to permit or deny write functions (add/edit) on a publication
 */

import('lib.pkp.classes.security.authorization.internal.ContextPolicy');
import('lib.pkp.classes.security.authorization.PublicationAccessPolicy');
import('lib.pkp.classes.security.authorization.StageRolePolicy');

class PublicationWritePolicy extends ContextPolicy {
	/**
	 * Constructor
	 * @param $request PKPRequest
	 * @param $args array request arguments
	 * @param $roleAssignments array
	 */
	function __construct($request, &$args, $roleAssignments) {
		parent::__construct($request);

		// Can the user access this publication?
		$this->addPolicy(new PublicationAccessPolicy($request, $args, $roleAssignments));

		// Is the user assigned to this submission in one of these roles, and does this role
		// have access to the _current_ stage of the submission?
		$this->addPolicy(new StageRolePolicy([ROLE_ID_MANAGER, ROLE_ID_SUB_EDITOR, ROLE_ID_ASSISTANT, ROLE_ID_AUTHOR]));
	}
}



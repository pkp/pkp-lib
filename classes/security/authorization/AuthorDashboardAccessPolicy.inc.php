<?php
/**
 * @file classes/security/authorization/AuthorDashboardAccessPolicy.inc.php
 *
 * Copyright (c) 2014-2015 Simon Fraser University Library
 * Copyright (c) 2000-2015 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class AuthorDashboardAccessPolicy
 * @ingroup security_authorization
 *
 * @brief Class to control access to author dashboard.
 */

import('lib.pkp.classes.security.authorization.internal.ContextPolicy');
import('lib.pkp.classes.security.authorization.PolicySet');

class AuthorDashboardAccessPolicy extends ContextPolicy {
	/**
	 * Constructor
	 * @param $request PKPRequest
	 * @param $args array request arguments
	 * @param $roleAssignments array
	 */
	function AuthorDashboardAccessPolicy($request, &$args, $roleAssignments) {
		parent::ContextPolicy($request);

		$authorDashboardPolicy = new PolicySet(COMBINING_DENY_OVERRIDES);

		// AuthorDashboard requires a valid submission in request.
		import('lib.pkp.classes.security.authorization.SubmissionAccessPolicy');
		$authorDashboardPolicy->addPolicy(new SubmissionAccessPolicy($request, $args, $roleAssignments), true);

		// Check if the user has an stage assignment with the submission in request.
		// Any workflow stage assignment is sufficient to access the author dashboard.
		import('lib.pkp.classes.security.authorization.internal.UserAccessibleWorkflowStageRequiredPolicy');
		$authorDashboardPolicy->addPolicy(new UserAccessibleWorkflowStageRequiredPolicy($request));

		$this->addPolicy($authorDashboardPolicy);
	}
}

?>

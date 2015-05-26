<?php
/**
 * @file classes/security/authorization/QueryAccessPolicy.inc.php
 *
 * Copyright (c) 2014-2015 Simon Fraser University Library
 * Copyright (c) 2000-2015 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class QueryAccessPolicy
 * @ingroup security_authorization
 *
 * @brief Class to control access to queries.
 */

import('lib.pkp.classes.security.authorization.internal.ContextPolicy');
import('lib.pkp.classes.security.authorization.RoleBasedHandlerOperationPolicy');

define('QUERY_ACCESS_READ', 1);
define('QUERY_ACCESS_MODIFY', 2);

class QueryAccessPolicy extends ContextPolicy {
	/**
	 * Constructor
	 * @param $request PKPRequest
	 * @param $args array request parameters
	 * @param $roleAssignments array
	 * @param $mode int bitfield QUERY_ACCESS_...
	 * @param $stageId int
	 */
	function QueryAccessPolicy($request, $args, $roleAssignments, $mode, $stageId) {
		parent::ContextPolicy($request);

		// We need a valid workflow stage.
		import('lib.pkp.classes.security.authorization.WorkflowStageAccessPolicy');
		$this->addPolicy(new WorkflowStageAccessPolicy($request, $args, $roleAssignments, 'submissionId', $stageId));

		// We need a query matching the submission in the request.
		import('lib.pkp.classes.security.authorization.internal.QueryRequiredPolicy');
		$this->addPolicy(new QueryRequiredPolicy($request, $args));

		// Authors, context managers and sub editors potentially have
		// access to queries. We'll have to define
		// differentiated policies for those roles in a policy set.
		$queryAccessPolicy = new PolicySet(COMBINING_PERMIT_OVERRIDES);

		//
		// Managerial role
		//
		if (isset($roleAssignments[ROLE_ID_MANAGER])) {
			// Managers have all access to all queries.
			$queryAccessPolicy->addPolicy(new RoleBasedHandlerOperationPolicy($request, ROLE_ID_MANAGER, $roleAssignments[ROLE_ID_MANAGER]));
		}

/* FIXME FIXME FIXME
		//
		// Assistants
		//
		if (isset($roleAssignments[ROLE_ID_ASSISTANT])) {

			// 1) Assistants can access all operations on queries...
			$assistantQueryAccessPolicy = new PolicySet(COMBINING_DENY_OVERRIDES);
			$assistantQueryAccessPolicy->addPolicy(new RoleBasedHandlerOperationPolicy($request, ROLE_ID_ASSISTANT, $roleAssignments[ROLE_ID_ASSISTANT]));

			// 2) ... but only if they have access to the workflow stage.
			import('lib.pkp.classes.security.authorization.WorkflowStageAccessPolicy'); // pulled from context-specific class path.
			$assistantQueryAccessPolicy->addPolicy(new WorkflowStageAccessPolicy($request, $args, $roleAssignments, 'submissionId', $stageId));
			$queryAccessPolicy->addPolicy($assistantQueryAccessPolicy);
		}


		//
		// Authors
		//
		if (isset($roleAssignments[ROLE_ID_AUTHOR])) {
fatalError('FIXME');
			if ($mode & QUERY_ACCESS_READ) {
				// 1) Authors can access read operations on queries...
				$authorQueryAccessPolicy = new PolicySet(COMBINING_DENY_OVERRIDES);
				$authorQueryAccessPolicy->addPolicy(new RoleBasedHandlerOperationPolicy($request, ROLE_ID_AUTHOR, $roleAssignments[ROLE_ID_AUTHOR]));

				// 2) ... but only if they are assigned to the workflow stage as an stage participant.
				import('lib.pkp.classes.security.authorization.WorkflowStageAccessPolicy');
				$authorQueryAccessPolicy->addPolicy(new WorkflowStageAccessPolicy($request, $args, $roleAssignments, 'submissionId', $stageId));
				$queryAccessPolicy->addPolicy($authorQueryAccessPolicy);
			}
		}

		//
		// Sub editor role
		//
		if (isset($roleAssignments[ROLE_ID_SUB_EDITOR])) {
fatalError('FIXME');
			// 1) Section editors can access all operations on queries ...
			$sectionEditorFileAccessPolicy = new PolicySet(COMBINING_DENY_OVERRIDES);
			$sectionEditorFileAccessPolicy->addPolicy(new RoleBasedHandlerOperationPolicy($request, ROLE_ID_SUB_EDITOR, $roleAssignments[ROLE_ID_SUB_EDITOR]));

			// 2) ... but only if the requested query submission is part of their section.
			import('lib.pkp.classes.security.authorization.internal.SectionAssignmentPolicy');
			$sectionEditorFileAccessPolicy->addPolicy(new SectionAssignmentPolicy($request));
			$queryAccessPolicy->addPolicy($sectionEditorFileAccessPolicy);
		}
FIXME FIXME FIXME */
		$this->addPolicy($queryAccessPolicy);

		return $queryAccessPolicy;
	}
}

?>

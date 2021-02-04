<?php
/**
 * @file classes/security/authorization/WorkflowStageAccessPolicy.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class WorkflowStageAccessPolicy
 * @ingroup security_authorization
 *
 * @brief Class to control access to OMP's submission workflow stage components
 */

import('lib.pkp.classes.security.authorization.internal.ContextPolicy');
import('lib.pkp.classes.security.authorization.PolicySet');
import('lib.pkp.classes.security.authorization.RoleBasedHandlerOperationPolicy');

class WorkflowStageAccessPolicy extends ContextPolicy {
	/**
	 * Constructor
	 * @param $request PKPRequest
	 * @param $args array request arguments
	 * @param $roleAssignments array
	 * @param $submissionParameterName string
	 * @param $stageId integer One of the WORKFLOW_STAGE_ID_* constants.
	 * @param $workflowType string|null Which workflow the stage access must be granted
	 *  for. One of WORKFLOW_TYPE_*.
	 */
	function __construct($request, &$args, $roleAssignments, $submissionParameterName, $stageId, $workflowType = null) {
		parent::__construct($request);

		// A workflow stage component requires a valid workflow stage.
		import('lib.pkp.classes.security.authorization.internal.WorkflowStageRequiredPolicy');
		$this->addPolicy(new WorkflowStageRequiredPolicy($stageId));

		// A workflow stage component can only be called if there's a
		// valid submission in the request.
		import('lib.pkp.classes.security.authorization.internal.SubmissionRequiredPolicy');
		$submissionRequiredPolicy = new SubmissionRequiredPolicy($request, $args, $submissionParameterName);
		$this->addPolicy($submissionRequiredPolicy);

		import('lib.pkp.classes.security.authorization.internal.UserAccessibleWorkflowStageRequiredPolicy');
		$this->addPolicy(new UserAccessibleWorkflowStageRequiredPolicy($request, $workflowType));

		// Users can access all whitelisted operations for submissions and workflow stages...
		$roleBasedPolicy = new PolicySet(COMBINING_PERMIT_OVERRIDES);
		foreach ($roleAssignments as $roleId => $operations) {
			$roleBasedPolicy->addPolicy(new RoleBasedHandlerOperationPolicy($request, $roleId, $operations));
		}
		$this->addPolicy($roleBasedPolicy);

		// ... if they can access the requested workflow stage.
		import('lib.pkp.classes.security.authorization.internal.UserAccessibleWorkflowStagePolicy');
		$this->addPolicy(new UserAccessibleWorkflowStagePolicy($stageId, $workflowType));
	}
}



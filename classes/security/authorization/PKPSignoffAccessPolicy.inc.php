<?php
/**
 * @file classes/security/authorization/PKPSignoffAccessPolicy.inc.php
 *
 * Copyright (c) 2000-2013 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class PKPSignoffAccessPolicy
 * @ingroup security_authorization
 *
 * @brief Class to control access to signoffs.
 */

import('lib.pkp.classes.security.authorization.internal.ContextPolicy');
import('lib.pkp.classes.security.authorization.RoleBasedHandlerOperationPolicy');

define('SIGNOFF_ACCESS_READ', 1);
define('SIGNOFF_ACCESS_MODIFY', 2);

class PKPSignoffAccessPolicy extends ContextPolicy {

	/** var $_baseSectionAccessPolicy the base policy for the signoff before _SUB_EDITOR is considered */
	var $_baseSectionAccessPolicy;

	/**
	 * Constructor
	 * @param $request PKPRequest
	 * @param $args array request parameters
	 * @param $roleAssignments array
	 * @param $mode int bitfield SIGNOFF_ACCESS_...
	 * @param $stageId int
	 */
	function PKPSignoffAccessPolicy($request, $args, $roleAssignments, $mode, $stageId) {
		parent::ContextPolicy($request);
		$this->_baseSectionAccessPolicy = $this->buildSignoffAccessPolicy($request, $args, $roleAssignments,$mode, $stageId);
	}

	/**
	 *
	 * @param PKPRequest $request
	 * @param array $args
	 * @param array $roleAssignments
	 * @param $mode int bitfield SIGNOFF_ACCESS_...
	 * @param $stageId int
	 */
	function buildSignoffAccessPolicy($request, $args, $roleAssignments, $mode, $stageId) {
		// We need a submission matching the file in the request.
		import('lib.pkp.classes.security.authorization.internal.SignoffExistsAccessPolicy');
		$this->addPolicy(new SignoffExistsAccessPolicy($request, $args));

		// We need a valid workflow stage.
		import('lib.pkp.classes.security.authorization.internal.WorkflowStageRequiredPolicy');
		$this->addPolicy(new WorkflowStageRequiredPolicy($stageId));

		// Authors, context managers and sub editors potentially have
		// access to signoffs. We'll have to define
		// differentiated policies for those roles in a policy set.
		$signoffAccessPolicy = new PolicySet(COMBINING_PERMIT_OVERRIDES);

		//
		// Managerial role
		//
		if (isset($roleAssignments[ROLE_ID_MANAGER])) {
			// Managers have all access to all signoffs.
			$signoffAccessPolicy->addPolicy(new RoleBasedHandlerOperationPolicy($request, ROLE_ID_MANAGER, $roleAssignments[ROLE_ID_MANAGER]));
		}

		//
		// Assistants
		//
		if (isset($roleAssignments[ROLE_ID_ASSISTANT])) {
			// 1) Assistants can access all operations on signoffs...
			$assistantSignoffAccessPolicy = new PolicySet(COMBINING_DENY_OVERRIDES);
			$assistantSignoffAccessPolicy->addPolicy(new RoleBasedHandlerOperationPolicy($request, ROLE_ID_ASSISTANT, $roleAssignments[ROLE_ID_ASSISTANT]));

			// 2) ... but only if they have access to the workflow stage.
			import('classes.security.authorization.WorkflowStageAccessPolicy'); // pulled from context-specific class path.
			$assistantSignoffAccessPolicy->addPolicy(new WorkflowStageAccessPolicy($request, $args, $roleAssignments, 'submissionId', $stageId));
			$signoffAccessPolicy->addPolicy($assistantSignoffAccessPolicy);
		}


		//
		// Authors
		//
		if (isset($roleAssignments[ROLE_ID_AUTHOR])) {
			if ($mode & SIGNOFF_ACCESS_READ) {
				// 1) Authors can access read operations on signoffs...
				$authorSignoffAccessPolicy = new PolicySet(COMBINING_DENY_OVERRIDES);
				$authorSignoffAccessPolicy->addPolicy(new RoleBasedHandlerOperationPolicy($request, ROLE_ID_AUTHOR, $roleAssignments[ROLE_ID_AUTHOR]));

				// 2) ... but only if they are assigned to the workflow stage as an stage participant.
				import('classes.security.authorization.WorkflowStageAccessPolicy');
				$authorSignoffAccessPolicy->addPolicy(new WorkflowStageAccessPolicy($request, $args, $roleAssignments, 'submissionId', $stageId));
				$signoffAccessPolicy->addPolicy($authorSignoffAccessPolicy);
			}
		}

		//
		// User owns the signoff (all roles): permit
		//
		import('lib.pkp.classes.security.authorization.internal.SignoffAssignedToUserAccessPolicy');
		$userOwnsSignoffPolicy = new SignoffAssignedToUserAccessPolicy($request);
		$signoffAccessPolicy->addPolicy($userOwnsSignoffPolicy);
		$this->addPolicy($signoffAccessPolicy);

		return $signoffAccessPolicy;
	}
}

?>

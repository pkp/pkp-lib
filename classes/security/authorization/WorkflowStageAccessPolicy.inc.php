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

namespace PKP\security\authorization;

use PKP\security\authorization\internal\ContextPolicy;
use PKP\security\authorization\internal\SubmissionRequiredPolicy;
use PKP\security\authorization\internal\UserAccessibleWorkflowStagePolicy;
use PKP\security\authorization\internal\UserAccessibleWorkflowStageRequiredPolicy;
use PKP\security\authorization\internal\WorkflowStageRequiredPolicy;

class WorkflowStageAccessPolicy extends ContextPolicy
{
    /**
     * Constructor
     *
     * @param PKPRequest $request
     * @param array $args request arguments
     * @param array $roleAssignments
     * @param string $submissionParameterName
     * @param int $stageId One of the WORKFLOW_STAGE_ID_* constants.
     * @param string|null $workflowType Which workflow the stage access must be granted
     *  for. One of PKPApplication::WORKFLOW_TYPE_*.
     */
    public function __construct($request, &$args, $roleAssignments, $submissionParameterName, $stageId, $workflowType = null)
    {
        parent::__construct($request);

        // A workflow stage component requires a valid workflow stage.
        $this->addPolicy(new WorkflowStageRequiredPolicy($stageId));

        // A workflow stage component can only be called if there's a
        // valid submission in the request.
        $submissionRequiredPolicy = new SubmissionRequiredPolicy($request, $args, $submissionParameterName);
        $this->addPolicy($submissionRequiredPolicy);

        $this->addPolicy(new UserAccessibleWorkflowStageRequiredPolicy($request, $workflowType));

        // Users can access all whitelisted operations for submissions and workflow stages...
        $roleBasedPolicy = new PolicySet(PolicySet::COMBINING_PERMIT_OVERRIDES);
        foreach ($roleAssignments as $roleId => $operations) {
            $roleBasedPolicy->addPolicy(new RoleBasedHandlerOperationPolicy($request, $roleId, $operations));
        }
        $this->addPolicy($roleBasedPolicy);

        // ... if they can access the requested workflow stage.
        $this->addPolicy(new UserAccessibleWorkflowStagePolicy($stageId, $workflowType));
    }
}

if (!PKP_STRICT_MODE) {
    class_alias('\PKP\security\authorization\WorkflowStageAccessPolicy', '\WorkflowStageAccessPolicy');
}

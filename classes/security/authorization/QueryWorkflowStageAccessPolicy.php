<?php
/**
 * @file classes/security/authorization/QueryWorkflowStageAccessPolicy.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class QueryWorkflowStageAccessPolicy
 * @ingroup security_authorization
 *
 * @brief Class to control access to submission workflow stage components related to queries
 */

namespace PKP\security\authorization;

use PKP\security\authorization\internal\ContextPolicy;
use PKP\security\authorization\internal\QueryUserAccessibleWorkflowStageRequiredPolicy;
use PKP\security\authorization\internal\SubmissionRequiredPolicy;
use PKP\security\authorization\internal\WorkflowStageRequiredPolicy;

class QueryWorkflowStageAccessPolicy extends ContextPolicy
{
    /**
     * Constructor
     *
     * @param PKPRequest $request
     * @param array $args request arguments
     * @param array $roleAssignments
     * @param string $submissionParameterName
     * @param int $stageId One of the WORKFLOW_STAGE_ID_* constants.
     */
    public function __construct($request, &$args, $roleAssignments, $submissionParameterName, $stageId)
    {
        parent::__construct($request);

        // A workflow stage component requires a valid workflow stage.
        $this->addPolicy(new WorkflowStageRequiredPolicy($stageId));

        // A workflow stage component can only be called if there's a
        // valid submission in the request.
        $this->addPolicy(new SubmissionRequiredPolicy($request, $args, $submissionParameterName));

        // Extends UserAccessibleWorkflowStagePolicy in order to permit users with review assignments
        // to access the reviews grid
        $this->addPolicy(new QueryUserAccessibleWorkflowStageRequiredPolicy($request));

        // Users can access all whitelisted operations for submissions and workflow stages...
        $roleBasedPolicy = new PolicySet(PolicySet::COMBINING_PERMIT_OVERRIDES);
        foreach ($roleAssignments as $roleId => $operations) {
            $roleBasedPolicy->addPolicy(new RoleBasedHandlerOperationPolicy($request, $roleId, $operations));
        }
        $this->addPolicy($roleBasedPolicy);
    }
}

if (!PKP_STRICT_MODE) {
    class_alias('\PKP\security\authorization\QueryWorkflowStageAccessPolicy', '\QueryWorkflowStageAccessPolicy');
}

<?php
/**
 * @file classes/security/authorization/ReviewStageAccessPolicy.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class ReviewStageAccessPolicy
 *
 * @ingroup security_authorization
 *
 * @brief Class to control access to review stage components
 */

namespace PKP\security\authorization;

use PKP\security\authorization\internal\ContextPolicy;
use PKP\security\authorization\internal\WorkflowStageRequiredPolicy;

class ReviewStageAccessPolicy extends ContextPolicy
{
    /**
     * Constructor
     *
     * @param PKPRequest $request
     * @param array $args request arguments
     * @param array $roleAssignments
     * @param string $submissionParameterName
     * @param int $stageId One of the WORKFLOW_STAGE_ID_* constants.
     * @param bool $permitDeclined Whether to permit reviewers to fetch declined review assignments.
     */
    public function __construct($request, &$args, $roleAssignments, $submissionParameterName, $stageId, $permitDeclined = false)
    {
        parent::__construct($request);

        // Create a "permit overrides" policy set that specifies
        // role-specific access to submission stage operations.
        $workflowStagePolicy = new PolicySet(PolicySet::COMBINING_PERMIT_OVERRIDES);

        // Add the workflow policy, for editorial / context roles
        $workflowStagePolicy->addPolicy(new WorkflowStageAccessPolicy($request, $args, $roleAssignments, $submissionParameterName, $stageId));

        if ($stageId == WORKFLOW_STAGE_ID_INTERNAL_REVIEW || $stageId == WORKFLOW_STAGE_ID_EXTERNAL_REVIEW) {
            // Add the submission policy, for reviewer roles
            $submissionPolicy = new SubmissionAccessPolicy($request, $args, $roleAssignments, $submissionParameterName, $permitDeclined);
            $submissionPolicy->addPolicy(new WorkflowStageRequiredPolicy($stageId));
            $workflowStagePolicy->addPolicy($submissionPolicy);
        }

        // Add the role-specific policies to this policy set.
        $this->addPolicy($workflowStagePolicy);
    }
}

if (!PKP_STRICT_MODE) {
    class_alias('\PKP\security\authorization\ReviewStageAccessPolicy', '\ReviewStageAccessPolicy');
}

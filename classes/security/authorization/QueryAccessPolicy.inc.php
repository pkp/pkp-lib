<?php
/**
 * @file classes/security/authorization/QueryAccessPolicy.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class QueryAccessPolicy
 * @ingroup security_authorization
 *
 * @brief Class to control access to queries.
 */

namespace PKP\security\authorization;

use PKP\security\authorization\internal\ContextPolicy;
use PKP\security\authorization\internal\QueryAssignedToUserAccessPolicy;
use PKP\security\authorization\internal\QueryRequiredPolicy;
use PKP\security\authorization\internal\QueryUserAccessibleWorkflowStageRequiredPolicy;
use PKP\security\Role;

class QueryAccessPolicy extends ContextPolicy
{
    /**
     * Constructor
     *
     * @param PKPRequest $request
     * @param array $args request parameters
     * @param array $roleAssignments
     * @param int $stageId
     */
    public function __construct($request, $args, $roleAssignments, $stageId)
    {
        parent::__construct($request);

        // We need a valid workflow stage.
        $this->addPolicy(new QueryWorkflowStageAccessPolicy($request, $args, $roleAssignments, 'submissionId', $stageId));

        // We need a query matching the submission in the request.
        $this->addPolicy(new QueryRequiredPolicy($request, $args));

        // The query must be assigned to the current user, with exceptions for Managers
        $this->addPolicy(new QueryAssignedToUserAccessPolicy($request));

        // Authors, reviewers, context managers and sub editors potentially have
        // access to queries. We'll have to define
        // differentiated policies for those roles in a policy set.
        $queryAccessPolicy = new PolicySet(PolicySet::COMBINING_PERMIT_OVERRIDES);

        //
        // Site Admin role
        //
        if (isset($roleAssignments[Role::ROLE_ID_SITE_ADMIN])) {
            // Site administrators have all access to all queries.
            $queryAccessPolicy->addPolicy(new RoleBasedHandlerOperationPolicy($request, Role::ROLE_ID_SITE_ADMIN, $roleAssignments[Role::ROLE_ID_SITE_ADMIN]));
        }

        //
        // Managerial role
        //
        if (isset($roleAssignments[Role::ROLE_ID_MANAGER])) {
            // Managers have all access to all queries.
            $queryAccessPolicy->addPolicy(new RoleBasedHandlerOperationPolicy($request, Role::ROLE_ID_MANAGER, $roleAssignments[Role::ROLE_ID_MANAGER]));
        }

        //
        // Assistants
        //
        if (isset($roleAssignments[Role::ROLE_ID_ASSISTANT])) {

            // 1) Assistants can access all operations on queries...
            $assistantQueryAccessPolicy = new PolicySet(PolicySet::COMBINING_DENY_OVERRIDES);
            $assistantQueryAccessPolicy->addPolicy(new RoleBasedHandlerOperationPolicy($request, Role::ROLE_ID_ASSISTANT, $roleAssignments[Role::ROLE_ID_ASSISTANT]));

            // 2) ... but only if they have access to the workflow stage.
            $assistantQueryAccessPolicy->addPolicy(new QueryWorkflowStageAccessPolicy($request, $args, $roleAssignments, 'submissionId', $stageId));

            $queryAccessPolicy->addPolicy($assistantQueryAccessPolicy);
        }

        //
        // Reviewers
        //
        if (isset($roleAssignments[Role::ROLE_ID_REVIEWER])) {
            // 1) Reviewers can access read operations on queries...
            $reviewerQueryAccessPolicy = new PolicySet(PolicySet::COMBINING_DENY_OVERRIDES);
            $reviewerQueryAccessPolicy->addPolicy(new RoleBasedHandlerOperationPolicy($request, Role::ROLE_ID_REVIEWER, $roleAssignments[Role::ROLE_ID_REVIEWER]));

            // 2) ... but only if they are assigned to the submissions as a reviewer
            $reviewerQueryAccessPolicy->addPolicy(new QueryWorkflowStageAccessPolicy($request, $args, $roleAssignments, 'submissionId', $stageId));

            $queryAccessPolicy->addPolicy($reviewerQueryAccessPolicy);
        }

        //
        // Authors
        //
        if (isset($roleAssignments[Role::ROLE_ID_AUTHOR])) {
            // 1) Authors can access read operations on queries...
            $authorQueryAccessPolicy = new PolicySet(PolicySet::COMBINING_DENY_OVERRIDES);
            $authorQueryAccessPolicy->addPolicy(new RoleBasedHandlerOperationPolicy($request, Role::ROLE_ID_AUTHOR, $roleAssignments[Role::ROLE_ID_AUTHOR]));

            // 2) ... but only if they are assigned to the workflow stage as an stage participant...
            $authorQueryAccessPolicy->addPolicy(new QueryWorkflowStageAccessPolicy($request, $args, $roleAssignments, 'submissionId', $stageId));

            $queryAccessPolicy->addPolicy($authorQueryAccessPolicy);
        }

        //
        // Sub editor role
        //
        if (isset($roleAssignments[Role::ROLE_ID_SUB_EDITOR])) {
            // 1) Sub editors can access all operations on submissions ...
            $subEditorQueryAccessPolicy = new PolicySet(PolicySet::COMBINING_DENY_OVERRIDES);
            $subEditorQueryAccessPolicy->addPolicy(new RoleBasedHandlerOperationPolicy($request, Role::ROLE_ID_SUB_EDITOR, $roleAssignments[Role::ROLE_ID_SUB_EDITOR]));

            // 2) ... but only if they have been assigned to the requested submission.
            $subEditorQueryAccessPolicy->addPolicy(new QueryUserAccessibleWorkflowStageRequiredPolicy($request));

            $queryAccessPolicy->addPolicy($subEditorQueryAccessPolicy);
        }
        $this->addPolicy($queryAccessPolicy);

        return $queryAccessPolicy;
    }
}

if (!PKP_STRICT_MODE) {
    class_alias('\PKP\security\authorization\QueryAccessPolicy', '\QueryAccessPolicy');
}

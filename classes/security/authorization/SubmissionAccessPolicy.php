<?php
/**
 * @file classes/security/authorization/SubmissionAccessPolicy.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class SubmissionAccessPolicy
 * @ingroup security_authorization
 *
 * @brief Base class to control (write) access to submissions and (read) access to
 * submission details in OMP.
 */

namespace PKP\security\authorization;

use PKP\security\authorization\internal\ContextPolicy;
use PKP\security\authorization\internal\ReviewAssignmentAccessPolicy;
use PKP\security\authorization\internal\SubmissionAuthorPolicy;
use PKP\security\authorization\internal\SubmissionRequiredPolicy;
use PKP\security\authorization\internal\UserAccessibleWorkflowStageRequiredPolicy;
use PKP\security\Role;

class SubmissionAccessPolicy extends ContextPolicy
{
    /**
     * Constructor
     *
     * @param PKPRequest $request
     * @param array $args request parameters
     * @param array $roleAssignments
     * @param string $submissionParameterName the request parameter we
     *  expect the submission id in.
     * @param bool $permitDeclined True iff declined reviews are permitted for viewing by reviewers
     */
    public function __construct($request, $args, $roleAssignments, $submissionParameterName = 'submissionId', $permitDeclined = false)
    {
        parent::__construct($request);

        // We need a submission in the request.
        $this->addPolicy(new SubmissionRequiredPolicy($request, $args, $submissionParameterName));

        // Authors, managers and sub editors potentially have
        // access to submissions. We'll have to define differentiated
        // policies for those roles in a policy set.
        $submissionAccessPolicy = new PolicySet(PolicySet::COMBINING_PERMIT_OVERRIDES);

        //
        // Site administrator role
        //
        if (isset($roleAssignments[Role::ROLE_ID_SITE_ADMIN])) {
            // Site administrators have access to all submissions.
            $submissionAccessPolicy->addPolicy(new RoleBasedHandlerOperationPolicy($request, Role::ROLE_ID_SITE_ADMIN, $roleAssignments[Role::ROLE_ID_SITE_ADMIN]));
        }

        //
        // Managerial role
        //
        if (isset($roleAssignments[Role::ROLE_ID_MANAGER])) {
            // Managers have access to all submissions.
            $submissionAccessPolicy->addPolicy(new RoleBasedHandlerOperationPolicy($request, Role::ROLE_ID_MANAGER, $roleAssignments[Role::ROLE_ID_MANAGER]));
        }

        //
        // Author role
        //
        if (isset($roleAssignments[Role::ROLE_ID_AUTHOR])) {
            // 1) Author role user groups can access whitelisted operations ...
            $authorSubmissionAccessPolicy = new PolicySet(PolicySet::COMBINING_DENY_OVERRIDES);
            $authorSubmissionAccessPolicy->addPolicy(new RoleBasedHandlerOperationPolicy($request, Role::ROLE_ID_AUTHOR, $roleAssignments[Role::ROLE_ID_AUTHOR], 'user.authorization.authorRoleMissing'));

            // 2) ... if they meet one of the following requirements:
            $authorSubmissionAccessOptionsPolicy = new PolicySet(PolicySet::COMBINING_PERMIT_OVERRIDES);

            // 2a) ...the requested submission is their own ...
            $authorSubmissionAccessOptionsPolicy->addPolicy(new SubmissionAuthorPolicy($request));

            // 2b) ...OR, at least one workflow stage has been assigned to them in the requested submission.
            $authorSubmissionAccessOptionsPolicy->addPolicy(new UserAccessibleWorkflowStageRequiredPolicy($request));

            $authorSubmissionAccessPolicy->addPolicy($authorSubmissionAccessOptionsPolicy);
            $submissionAccessPolicy->addPolicy($authorSubmissionAccessPolicy);
        }


        //
        // Reviewer role
        //
        if (isset($roleAssignments[Role::ROLE_ID_REVIEWER])) {
            // 1) Reviewers can access whitelisted operations ...
            $reviewerSubmissionAccessPolicy = new PolicySet(PolicySet::COMBINING_DENY_OVERRIDES);
            $reviewerSubmissionAccessPolicy->addPolicy(new RoleBasedHandlerOperationPolicy($request, Role::ROLE_ID_REVIEWER, $roleAssignments[Role::ROLE_ID_REVIEWER]));

            // 2) ... but only if they have been assigned to the submission as reviewers.
            $reviewerSubmissionAccessPolicy->addPolicy(new ReviewAssignmentAccessPolicy($request, $permitDeclined));
            $submissionAccessPolicy->addPolicy($reviewerSubmissionAccessPolicy);
        }

        //
        // Assistant role
        //
        if (isset($roleAssignments[Role::ROLE_ID_ASSISTANT])) {
            // 1) Assistants can access whitelisted operations ...
            $contextSubmissionAccessPolicy = new PolicySet(PolicySet::COMBINING_DENY_OVERRIDES);
            $contextSubmissionAccessPolicy->addPolicy(new RoleBasedHandlerOperationPolicy($request, Role::ROLE_ID_ASSISTANT, $roleAssignments[Role::ROLE_ID_ASSISTANT]));

            // 2) ... but only if they have been assigned to the submission workflow.
            $contextSubmissionAccessPolicy->addPolicy(new UserAccessibleWorkflowStageRequiredPolicy($request));
            $submissionAccessPolicy->addPolicy($contextSubmissionAccessPolicy);
        }

        //
        // Sub editor role
        //
        if (isset($roleAssignments[Role::ROLE_ID_SUB_EDITOR])) {
            // 1) Sub editors can access all operations on submissions ...
            $subEditorSubmissionAccessPolicy = new PolicySet(PolicySet::COMBINING_DENY_OVERRIDES);
            $subEditorSubmissionAccessPolicy->addPolicy(new RoleBasedHandlerOperationPolicy($request, Role::ROLE_ID_SUB_EDITOR, $roleAssignments[Role::ROLE_ID_SUB_EDITOR]));

            // 2b) ... but only if they have been assigned to the requested submission.
            $subEditorSubmissionAccessPolicy->addPolicy(new UserAccessibleWorkflowStageRequiredPolicy($request));

            $submissionAccessPolicy->addPolicy($subEditorSubmissionAccessPolicy);
        }

        $this->addPolicy($submissionAccessPolicy);

        return $submissionAccessPolicy;
    }
}

if (!PKP_STRICT_MODE) {
    class_alias('\PKP\security\authorization\SubmissionAccessPolicy', '\SubmissionAccessPolicy');
}

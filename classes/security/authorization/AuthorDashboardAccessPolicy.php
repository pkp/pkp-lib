<?php
/**
 * @file classes/security/authorization/AuthorDashboardAccessPolicy.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class AuthorDashboardAccessPolicy
 *
 * @ingroup security_authorization
 *
 * @brief Class to control access to author dashboard.
 */

namespace PKP\security\authorization;

use PKP\core\PKPApplication;
use PKP\core\PKPRequest;
use PKP\security\authorization\internal\ContextPolicy;
use PKP\security\authorization\internal\UserAccessibleWorkflowStageRequiredPolicy;

class AuthorDashboardAccessPolicy extends ContextPolicy
{
    /**
     * Constructor
     *
     * @param PKPRequest $request
     * @param array $args request arguments
     * @param array $roleAssignments
     */
    public function __construct($request, &$args, $roleAssignments)
    {
        parent::__construct($request);

        $authorDashboardPolicy = new PolicySet(PolicySet::COMBINING_DENY_OVERRIDES);

        // AuthorDashboard requires a valid submission in request.
        $authorDashboardPolicy->addPolicy(new SubmissionAccessPolicy($request, $args, $roleAssignments), true);

        // Check if the user has an stage assignment with the submission in request.
        // Any workflow stage assignment is sufficient to access the author dashboard.
        $authorDashboardPolicy->addPolicy(new UserAccessibleWorkflowStageRequiredPolicy($request, PKPApplication::WORKFLOW_TYPE_AUTHOR));

        $this->addPolicy($authorDashboardPolicy);
    }
}

if (!PKP_STRICT_MODE) {
    class_alias('\PKP\security\authorization\AuthorDashboardAccessPolicy', '\AuthorDashboardAccessPolicy');
}

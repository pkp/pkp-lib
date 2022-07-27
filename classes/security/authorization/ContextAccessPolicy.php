<?php
/**
 * @file classes/security/authorization/ContextAccessPolicy.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class ContextAccessPolicy
 * @ingroup security_authorization
 *
 * @brief Class to control access to PKP applications' setup components
 */

namespace PKP\security\authorization;

use PKP\security\authorization\internal\ContextPolicy;

class ContextAccessPolicy extends ContextPolicy
{
    /**
     * Constructor
     *
     * @param PKPRequest $request
     * @param array $roleAssignments
     */
    public function __construct($request, $roleAssignments)
    {
        parent::__construct($request);

        // On context level we don't have role-specific conditions
        // so we can simply add all role assignments. It's ok if
        // any of these role conditions permits access.
        $contextRolePolicy = new PolicySet(PolicySet::COMBINING_PERMIT_OVERRIDES);
        foreach ($roleAssignments as $role => $operations) {
            $contextRolePolicy->addPolicy(new RoleBasedHandlerOperationPolicy($request, $role, $operations));
        }
        $this->addPolicy($contextRolePolicy);
    }
}

if (!PKP_STRICT_MODE) {
    class_alias('\PKP\security\authorization\ContextAccessPolicy', '\ContextAccessPolicy');
}

<?php
/**
 * @file classes/security/authorization/internal/QueryAssignedToUserAccessPolicy.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class QueryAssignedToUserAccessPolicy
 *
 * @ingroup security_authorization_internal
 *
 * @brief Class to control access to a query that is assigned to the current user
 *
 */

namespace PKP\security\authorization\internal;

use APP\core\Application;
use PKP\db\DAORegistry;
use PKP\security\authorization\AuthorizationPolicy;
use PKP\security\Role;

class QueryAssignedToUserAccessPolicy extends AuthorizationPolicy
{
    /** @var PKPRequest */
    public $_request;

    /**
     * Constructor
     *
     * @param PKPRequest $request
     */
    public function __construct($request)
    {
        parent::__construct('user.authorization.submissionQuery');
        $this->_request = $request;
    }

    //
    // Implement template methods from AuthorizationPolicy
    //
    /**
     * @see AuthorizationPolicy::effect()
     */
    public function effect()
    {
        // A query should already be in the context.
        $query = $this->getAuthorizedContextObject(Application::ASSOC_TYPE_QUERY);
        if (!$query instanceof \PKP\query\Query) {
            return AuthorizationPolicy::AUTHORIZATION_DENY;
        }

        // Check that there is a currently logged in user.
        $user = $this->_request->getUser();
        if (!$user instanceof \PKP\user\User) {
            return AuthorizationPolicy::AUTHORIZATION_DENY;
        }

        // Determine if the query is assigned to the user.
        $queryDao = DAORegistry::getDAO('QueryDAO'); /** @var QueryDAO $queryDao */
        if ($queryDao->getParticipantIds($query->getId(), $user->getId())) {
            return AuthorizationPolicy::AUTHORIZATION_PERMIT;
        }

        // Managers are allowed to access discussions they are not participants in
        // as long as they have Manager-level access to the workflow stage
        $accessibleWorkflowStages = $this->getAuthorizedContextObject(Application::ASSOC_TYPE_ACCESSIBLE_WORKFLOW_STAGES);
        $managerAssignments = array_intersect([Role::ROLE_ID_MANAGER, Role::ROLE_ID_SITE_ADMIN], $accessibleWorkflowStages[$query->getStageId()] ?? []);
        if (!empty($managerAssignments)) {
            return AuthorizationPolicy::AUTHORIZATION_PERMIT;
        }

        // Otherwise, deny.
        return AuthorizationPolicy::AUTHORIZATION_DENY;
    }
}

if (!PKP_STRICT_MODE) {
    class_alias('\PKP\security\authorization\internal\QueryAssignedToUserAccessPolicy', '\QueryAssignedToUserAccessPolicy');
}

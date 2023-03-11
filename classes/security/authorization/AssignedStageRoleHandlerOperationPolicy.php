<?php
/**
 * @file classes/security/authorization/AssignedStageRoleHandlerOperationPolicy.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class AssignedStageRoleHandlerOperationPolicy
 * @ingroup security_authorization
 *
 * @brief Class to control access to handler operations based on assigned
 *  role(s) in a submission's workflow stage.
 */

namespace PKP\security\authorization;

use APP\core\Application;

class AssignedStageRoleHandlerOperationPolicy extends RoleBasedHandlerOperationPolicy
{
    /** @var int */
    public $_stageId;

    /**
     * Constructor
     *
     * @param PKPRequest $request
     * @param array|integer $roles either a single role ID or an array of role ids
     * @param array|string $operations either a single operation or a list of operations that
     *  this policy is targeting.
     * @param int $stageId The stage ID to check for assigned roles
     * @param string $message a message to be displayed if the authorization fails
     * @param bool $allRoles whether all roles must match ("all of") or whether it is
     *  enough for only one role to match ("any of"). Default: false ("any of")
     */
    public function __construct(
        $request,
        $roles,
        $operations,
        $stageId,
        $message = 'user.authorization.assignedStageRoleBasedAccessDenied',
        $allRoles = false
    ) {
        parent::__construct($request, $roles, $operations, $message, $allRoles);

        $this->_stageId = $stageId;
    }

    //
    // Implement template methods from AuthorizationPolicy
    //
    /**
     * @see AuthorizationPolicy::effect()
     */
    public function effect()
    {
        // Check whether the user has one of the allowed roles
        // assigned. If that's the case we'll permit access.
        // Get user roles grouped by context.
        $userRoles = $this->getAuthorizedContextObject(Application::ASSOC_TYPE_ACCESSIBLE_WORKFLOW_STAGES);
        if (empty($userRoles) || empty($userRoles[$this->_stageId])) {
            return AuthorizationPolicy::AUTHORIZATION_DENY;
        }

        if (!$this->_checkUserRoleAssignment($userRoles[$this->_stageId])) {
            return AuthorizationPolicy::AUTHORIZATION_DENY;
        }
        if (!$this->_checkOperationWhitelist()) {
            return AuthorizationPolicy::AUTHORIZATION_DENY;
        }

        return AuthorizationPolicy::AUTHORIZATION_PERMIT;
    }
}

if (!PKP_STRICT_MODE) {
    class_alias('\PKP\security\authorization\AssignedStageRoleHandlerOperationPolicy', '\AssignedStageRoleHandlerOperationPolicy');
}

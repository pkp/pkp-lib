<?php
/**
 * @file classes/security/authorization/AssignedStageRoleHandlerOperationPolicy.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class AssignedStageRoleHandlerOperationPolicy
 *
 * @ingroup security_authorization
 *
 * @brief Class to control access to handler operations based on assigned
 *  role(s) in a submission's workflow stage.
 */

namespace PKP\security\authorization;

use APP\facades\Repo;
use PKP\core\PKPApplication;
use PKP\core\PKPRequest;
use PKP\db\DAORegistry;
use PKP\security\Role;
use PKP\stageAssignment\StageAssignment;
use PKP\stageAssignment\StageAssignmentDAO;

class AssignedStageRoleHandlerOperationPolicy extends RoleBasedHandlerOperationPolicy
{
    public int $_stageId;

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

        $this->_stageId = (int) $stageId;
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
        $stageAssignmentDao = DAORegistry::getDAO('StageAssignmentDAO'); /** @var StageAssignmentDAO $stageAssignmentDao */
        $submission = $this->getAuthorizedContextObject(PKPApplication::ASSOC_TYPE_SUBMISSION);
        $userId = $this->_request->getUser()->getId();

        // We need all user assignments to the current submission to identify correct access for the managers/admin assigned in the lower-level roles
        $userRoleAssignments = $stageAssignmentDao->getBySubmissionAndUserIdAndStageId(
            $submission->getId(),
            $userId
        )->toArray();

        // Retrieve user role assignments to the current stage
        $stageRoleAssignmentIds = [];
        $userRoleIds = $this->getAuthorizedContextObject(PKPApplication::ASSOC_TYPE_USER_ROLES);
        foreach ($userRoleAssignments as $key => $stageAssignment) { /** @var StageAssignment $stageAssignment */

            $userGroup = Repo::userGroup()->get($stageAssignment->getUserGroupId());
            $stageRoleId = $userGroup->getRoleId();

            // Check global user roles within the context, e.g., user can be assigned in the role, which was revoked, see pkp/pkp-lib#9127
            if (!in_array($stageRoleId, $userRoleIds)) {
                unset($userRoleAssignments[$key]);
                continue;
            }

            // Assignments for the current stage only
            if ($stageAssignment->getStageId() !== $this->_stageId) {
                continue;
            }

            $stageRoleAssignmentIds[] = $stageRoleId;
        }

        // If user isn't assigned to a submission but has an admin or manager role - allow the access
        if (empty($userRoleAssignments) && !empty(array_intersect([Role::ROLE_ID_SITE_ADMIN, Role::ROLE_ID_MANAGER], $userRoleIds))) {
            return AuthorizationPolicy::AUTHORIZATION_PERMIT;
        }

        // User isn't assigned, deny the access
        if (empty($stageRoleAssignmentIds)) {
            return AuthorizationPolicy::AUTHORIZATION_DENY;
        }

        if (!$this->_checkUserRoleAssignment($stageRoleAssignmentIds)) {
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

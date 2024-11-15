<?php
/**
 * @file classes/security/authorization/StageRolePolicy.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class StageRolePolicy
 *
 * @ingroup security_authorization
 *
 * @brief Class to check if the user has an assigned role on a specific
 *   submission stage. Optionally deny authorization if that stage
 *   assignment is a "recommend only" assignment.
 */

namespace PKP\security\authorization;

use APP\core\Application;
use APP\facades\Repo;
use PKP\security\Role;
use PKP\stageAssignment\StageAssignment;
use PKP\userGroup\UserGroup;

class StageRolePolicy extends AuthorizationPolicy
{
    /** @var array */
    private $_roleIds;

    /** @var int|null */
    private $_stageId;

    /** @var bool */
    private $_allowRecommendOnly;

    /**
     * Constructor
     *
     * @param array $roleIds The roles required to be authorized
     * @param int $stageId The stage the role assignment is required on to be authorized.
     *   Leave this null to check against the submission's currently active stage.
     * @param bool $allowRecommendOnly Authorize the user even if the stage assignment
     *   is a "recommend only" assignment. Default allows "recommend only" assignments to
     *   pass authorization.
     */
    public function __construct($roleIds, $stageId = null, $allowRecommendOnly = true)
    {
        parent::__construct('user.authorization.accessibleWorkflowStage');
        $this->_roleIds = $roleIds;
        $this->_stageId = $stageId;
        $this->_allowRecommendOnly = $allowRecommendOnly;
    }

    //
    // Implement template methods from AuthorizationPolicy
    //
    /**
     * @see AuthorizationPolicy::effect()
     */
    public function effect()
    {
        // Use the submission's current stage id if none is specified in policy
        if (!$this->_stageId) {
            $this->_stageId = $this->getAuthorizedContextObject(Application::ASSOC_TYPE_SUBMISSION)->getData('stageId');
        }

        // Check whether the user has one of the allowed roles assigned in the correct stage
        $userAccessibleStages = (array) $this->getAuthorizedContextObject(Application::ASSOC_TYPE_ACCESSIBLE_WORKFLOW_STAGES);

        if (array_key_exists($this->_stageId, $userAccessibleStages) && array_intersect($this->_roleIds, $userAccessibleStages[$this->_stageId])) {
            if ($this->_allowRecommendOnly) {
                return AuthorizationPolicy::AUTHORIZATION_PERMIT;
            }

            // Replaces StageAssignmentDAO::getBySubmissionAndUserIdAndStageId
            $stageAssignments = StageAssignment::withSubmissionIds([$this->getAuthorizedContextObject(Application::ASSOC_TYPE_SUBMISSION)->getId()])
                ->withStageIds([$this->_stageId])
                ->withUserId(Application::get()->getRequest()->getUser()->getId())
                ->get();

            foreach ($stageAssignments as $stageAssignment) {
                $userGroup = UserGroup::findById($stageAssignment->userGroupId);
                if ($userGroup && in_array($userGroup->roleId, $this->_roleIds) && !$stageAssignment->recommendOnly) {
                    return AuthorizationPolicy::AUTHORIZATION_PERMIT;
                }
            }
        }

        // A manager is granted access when they are not assigned in any other role
        if (count(array_intersect([Role::ROLE_ID_MANAGER, Role::ROLE_ID_SITE_ADMIN], $this->getAuthorizedContextObject(Application::ASSOC_TYPE_USER_ROLES)))) {
            if ($this->_allowRecommendOnly) {
                return AuthorizationPolicy::AUTHORIZATION_PERMIT;
            }
            // Check stage assignments of a user with a managerial role
            // Replaces StageAssignmentDAO::getBySubmissionAndUserIdAndStageId
            $stageAssignments = StageAssignment::withSubmissionIds([$this->getAuthorizedContextObject(Application::ASSOC_TYPE_SUBMISSION)->getId()])
                ->withStageIds([$this->_stageId])
                ->withUserId(Application::get()->getRequest()->getUser()->getId())
                ->get();

            $noResults = true;
            foreach ($stageAssignments as $stageAssignment) {
                $noResults = false;
                $userGroup = UserGroup::find($stageAssignment->userGroupId);
                if ($userGroup && $userGroup->roleId == Role::ROLE_ID_MANAGER && !$stageAssignment->recommendOnly) {
                    return AuthorizationPolicy::AUTHORIZATION_PERMIT;
                }
            }
            if ($noResults) {
                return AuthorizationPolicy::AUTHORIZATION_PERMIT;
            }
        }

        return AuthorizationPolicy::AUTHORIZATION_DENY;
    }
}

if (!PKP_STRICT_MODE) {
    class_alias('\PKP\security\authorization\StageRolePolicy', '\StageRolePolicy');
}

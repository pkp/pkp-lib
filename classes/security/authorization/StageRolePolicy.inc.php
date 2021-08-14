<?php
/**
 * @file classes/security/authorization/StageRolePolicy.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class StageRolePolicy
 * @ingroup security_authorization
 *
 * @brief Class to check if the user has an assigned role on a specific
 *   submission stage. Optionally deny authorization if that stage
 *   assignment is a "recommend only" assignment.
 */

namespace PKP\security\authorization;

use APP\core\Application;
use PKP\db\DAORegistry;
use PKP\security\Role;

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
            $this->_stageId = $this->getAuthorizedContextObject(ASSOC_TYPE_SUBMISSION)->getData('stageId');
        }

        // Check whether the user has one of the allowed roles assigned in the correct stage
        $userAccessibleStages = (array) $this->getAuthorizedContextObject(ASSOC_TYPE_ACCESSIBLE_WORKFLOW_STAGES);

        if (array_key_exists($this->_stageId, $userAccessibleStages) && array_intersect($this->_roleIds, $userAccessibleStages[$this->_stageId])) {
            if ($this->_allowRecommendOnly) {
                return AuthorizationPolicy::AUTHORIZATION_PERMIT;
            }
            $stageAssignmentDao = DAORegistry::getDAO('StageAssignmentDAO'); /** @var StageAssignmentDAO $stageAssignmentDao */
            $result = $stageAssignmentDao->getBySubmissionAndUserIdAndStageId(
                $this->getAuthorizedContextObject(ASSOC_TYPE_SUBMISSION)->getId(),
                Application::get()->getRequest()->getUser()->getId(),
                $this->_stageId
            );
            while ($stageAssignment = $result->next()) {
                $userGroupDao = DAORegistry::getDAO('UserGroupDAO'); /** @var UserGroupDAO $userGroupDao */
                $userGroup = $userGroupDao->getById($stageAssignment->getUserGroupId());
                if (in_array($userGroup->getRoleId(), $this->_roleIds) && !$stageAssignment->getRecommendOnly()) {
                    return AuthorizationPolicy::AUTHORIZATION_PERMIT;
                }
            }
        }

        // A manager is granted access when they are not assigned in any other role
        if (empty($userAccessibleStages) && in_array(Role::ROLE_ID_MANAGER, $this->getAuthorizedContextObject(ASSOC_TYPE_USER_ROLES))) {
            if ($this->_allowRecommendOnly) {
                return AuthorizationPolicy::AUTHORIZATION_PERMIT;
            }
            // Managers may have a stage assignment but no $userAccessibleStages, so they will
            // not be caught by the earlier code that checks stage assignments.
            $stageAssignmentDao = DAORegistry::getDAO('StageAssignmentDAO'); /** @var StageAssignmentDAO $stageAssignmentDao */
            $result = $stageAssignmentDao->getBySubmissionAndUserIdAndStageId(
                $this->getAuthorizedContextObject(ASSOC_TYPE_SUBMISSION)->getId(),
                Application::get()->getRequest()->getUser()->getId(),
                $this->_stageId
            );
            $userGroupDao = DAORegistry::getDAO('UserGroupDAO'); /** @var UserGroupDAO $userGroupDao */
            $noResults = true;
            while ($stageAssignment = $result->next()) {
                $noResults = false;
                $userGroup = $userGroupDao->getById($stageAssignment->getUserGroupId());
                if ($userGroup->getRoleId() == Role::ROLE_ID_MANAGER && !$stageAssignment->getRecommendOnly()) {
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

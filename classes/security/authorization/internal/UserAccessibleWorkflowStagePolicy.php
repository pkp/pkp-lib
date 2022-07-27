<?php
/**
 * @file classes/security/authorization/internal/UserAccessibleWorkflowStagePolicy.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class UserAccessibleWorkflowStagePolicy
 * @ingroup security_authorization_internal
 *
 * @brief Class to control access to a
 *
 */

namespace PKP\security\authorization\internal;

use APP\core\Application;

use PKP\security\authorization\AuthorizationPolicy;

class UserAccessibleWorkflowStagePolicy extends AuthorizationPolicy
{
    /** @var int */
    public $_stageId;

    /** @var string Workflow type. One of PKPApplication::WORKFLOW_TYPE_... */
    public $_workflowType;

    /**
     * Constructor
     *
     * @param int $stageId The one that will be checked against accessible
     * user workflow stages.
     * @param string $workflowType Which workflow the stage access must be granted
     *  for. One of PKPApplication::WORKFLOW_TYPE_*.
     */
    public function __construct($stageId, $workflowType = null)
    {
        parent::__construct('user.authorization.accessibleWorkflowStage');
        $this->_stageId = $stageId;
        if (!is_null($workflowType)) {
            $this->_workflowType = $workflowType;
        }
    }


    //
    // Implement template methods from AuthorizationPolicy
    //
    /**
     * @see AuthorizationPolicy::effect()
     */
    public function effect()
    {
        $userAccessibleStages = $this->getAuthorizedContextObject(ASSOC_TYPE_ACCESSIBLE_WORKFLOW_STAGES);

        // User has no access to any stage in any workflow
        if (empty($userAccessibleStages)) {
            return AuthorizationPolicy::AUTHORIZATION_DENY;

        // Does user have access to this stage in the requested workflow?
        } elseif (!is_null($this->_workflowType)) {
            $workflowTypeRoles = Application::getWorkflowTypeRoles();
            if (array_key_exists($this->_stageId, $userAccessibleStages) && array_intersect($workflowTypeRoles[$this->_workflowType], $userAccessibleStages[$this->_stageId])) {
                return AuthorizationPolicy::AUTHORIZATION_PERMIT;
            }
            return AuthorizationPolicy::AUTHORIZATION_DENY;

        // The user has access to this stage in any workflow
        } elseif (array_key_exists($this->_stageId, $userAccessibleStages)) {
            return AuthorizationPolicy::AUTHORIZATION_PERMIT;
        }

        return AuthorizationPolicy::AUTHORIZATION_DENY;
    }
}

if (!PKP_STRICT_MODE) {
    class_alias('\PKP\security\authorization\internal\UserAccessibleWorkflowStagePolicy', '\UserAccessibleWorkflowStagePolicy');
}

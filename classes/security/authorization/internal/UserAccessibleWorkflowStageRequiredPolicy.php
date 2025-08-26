<?php

/**
 * @file classes/security/authorization/internal/UserAccessibleWorkflowStageRequiredPolicy.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class UserAccessibleWorkflowStageRequiredPolicy
 *
 * @ingroup security_authorization_internal
 *
 * @brief Policy to deny access if an user assigned workflow stage is not found.
 *
 */

namespace PKP\security\authorization\internal;

use APP\core\Application;
use PKP\security\Role;
use PKP\core\PKPRequest;
use PKP\security\authorization\AuthorizationPolicy;

class UserAccessibleWorkflowStageRequiredPolicy extends AuthorizationPolicy
{
    /** @var PKPRequest */
    public $_request;

    /** @var string Workflow type. One of PKPApplication::WORKFLOW_TYPE_... */
    public $_workflowType;

    /**
     * Constructor
     *
     * @param PKPRequest $request
     * @param string $workflowType Which workflow the stage access must be granted
     *  for. One of PKPApplication::WORKFLOW_TYPE_*.
     */
    public function __construct($request, $workflowType = null)
    {
        parent::__construct('user.authorization.accessibleWorkflowStage');
        $this->_request = $request;
        $this->_workflowType = $workflowType;
    }

    //
    // Implement template methods from AuthorizationPolicy
    //
    /**
     * @see AuthorizationPolicy::effect()
     */
    public function effect(): int
    {
        $accessible = (array) $this->getAuthorizedContextObject(Application::ASSOC_TYPE_ACCESSIBLE_WORKFLOW_STAGES);

        $userRoles = (array) $this->getAuthorizedContextObject(Application::ASSOC_TYPE_USER_ROLES);

        $isSubEditor = in_array(Role::ROLE_ID_SUB_EDITOR, $userRoles, true);
        $isManagerOrAdmin = in_array(Role::ROLE_ID_MANAGER, $userRoles, true)
            || in_array(Role::ROLE_ID_SITE_ADMIN, $userRoles, true);

        if ($isSubEditor || $isManagerOrAdmin) {
            foreach ([
                WORKFLOW_STAGE_ID_SUBMISSION,
                WORKFLOW_STAGE_ID_INTERNAL_REVIEW,
                WORKFLOW_STAGE_ID_EXTERNAL_REVIEW,
                WORKFLOW_STAGE_ID_EDITING,
                WORKFLOW_STAGE_ID_PRODUCTION,
            ] as $stageId) {
                $existing = $accessible[$stageId] ?? [];
                if ($isSubEditor && !in_array(Role::ROLE_ID_SUB_EDITOR, $existing, true)) {
                    $existing[] = Role::ROLE_ID_SUB_EDITOR;
                }
                if ($isManagerOrAdmin && !in_array(Role::ROLE_ID_MANAGER, $existing, true)) {
                    $existing[] = Role::ROLE_ID_MANAGER;
                }
                $accessible[$stageId] = $existing;
            }

            $this->addAuthorizedContextObject(Application::ASSOC_TYPE_ACCESSIBLE_WORKFLOW_STAGES, $accessible);
        }

        if (empty($accessible)) {
            return AuthorizationPolicy::AUTHORIZATION_DENY;
        }

        return AuthorizationPolicy::AUTHORIZATION_PERMIT;
    }
}

if (!PKP_STRICT_MODE) {
    class_alias('\PKP\security\authorization\internal\UserAccessibleWorkflowStageRequiredPolicy', '\UserAccessibleWorkflowStageRequiredPolicy');
}

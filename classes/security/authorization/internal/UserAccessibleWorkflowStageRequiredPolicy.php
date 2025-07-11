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
use APP\facades\Repo;
use PKP\core\PKPApplication;
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
        $request = $this->_request;
        $context = $request->getContext();
        $contextId = $context->getId();
        $user = $request->getUser();
        if (!$user instanceof \PKP\user\User) {
            return AuthorizationPolicy::AUTHORIZATION_DENY;
        }

        $accessibleWorkflowStages = Repo::user()->getAccessibleWorkflowStages(
            $user->getId(),
            $contextId,
            $this->getAuthorizedContextObject(Application::ASSOC_TYPE_SUBMISSION),
            $this->getAuthorizedContextObject(PKPApplication::ASSOC_TYPE_USER_ROLES)
        );

        $this->addAuthorizedContextObject(Application::ASSOC_TYPE_ACCESSIBLE_WORKFLOW_STAGES, $accessibleWorkflowStages);

        // Does the user have a role which matches the requested workflow?
        if (!is_null($this->_workflowType)) {
            $workflowTypeRoles = Application::getWorkflowTypeRoles();
            foreach ($accessibleWorkflowStages as $stageId => $roles) {
                if (array_intersect($workflowTypeRoles[$this->_workflowType], $roles)) {
                    return AuthorizationPolicy::AUTHORIZATION_PERMIT;
                }
            }
            return AuthorizationPolicy::AUTHORIZATION_DENY;

            // User has at least one role in any stage in any workflow
        } elseif (!empty($accessibleWorkflowStages)) {
            return AuthorizationPolicy::AUTHORIZATION_PERMIT;
        }

        return AuthorizationPolicy::AUTHORIZATION_DENY;
    }
}

<?php
/**
 * @file classes/security/authorization/internal/UserAccessibleWorkflowStageRequiredPolicy.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class UserAccessibleWorkflowStageRequiredPolicy
 * @ingroup security_authorization_internal
 *
 * @brief Policy to deny access if an user assigned workflow stage is not found.
 *
 */

namespace PKP\security\authorization\internal;

use APP\core\Application;
use APP\facades\Repo;
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
        AppLocale::requireComponents(LOCALE_COMPONENT_PKP_USER);
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
    public function effect()
    {
        $request = $this->_request;
        $context = $request->getContext();
        $contextId = $context->getId();
        $user = $request->getUser();
        if (!$user instanceof \PKP\user\User) {
            return AuthorizationPolicy::AUTHORIZATION_DENY;
        }

        $userId = $user->getId();
        $submission = $this->getAuthorizedContextObject(ASSOC_TYPE_SUBMISSION);

        $accessibleWorkflowStages = [];
        $workflowStages = Application::get()->getApplicationStages();
        foreach ($workflowStages as $stageId) {
            $accessibleStageRoles = Repo::user()->getAccessibleStageRoles($userId, $contextId, $submission, $stageId);
            if (!empty($accessibleStageRoles)) {
                $accessibleWorkflowStages[$stageId] = $accessibleStageRoles;
            }
        }

        $this->addAuthorizedContextObject(ASSOC_TYPE_ACCESSIBLE_WORKFLOW_STAGES, $accessibleWorkflowStages);

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

if (!PKP_STRICT_MODE) {
    class_alias('\PKP\security\authorization\internal\UserAccessibleWorkflowStageRequiredPolicy', '\UserAccessibleWorkflowStageRequiredPolicy');
}

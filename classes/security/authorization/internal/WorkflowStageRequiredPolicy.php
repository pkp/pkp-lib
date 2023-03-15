<?php
/**
 * @file classes/security/authorization/internal/WorkflowStageRequiredPolicy.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class WorkflowStageRequiredPolicy
 * @ingroup security_authorization_internal
 *
 * @brief Policy that ensures that the given workflow stage is valid.
 */

namespace PKP\security\authorization\internal;

use APP\core\Application;

use PKP\security\authorization\AuthorizationPolicy;

class WorkflowStageRequiredPolicy extends AuthorizationPolicy
{
    /** @var int */
    public $_stageId;

    /**
     * Constructor
     *
     * @param int $stageId One of the WORKFLOW_STAGE_ID_* constants.
     */
    public function __construct($stageId)
    {
        parent::__construct('user.authorization.workflowStageRequired');
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
        // Check the stage id.
        $validAppStages = Application::getApplicationStages();
        if (!in_array($this->_stageId, array_values($validAppStages))) {
            return AuthorizationPolicy::AUTHORIZATION_DENY;
        }

        // Save the workflow stage to the authorization context.
        $this->addAuthorizedContextObject(Application::ASSOC_TYPE_WORKFLOW_STAGE, $this->_stageId);
        return AuthorizationPolicy::AUTHORIZATION_PERMIT;
    }
}

if (!PKP_STRICT_MODE) {
    class_alias('\PKP\security\authorization\internal\WorkflowStageRequiredPolicy', '\WorkflowStageRequiredPolicy');
}

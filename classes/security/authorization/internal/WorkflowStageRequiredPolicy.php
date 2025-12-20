<?php

/**
 * @file classes/security/authorization/internal/WorkflowStageRequiredPolicy.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class WorkflowStageRequiredPolicy
 *
 * @ingroup security_authorization_internal
 *
 * @brief Policy that ensures that the given workflow stage is valid.
 */

namespace PKP\security\authorization\internal;

use APP\core\Application;
use PKP\security\authorization\AuthorizationPolicy;

class WorkflowStageRequiredPolicy extends AuthorizationPolicy
{
    /**
     * Constructor
     *
     * @param ?int $stageId One of the WORKFLOW_STAGE_ID_* constants.
     * @param ?int $assocType One of the PKPApplication::ASSOC_TYPE_* constants
     */
    public function __construct(protected ?int $stageId, protected ?int $assocType = null)
    {
        parent::__construct('user.authorization.workflowStageRequired');
    }


    //
    // Implement template methods from AuthorizationPolicy
    //
    /**
     * @see AuthorizationPolicy::effect()
     */
    public function effect(): int
    {
        if (!$this->stageId && $this->assocType) {
            // Try to get stage ID from the associated object
            $assoc = $this->getAuthorizedContextObject($this->assocType);
            if ($assoc && isset($assoc->stageId)) {
                $this->stageId = $assoc->stageId;
            }
        }

        if (!$this->stageId) {
            return AuthorizationPolicy::AUTHORIZATION_DENY;
        }

        // Check the stage id.
        $validAppStages = Application::getApplicationStages();
        if (!in_array($this->stageId, array_values($validAppStages))) {
            return AuthorizationPolicy::AUTHORIZATION_DENY;
        }

        // Save the workflow stage to the authorization context.
        $this->addAuthorizedContextObject(Application::ASSOC_TYPE_WORKFLOW_STAGE, $this->stageId);
        return AuthorizationPolicy::AUTHORIZATION_PERMIT;
    }
}

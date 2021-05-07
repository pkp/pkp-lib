<?php
/**
 * @file classes/security/authorization/internal/ManagerRequiredPolicy.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class ManagerRequiredPolicy
 * @ingroup security_authorization_internal
 *
 * @brief Class to prevent access unless a manager is assigned to the stage.
 *
 * NB: This policy expects a previously authorized submission and a stage id
 * in the authorization context.
 */

namespace PKP\security\authorization\internal;

use APP\submission\Submission;
use PKP\db\DAORegistry;

use PKP\security\authorization\AuthorizationPolicy;

class ManagerRequiredPolicy extends AuthorizationPolicy
{
    /** @var PKPRequest */
    public $_request;

    /**
     * Constructor
     *
     * @param $request PKPRequest
     */
    public function __construct($request)
    {
        parent::__construct('user.authorization.managerRequired');
        $this->_request = $request;
    }

    //
    // Implement template methods from AuthorizationPolicy
    //
    /**
     * @see AuthorizationPolicy::effect()
     */
    public function effect()
    {
        // Get the submission
        $submission = $this->getAuthorizedContextObject(ASSOC_TYPE_SUBMISSION);
        if (!$submission instanceof Submission) {
            return AuthorizationPolicy::AUTHORIZATION_DENY;
        }

        // Get the stage
        $stageId = $this->getAuthorizedContextObject(ASSOC_TYPE_WORKFLOW_STAGE);
        if (!is_numeric($stageId)) {
            return AuthorizationPolicy::AUTHORIZATION_DENY;
        }

        $stageAssignmentDao = DAORegistry::getDAO('StageAssignmentDAO'); /** @var StageAssignmentDAO $stageAssignmentDao */
        if ($stageAssignmentDao->editorAssignedToStage($submission->getId(), $stageId)) {
            return AuthorizationPolicy::AUTHORIZATION_PERMIT;
        } else {
            return AuthorizationPolicy::AUTHORIZATION_DENY;
        }
    }
}

if (!PKP_STRICT_MODE) {
    class_alias('\PKP\security\authorization\internal\ManagerRequiredPolicy', '\ManagerRequiredPolicy');
}

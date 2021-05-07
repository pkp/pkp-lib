<?php
/**
 * @file classes/security/authorization/EditorDecisionAccessPolicy.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class EditorDecisionAccessPolicy
 * @ingroup security_authorization
 *
 * @brief Class to control access to submission workflow stage components
 */

namespace PKP\security\authorization;

use PKP\core\PKPApplication;
use PKP\security\authorization\internal\ContextPolicy;
use PKP\security\authorization\internal\ManagerRequiredPolicy;

class EditorDecisionAccessPolicy extends ContextPolicy
{
    /**
     * Constructor
     *
     * @param $request PKPRequest
     * @param $args array request arguments
     * @param $roleAssignments array
     * @param $submissionParameterName string
     * @param $stageId integer One of the WORKFLOW_STAGE_ID_* constants.
     */
    public function __construct($request, &$args, $roleAssignments, $submissionParameterName, $stageId)
    {
        parent::__construct($request);

        // A decision can only be made if there is a valid workflow stage
        $this->addPolicy(new WorkflowStageAccessPolicy($request, $args, $roleAssignments, $submissionParameterName, $stageId, PKPApplication::WORKFLOW_TYPE_EDITORIAL));

        // An editor decision can only be made if there is an editor assigned to the stage
        $this->addPolicy(new ManagerRequiredPolicy($request));
    }
}

if (!PKP_STRICT_MODE) {
    class_alias('\PKP\security\authorization\EditorDecisionAccessPolicy', '\EditorDecisionAccessPolicy');
}

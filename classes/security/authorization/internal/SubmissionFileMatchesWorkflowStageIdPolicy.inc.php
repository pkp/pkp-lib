<?php
/**
 * @file classes/security/authorization/internal/SubmissionFileMatchesWorkflowStageIdPolicy.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class SubmissionFileMatchesWorkflowStageIdPolicy
 * @ingroup security_authorization_internal
 *
 * @brief Submission file policy to check if the file belongs to the specified workflow stage ID
 */

namespace PKP\security\authorization\internal;

use APP\facades\Repo;
use PKP\security\authorization\AuthorizationPolicy;
use PKP\submissionFile\SubmissionFile;

class SubmissionFileMatchesWorkflowStageIdPolicy extends SubmissionFileBaseAccessPolicy
{
    /** @var int|null Workflow stage ID (WORKFLOW_STAGE_ID_...) */
    protected $_stageId = null;

    /**
     * Constructor
     *
     * @param PKPRequest $request
     * @param int $stageId Workflow stage ID (WORKFLOW_STAGE_ID_...)
     * @param null|mixed $submissionFileId
     */
    public function __construct($request, $submissionFileId = null, $stageId = null)
    {
        parent::__construct($request, $submissionFileId);
        $this->_stageId = (int) $stageId;
    }


    //
    // Implement template methods from AuthorizationPolicy
    //
    /**
     * @see AuthorizationPolicy::effect()
     */
    public function effect()
    {
        // Get the submission file
        $request = $this->getRequest();
        $submissionFile = $this->getSubmissionFile($request);
        if (!$submissionFile instanceof SubmissionFile) {
            return AuthorizationPolicy::AUTHORIZATION_DENY;
        }

        $workflowStageId = Repo::submissionFile()->getWorkflowStageId($submissionFile);

        // Check if the submission file belongs to the specified workflow stage.
        if ($workflowStageId != $this->_stageId) {
            return AuthorizationPolicy::AUTHORIZATION_DENY;
        }

        return AuthorizationPolicy::AUTHORIZATION_PERMIT;
    }
}

if (!PKP_STRICT_MODE) {
    class_alias('\PKP\security\authorization\internal\SubmissionFileMatchesWorkflowStageIdPolicy', '\SubmissionFileMatchesWorkflowStageIdPolicy');
}

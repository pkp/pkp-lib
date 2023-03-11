<?php
/**
 * @file classes/security/authorization/internal/SubmissionFileStageRequiredPolicy.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class SubmissionFileStageRequiredPolicy
 * @ingroup security_authorization_internal
 *
 * @brief Submission file policy to ensure that we have a file at a required stage.
 *
 */

namespace PKP\security\authorization\internal;

use APP\core\Application;
use PKP\db\DAORegistry;
use PKP\security\authorization\AuthorizationPolicy;

class SubmissionFileStageRequiredPolicy extends SubmissionFileBaseAccessPolicy
{
    /** @var int SubmissionFile::SUBMISSION_FILE_... */
    public $_fileStage;

    /** @var bool Whether the file has to be viewable */
    public $_viewable;

    /**
     * Constructor
     *
     * @param PKPRequest $request
     * @param int $submissionFileId This policy will try to
     * get the submission file from this data.
     * @param int $fileStage SubmissionFile::SUBMISSION_FILE_...
     * @param bool $viewable Whether the file has to be viewable
     */
    public function __construct($request, $submissionFileId, $fileStage, $viewable = false)
    {
        parent::__construct($request, $submissionFileId);
        $this->_fileStage = $fileStage;
        $this->_viewable = $viewable;
    }


    //
    // Implement template methods from AuthorizationPolicy
    //
    /**
     * @see AuthorizationPolicy::effect()
     */
    public function effect()
    {
        $request = $this->getRequest();

        // Get the submission file.
        $submissionFile = $this->getSubmissionFile($request);
        if (!$submissionFile instanceof \PKP\submissionFile\SubmissionFile) {
            return AuthorizationPolicy::AUTHORIZATION_DENY;
        }

        // Make sure that it's in the required stage
        if ($submissionFile->getFileStage() != $this->_fileStage) {
            return AuthorizationPolicy::AUTHORIZATION_DENY;
        }

        if ($this->_viewable) {
            // Make sure the file is visible. Unless file is included in an open review.
            if (!$submissionFile->getViewable()) {
                if ($submissionFile->getData('assocType') === Application::ASSOC_TYPE_REVIEW_ASSIGNMENT) {
                    $reviewAssignmentDao = DAORegistry::getDAO('ReviewAssignmentDAO'); /** @var ReviewAssignmentDAO $reviewAssignmentDao */
                    $reviewAssignment = $reviewAssignmentDao->getById((int) $submissionFile->getData('assocId'));
                    if ($reviewAssignment->getReviewMethod() != SUBMISSION_REVIEW_METHOD_OPEN) {
                        return AuthorizationPolicy::AUTHORIZATION_DENY;
                    }
                } else {
                    return AuthorizationPolicy::AUTHORIZATION_DENY;
                }
            }
        }

        // Made it through -- permit access.
        return AuthorizationPolicy::AUTHORIZATION_PERMIT;
    }
}

if (!PKP_STRICT_MODE) {
    class_alias('\PKP\security\authorization\internal\SubmissionFileStageRequiredPolicy', '\SubmissionFileStageRequiredPolicy');
}

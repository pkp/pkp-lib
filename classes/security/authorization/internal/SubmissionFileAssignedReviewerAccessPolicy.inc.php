<?php
/**
 * @file classes/security/authorization/internal/SubmissionFileAssignedReviewerAccessPolicy.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class SubmissionFileAssignedReviewerAccessPolicy
 * @ingroup security_authorization_internal
 *
 * @brief Submission file policy to check if the current user is an assigned
 * 	reviewer of the file.
 *
 */

namespace PKP\security\authorization\internal;

use PKP\db\DAORegistry;
use PKP\security\authorization\AuthorizationPolicy;
use PKP\submission\SubmissionFile;

class SubmissionFileAssignedReviewerAccessPolicy extends SubmissionFileBaseAccessPolicy
{
    /**
     * Constructor
     *
     * @param $request PKPRequest
     * @param null|mixed $submissionFileId
     */
    public function __construct($request, $submissionFileId = null)
    {
        parent::__construct($request, $submissionFileId);
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

        // Get the user
        $user = $request->getUser();
        if (!$user instanceof \PKP\user\User) {
            return AuthorizationPolicy::AUTHORIZATION_DENY;
        }

        // Get the submission file
        $submissionFile = $this->getSubmissionFile($request);
        if (!$submissionFile instanceof \PKP\submission\SubmissionFile) {
            return AuthorizationPolicy::AUTHORIZATION_DENY;
        }

        $context = $request->getContext();
        $reviewAssignmentDao = DAORegistry::getDAO('ReviewAssignmentDAO'); /** @var ReviewAssignmentDAO $reviewAssignmentDao */
        $reviewAssignments = $reviewAssignmentDao->getByUserId($user->getId());
        $reviewFilesDao = DAORegistry::getDAO('ReviewFilesDAO'); /** @var ReviewFilesDAO $reviewFilesDao */
        foreach ($reviewAssignments as $reviewAssignment) {
            if ($context->getData('restrictReviewerFileAccess') && !$reviewAssignment->getDateConfirmed()) {
                continue;
            }

            if (
                $submissionFile->getData('submissionId') == $reviewAssignment->getSubmissionId() &&
                $submissionFile->getData('fileStage') == SubmissionFile::SUBMISSION_FILE_REVIEW_FILE &&
                $reviewFilesDao->check($reviewAssignment->getId(), $submissionFile->getId())
            ) {
                $this->addAuthorizedContextObject(ASSOC_TYPE_REVIEW_ASSIGNMENT, $reviewAssignment);
                return AuthorizationPolicy::AUTHORIZATION_PERMIT;
            }
        }

        // If a pass condition wasn't found above, deny access.
        return AuthorizationPolicy::AUTHORIZATION_DENY;
    }
}

if (!PKP_STRICT_MODE) {
    class_alias('\PKP\security\authorization\internal\SubmissionFileAssignedReviewerAccessPolicy', '\SubmissionFileAssignedReviewerAccessPolicy');
}

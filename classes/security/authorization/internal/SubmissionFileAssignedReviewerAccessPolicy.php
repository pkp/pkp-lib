<?php
/**
 * @file classes/security/authorization/internal/SubmissionFileAssignedReviewerAccessPolicy.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class SubmissionFileAssignedReviewerAccessPolicy
 *
 * @ingroup security_authorization_internal
 *
 * @brief Submission file policy to check if the current user is an assigned
 * 	reviewer of the file.
 *
 */

namespace PKP\security\authorization\internal;

use APP\core\Application;
use Exception;
use PKP\db\DAORegistry;
use PKP\security\authorization\AuthorizationPolicy;
use PKP\submission\reviewAssignment\ReviewAssignmentDAO;
use PKP\submission\ReviewFilesDAO;
use PKP\submissionFile\SubmissionFile;

class SubmissionFileAssignedReviewerAccessPolicy extends SubmissionFileBaseAccessPolicy
{
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
        if (!$submissionFile instanceof SubmissionFile) {
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

            // Determine which file stage the requested file should be in.
            $reviewFileStage = null;
            switch ($reviewAssignment->getStageId()) {
                case WORKFLOW_STAGE_ID_INTERNAL_REVIEW:
                    $reviewFileStage = SubmissionFile::SUBMISSION_FILE_INTERNAL_REVIEW_FILE;
                    break;
                case WORKFLOW_STAGE_ID_EXTERNAL_REVIEW:
                    $reviewFileStage = SubmissionFile::SUBMISSION_FILE_REVIEW_FILE;
                    break;
                default: throw new Exception('Unknown review workflow stage ID!');
            }

            if (
                $submissionFile->getData('submissionId') == $reviewAssignment->getSubmissionId() &&
                $submissionFile->getData('fileStage') == $reviewFileStage &&
                $reviewFilesDao->check($reviewAssignment->getId(), $submissionFile->getId())
            ) {
                $this->addAuthorizedContextObject(Application::ASSOC_TYPE_REVIEW_ASSIGNMENT, $reviewAssignment);
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

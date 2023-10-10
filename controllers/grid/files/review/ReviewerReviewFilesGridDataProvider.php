<?php

/**
 * @file controllers/grid/files/review/ReviewerReviewFilesGridDataProvider.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class ReviewerReviewFilesGridDataProvider
 *
 * @ingroup controllers_grid_files_review
 *
 * @brief Provide reviewer access to review file data for review file grids.
 */

namespace PKP\controllers\grid\files\review;

use APP\core\Application;
use PKP\db\DAORegistry;
use PKP\security\authorization\internal\ReviewAssignmentRequiredPolicy;
use PKP\security\authorization\internal\ReviewRoundRequiredPolicy;
use PKP\security\authorization\internal\WorkflowStageRequiredPolicy;
use PKP\security\authorization\SubmissionAccessPolicy;
use PKP\submission\ReviewFilesDAO;
use PKP\submissionFile\SubmissionFile;

class ReviewerReviewFilesGridDataProvider extends ReviewGridDataProvider
{
    /**
     * Constructor
     */
    public function __construct()
    {
        $stageId = (int) Application::get()->getRequest()->getUserVar('stageId');
        $fileStage = $stageId === WORKFLOW_STAGE_ID_INTERNAL_REVIEW ? SubmissionFile::SUBMISSION_FILE_INTERNAL_REVIEW_FILE : SubmissionFile::SUBMISSION_FILE_REVIEW_FILE;
        parent::__construct($fileStage);
    }


    //
    // Implement template methods from GridDataProvider
    //
    /**
     * @see GridDataProvider::getAuthorizationPolicy()
     * Override the parent class, which defines a Workflow policy, to allow
     * reviewer access to this grid.
     */
    public function getAuthorizationPolicy($request, $args, $roleAssignments)
    {
        $context = $request->getContext();
        $policy = new SubmissionAccessPolicy($request, $args, $roleAssignments, 'submissionId', !$context->getData('restrictReviewerFileAccess'));

        $stageId = $request->getUserVar('stageId');
        $policy->addPolicy(new WorkflowStageRequiredPolicy($stageId));

        // Add policy to ensure there is a review round id.
        $policy->addPolicy(new ReviewRoundRequiredPolicy($request, $args));

        // Add policy to ensure there is a review assignment for certain operations.
        $policy->addPolicy(new ReviewAssignmentRequiredPolicy($request, $args, 'reviewAssignmentId'));

        return $policy;
    }

    /**
     * @see ReviewerReviewFilesGridDataProvider
     * Extend the parent class to filter out review round files that aren't allowed
     * for this reviewer according to ReviewFilesDAO.
     *
     * @param array $filter
     */
    public function loadData($filter = [])
    {
        $submissionFileData = parent::loadData();
        $reviewFilesDao = DAORegistry::getDAO('ReviewFilesDAO'); /** @var ReviewFilesDAO $reviewFilesDao */
        $reviewAssignment = $this->getAuthorizedContextObject(Application::ASSOC_TYPE_REVIEW_ASSIGNMENT);
        foreach ($submissionFileData as $submissionFileId => $fileData) {
            if (!$reviewFilesDao->check($reviewAssignment->getId(), $submissionFileId)) {
                // Not permitted; remove from list.
                unset($submissionFileData[$submissionFileId]);
            }
        }
        return $submissionFileData;
    }

    /**
     * @copydoc GridDataProvider::getRequestArgs()
     */
    public function getRequestArgs()
    {
        $reviewAssignment = $this->getAuthorizedContextObject(Application::ASSOC_TYPE_REVIEW_ASSIGNMENT);
        return array_merge(parent::getRequestArgs(), [
            'reviewAssignmentId' => $reviewAssignment->getId()
        ]);
    }
}

<?php

/**
 * @file controllers/grid/files/review/ReviewerReviewFilesGridDataProvider.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class ReviewerReviewFilesGridDataProvider
 * @ingroup controllers_grid_files_review
 *
 * @brief Provide reviewer access to review file data for review file grids.
 */

import('lib.pkp.controllers.grid.files.review.ReviewGridDataProvider');

class ReviewerReviewFilesGridDataProvider extends ReviewGridDataProvider {
	/**
	 * Constructor
	 */
	function __construct() {
		$stageId = (int) Application::get()->getRequest()->getUserVar('stageId');
		$fileStage = $stageId === WORKFLOW_STAGE_ID_INTERNAL_REVIEW ? SUBMISSION_FILE_INTERNAL_REVIEW_FILE : SUBMISSION_FILE_REVIEW_FILE;
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
	function getAuthorizationPolicy($request, $args, $roleAssignments) {
		import('lib.pkp.classes.security.authorization.SubmissionAccessPolicy');
		$context = $request->getContext();
		$policy = new SubmissionAccessPolicy($request, $args, $roleAssignments, 'submissionId', !$context->getData('restrictReviewerFileAccess'));

		$stageId = $request->getUserVar('stageId');
		import('lib.pkp.classes.security.authorization.internal.WorkflowStageRequiredPolicy');
		$policy->addPolicy(new WorkflowStageRequiredPolicy($stageId));

		// Add policy to ensure there is a review round id.
		import('lib.pkp.classes.security.authorization.internal.ReviewRoundRequiredPolicy');
		$policy->addPolicy(new ReviewRoundRequiredPolicy($request, $args));

		// Add policy to ensure there is a review assignment for certain operations.
		import('lib.pkp.classes.security.authorization.internal.ReviewAssignmentRequiredPolicy');
		$policy->addPolicy(new ReviewAssignmentRequiredPolicy($request, $args, 'reviewAssignmentId'));

		return $policy;
	}

	/**
	 * @see ReviewerReviewFilesGridDataProvider
	 * Extend the parent class to filter out review round files that aren't allowed
	 * for this reviewer according to ReviewFilesDAO.
	 * @param $filter array
	 */
	function loadData($filter = array()) {
		$submissionFileData = parent::loadData();
		$reviewFilesDao = DAORegistry::getDAO('ReviewFilesDAO'); /* @var $reviewFilesDao ReviewFilesDAO */
		$reviewAssignment = $this->getAuthorizedContextObject(ASSOC_TYPE_REVIEW_ASSIGNMENT);
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
	function getRequestArgs() {
		$reviewAssignment = $this->getAuthorizedContextObject(ASSOC_TYPE_REVIEW_ASSIGNMENT);
		return array_merge(parent::getRequestArgs(), array(
			'reviewAssignmentId' => $reviewAssignment->getId()
		));
	}
}

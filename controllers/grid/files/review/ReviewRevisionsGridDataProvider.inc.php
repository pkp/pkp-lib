<?php
/**
 * @file controllers/grid/files/review/ReviewRevisionsGridDataProvider.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class ReviewRevisionsGridDataProvider
 * @ingroup controllers_grid_files_review
 *
 * @brief Provide access to review revisions (new files added during a
 *  review round) for grids.
 */


import('lib.pkp.controllers.grid.files.review.ReviewGridDataProvider');

class ReviewRevisionsGridDataProvider extends ReviewGridDataProvider {

	/**
	 * Constructor
	 */
	function __construct() {
		$stageId = (int) Application::get()->getRequest()->getUserVar('stageId');
		$fileStage = $stageId === WORKFLOW_STAGE_ID_INTERNAL_REVIEW ? SUBMISSION_FILE_INTERNAL_REVIEW_REVISION : SUBMISSION_FILE_REVIEW_REVISION;
		parent::__construct($fileStage);
	}


	//
	// Implement template methods from GridDataProvider
	//
	/**
	 * @copydoc GridDataProvider::loadData()
	 */
	function loadData($filter = array()) {
		// Grab the files that are new (incoming) revisions
		// of those currently assigned to the review round.
		$submissionFilesIterator = Services::get('submissionFile')->getMany([
			'submissionIds' => [$this->getSubmission()->getId()],
			'fileStages' => [$this->getFileStage()],
			'reviewRoundIds' => [$this->getReviewRound()->getId()],
		]);
		return $this->prepareSubmissionFileData(iterator_to_array($submissionFilesIterator), false, $filter);
	}


	//
	// Overridden public methods from FilesGridDataProvider
	//
	/**
	 * @copydoc FilesGridDataProvider::getAddFileAction()
	 */
	function getAddFileAction($request) {
		import('lib.pkp.controllers.api.file.linkAction.AddRevisionLinkAction');
		$reviewRound = $this->getReviewRound();
		return new AddRevisionLinkAction(
			$request, $reviewRound, $this->getUploaderRoles()
		);
	}
}



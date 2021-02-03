<?php

/**
 * @file controllers/grid/files/review/EditorReviewFilesGridHandler.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class EditorReviewFilesGridHandler
 * @ingroup controllers_grid_files_review
 *
 * @brief Handle the editor review file grid (displays files that are to be reviewed in the current round)
 */

import('lib.pkp.controllers.grid.files.fileList.FileListGridHandler');

class EditorReviewFilesGridHandler extends FileListGridHandler {

	/**
	 * Constructor
	 */
	function __construct() {
		$stageId = (int) Application::get()->getRequest()->getUserVar('stageId');
		$fileStage = $stageId === WORKFLOW_STAGE_ID_INTERNAL_REVIEW ? SUBMISSION_FILE_INTERNAL_REVIEW_FILE : SUBMISSION_FILE_REVIEW_FILE;
		import('lib.pkp.controllers.grid.files.review.ReviewGridDataProvider');
		parent::__construct(
			new ReviewGridDataProvider($fileStage),
			null,
			FILE_GRID_EDIT|FILE_GRID_MANAGE|FILE_GRID_VIEW_NOTES|FILE_GRID_DELETE
		);

		$this->addRoleAssignment(
			array(ROLE_ID_MANAGER, ROLE_ID_SUB_EDITOR, ROLE_ID_ASSISTANT),
			array('fetchGrid', 'fetchRow', 'selectFiles')
		);

		$this->setTitle('reviewer.submission.reviewFiles');
	}


	//
	// Public handler methods
	//
	/**
	 * Show the form to allow the user to select review files
	 * (bring in/take out files from submission stage to review stage)
	 *
	 * FIXME: Move to its own handler so that it can be re-used among grids.
	 *
	 * @param $args array
	 * @param $request PKPRequest
	 * @return JSONMessage JSON object
	 */
	function selectFiles($args, $request) {
		$submission = $this->getSubmission();

		import('lib.pkp.controllers.grid.files.review.form.ManageReviewFilesForm');
		$manageReviewFilesForm = new ManageReviewFilesForm($submission->getId(), $this->getRequestArg('stageId'), $this->getRequestArg('reviewRoundId'));

		$manageReviewFilesForm->initData();
		return new JSONMessage(true, $manageReviewFilesForm->fetch($request));
	}
}



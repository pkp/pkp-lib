<?php

/**
 * @file controllers/grid/files/review/AuthorReviewRevisionsGridHandler.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class AuthorReviewRevisionsGridHandler
 * @ingroup controllers_grid_files_review
 *
 * @brief Display to authors the file revisions that they have uploaded.
 */

import('lib.pkp.controllers.grid.files.fileList.FileListGridHandler');

class AuthorReviewRevisionsGridHandler extends FileListGridHandler {
	/**
	 * Constructor
	 */
	function __construct() {
		$stageId = (int) Application::get()->getRequest()->getUserVar('stageId');
		$fileStage = $stageId === WORKFLOW_STAGE_ID_INTERNAL_REVIEW ? SUBMISSION_FILE_INTERNAL_REVIEW_REVISION : SUBMISSION_FILE_REVIEW_REVISION;
		import('lib.pkp.controllers.grid.files.review.ReviewGridDataProvider');
		parent::__construct(
			new ReviewGridDataProvider($fileStage),
			null,
			FILE_GRID_ADD|FILE_GRID_EDIT|FILE_GRID_DELETE
		);

		$this->addRoleAssignment(
			array(ROLE_ID_AUTHOR),
			array('fetchGrid', 'fetchRow')
		);

		$this->setTitle('editor.submission.revisions');
	}

	/**
	 * @copydoc GridHandler::getJSHandler()
	 */
	public function getJSHandler() {
		return '$.pkp.controllers.grid.files.review.AuthorReviewRevisionsGridHandler';
	}
}



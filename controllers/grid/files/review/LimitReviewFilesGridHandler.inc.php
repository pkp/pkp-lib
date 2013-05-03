<?php

/**
 * @file controllers/grid/files/review/LimitReviewFilesGridHandler.inc.php
 *
 * Copyright (c) 2000-2013 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class LimitReviewFilesGridHandler
 * @ingroup controllers_grid_files_review
 *
 * @brief Display a selectable list of review files for the round to editors.
 *   Items in this list can be selected or deselected to give a specific subset
 *   to a particular reviewer.
 */

import('lib.pkp.controllers.grid.files.fileList.SelectableFileListGridHandler');

class LimitReviewFilesGridHandler extends SelectableFileListGridHandler {
	/**
	 * Constructor
	 */
	function LimitReviewFilesGridHandler() {
		import('lib.pkp.controllers.grid.files.review.ReviewGridDataProvider');
		// Pass in null stageId to be set in initialize from request var.
		parent::SelectableFileListGridHandler(
			new ReviewGridDataProvider(SUBMISSION_FILE_REVIEW_FILE),
			null,
			FILE_GRID_VIEW_NOTES
		);

		$this->addRoleAssignment(
			array(ROLE_ID_MANAGER, ROLE_ID_SUB_EDITOR, ROLE_ID_ASSISTANT),
			array('fetchGrid', 'fetchRow')
		);

		// Set the grid information.
		$this->setTitle('editor.submissionReview.restrictFiles.gridTitle');
		$this->setInstructions('editor.submissionReview.restrictFiles.gridDescription');
	}

	/**
	 * @see GridHandler::isDataElementSelected()
	 */
	function isDataElementSelected($gridDataElement) {
		return true;
	}
}

?>

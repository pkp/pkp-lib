<?php
/**
 * @file controllers/grid/files/attachment/EditorSelectableReviewAttachmentsGridHandler.inc.php
 *
 * Copyright (c) 2014-2019 Simon Fraser University
 * Copyright (c) 2003-2019 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class EditorSelectableReviewAttachmentsGridHandler
 * @ingroup controllers_grid_files_attachments
 *
 * @brief Selectable review attachment grid requests (editor's perspective).
 */

import('lib.pkp.controllers.grid.files.fileList.SelectableFileListGridHandler');

class EditorSelectableReviewAttachmentsGridHandler extends SelectableFileListGridHandler {
	/**
	 * Constructor
	 */
	function __construct() {
		import('lib.pkp.controllers.grid.files.review.ReviewGridDataProvider');
		// Pass in null stageId to be set in initialize from request var.
		parent::__construct(
			// This grid lists all review round files, but creates attachments
			new ReviewGridDataProvider(SUBMISSION_FILE_ATTACHMENT, false, true),
			null,
			FILE_GRID_ADD|FILE_GRID_DELETE|FILE_GRID_VIEW_NOTES|FILE_GRID_EDIT
		);

		$this->addRoleAssignment(
			array(ROLE_ID_MANAGER, ROLE_ID_SUB_EDITOR, ROLE_ID_ASSISTANT),
			array('fetchGrid', 'fetchRow')
		);

		// Set the grid title.
		$this->setTitle('grid.reviewAttachments.send.title');
	}

	/**
	 * @copydoc GridHandler::isDataElementSelected()
	 */
	function isDataElementSelected($gridDataElement) {
		$file = $gridDataElement['submissionFile'];
		switch ($file->getFileStage()) {
			case SUBMISSION_FILE_ATTACHMENT: return true;
			case SUBMISSION_FILE_REVIEW_FILE: return false;
		}
		return $file->getViewable();
	}

	/**
	 * @copydoc SelectableFileListGridHandler::getSelectName()
	 */
	function getSelectName() {
		return 'selectedAttachments';
	}
}


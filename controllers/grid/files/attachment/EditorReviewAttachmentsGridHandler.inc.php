<?php

/**
 * @file controllers/grid/files/attachment/EditorReviewAttachmentsGridHandler.inc.php
 *
 * Copyright (c) 2003-2013 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class EditorReviewAttachmentsGridHandler
 * @ingroup controllers_grid_files_attachment
 *
 * @brief Editor's view of the Review Attachments Grid.
 */

import('lib.pkp.controllers.grid.files.fileList.FileListGridHandler');

class EditorReviewAttachmentsGridHandler extends FileListGridHandler {
	/**
	 * Constructor
	 */
	function EditorReviewAttachmentsGridHandler() {
		import('lib.pkp.controllers.grid.files.attachment.ReviewerReviewAttachmentGridDataProvider');
		// Pass in null stageId to be set in initialize from request var.
		parent::FileListGridHandler(
			new ReviewerReviewAttachmentGridDataProvider(SUBMISSION_FILE_REVIEW_ATTACHMENT),
			null,
			FILE_GRID_VIEW_NOTES
		);

		$this->addRoleAssignment(
			array(ROLE_ID_MANAGER, ROLE_ID_SUB_EDITOR, ROLE_ID_ASSISTANT),
			array(
				'fetchGrid', 'fetchRow'
			)
		);
	}
}

?>

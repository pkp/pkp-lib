<?php

/**
 * @file controllers/grid/files/attachment/AuthorOpenReviewAttachmentsGridHandler.inc.php
 *
 * Copyright (c) 2014-2020 Simon Fraser University
 * Copyright (c) 2003-2020 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class AuthorOpenReviewAttachmentsGridHandler
 * @ingroup controllers_grid_files_attachment
 *
 * @brief Handle review attachment grid requests in open reviews (author's perspective)
 */

import('lib.pkp.classes.controllers.grid.GridRow');
import('lib.pkp.controllers.grid.files.fileList.FileListGridHandler');

class AuthorOpenReviewAttachmentsGridHandler extends FileListGridHandler {
	/**
	 * Constructor
	 */
	function __construct() {
		import('lib.pkp.controllers.grid.files.attachment.ReviewerReviewAttachmentGridDataProvider');
		// Pass in null stageId to be set in initialize from request var.
		// Show also files that are not viewable by default
		parent::__construct(
			new ReviewerReviewAttachmentGridDataProvider(SUBMISSION_FILE_REVIEW_ATTACHMENT, false),
			null
		);

		$this->addRoleAssignment(
			array(ROLE_ID_MANAGER, ROLE_ID_SUB_EDITOR, ROLE_ID_ASSISTANT, ROLE_ID_AUTHOR),
			array('fetchGrid', 'fetchRow')
		);

	}
}



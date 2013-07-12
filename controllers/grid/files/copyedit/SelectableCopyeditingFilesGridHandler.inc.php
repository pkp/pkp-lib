<?php

/**
 * @file controllers/grid/files/copyedit/SelectableCopyeditingFilesGridHandler.inc.php
 *
 * Copyright (c) 2000-2013 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class SelectableCopyeditingFilesGridHandler
 * @ingroup controllers_grid_files_copyedit
 *
 * @brief Handle copyediting files grid requests to promote to production stage.
 */

import('lib.pkp.controllers.grid.files.fileList.SelectableFileListGridHandler');

class SelectableCopyeditingFilesGridHandler extends SelectableFileListGridHandler {
	/**
	 * Constructor
	 */
	function SelectableCopyeditingFilesGridHandler() {
		import('lib.pkp.controllers.grid.files.SubmissionFilesGridDataProvider');
		// Pass in null stageId to be set in initialize from request var.
		parent::SelectableFileListGridHandler(
			new SubmissionFilesGridDataProvider(SUBMISSION_FILE_COPYEDIT, true),
			null,
			FILE_GRID_VIEW_NOTES
		);

		$this->addRoleAssignment(
			array(ROLE_ID_MANAGER, ROLE_ID_SUB_EDITOR, ROLE_ID_ASSISTANT),
			array('fetchGrid', 'fetchRow')
		);

		// Set the grid title.
		$this->setTitle('submission.copyediting');

		$this->setInstructions('editor.submission.selectFairCopy');
	}
}

?>

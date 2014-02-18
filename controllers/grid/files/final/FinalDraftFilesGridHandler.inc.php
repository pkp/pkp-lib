<?php

/**
 * @file controllers/grid/files/final/FinalDraftFilesGridHandler.inc.php
 *
 * Copyright (c) 2014 Simon Fraser University Library
 * Copyright (c) 2003-2014 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class FinalDraftFilesGridHandler
 * @ingroup controllers_grid_files_final
 *
 * @brief Handle the final draft files grid (displays files sent to copyediting from the review stage)
 */

import('lib.pkp.controllers.grid.files.fileList.FileListGridHandler');

class FinalDraftFilesGridHandler extends FileListGridHandler {
	/**
	 * Constructor
	 * @param $capabilities integer A bit map with zero or more
	 *  FILE_GRID_* capabilities set.
	 */
	function FinalDraftFilesGridHandler() {
		import('lib.pkp.controllers.grid.files.final.FinalDraftFilesGridDataProvider');
		parent::FileListGridHandler(
			new FinalDraftFilesGridDataProvider(),
			null,
			FILE_GRID_MANAGE|FILE_GRID_VIEW_NOTES
		);
		$this->addRoleAssignment(
			array(
				ROLE_ID_SUB_EDITOR,
				ROLE_ID_MANAGER,
				ROLE_ID_ASSISTANT
			),
			array(
				'fetchGrid', 'fetchRow', 'selectFiles'
			)
		);

		$this->setTitle('submission.finalDraft');
		$this->setInstructions('editor.submission.editorial.finalDraftDescription');
	}

	//
	// Public handler methods
	//
	/**
	 * Show the form to allow the user to select review files
	 * (bring in/take out files from submission stage to review stage)
	 *
	 * FIXME: Move to it's own handler so that it can be re-used among grids.
	 *
	 * @param $args array
	 * @param $request PKPRequest
	 * @return string Serialized JSON object
	 */
	function selectFiles($args, $request) {
		$submission = $this->getSubmission();

		import('lib.pkp.controllers.grid.files.final.form.ManageFinalDraftFilesForm');
		$manageFinalDraftFilesForm = new ManageFinalDraftFilesForm($submission->getId());

		$manageFinalDraftFilesForm->initData($args, $request);
		$json = new JSONMessage(true, $manageFinalDraftFilesForm->fetch($request));
		return $json->getString();
	}
}

?>

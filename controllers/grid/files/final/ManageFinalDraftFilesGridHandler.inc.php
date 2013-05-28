<?php

/**
 * @file controllers/grid/files/final/ManageFinalDraftFilesGridHandler.inc.php
 *
 * Copyright (c) 2003-2013 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class ManageFinalDraftFilesGridHandler
 * @ingroup controllers_grid_files_final
 *
 * @brief Handle the editor review file selection grid (selects which files to send to review or to next review round)
 */

import('lib.pkp.controllers.grid.files.SelectableSubmissionFileListCategoryGridHandler');

class ManageFinalDraftFilesGridHandler extends SelectableSubmissionFileListCategoryGridHandler {
	/**
	 * Constructor
	 */
	function ManageFinalDraftFilesGridHandler() {
		import('lib.pkp.controllers.grid.files.SubmissionFilesCategoryGridDataProvider');
		parent::SelectableSubmissionFileListCategoryGridHandler(
			new SubmissionFilesCategoryGridDataProvider(SUBMISSION_FILE_FINAL),
			WORKFLOW_STAGE_ID_EDITING,
			FILE_GRID_ADD|FILE_GRID_DELETE|FILE_GRID_VIEW_NOTES
		);

		$this->addRoleAssignment(
			array(
				ROLE_ID_SUB_EDITOR,
				ROLE_ID_MANAGER,
				ROLE_ID_ASSISTANT
			),
			array(
				'fetchGrid', 'fetchCategory', 'fetchRow',
				'addFile',
				'downloadFile',
				'deleteFile',
				'updateFinalDraftFiles'
			)
		);

		// Set the grid title.
		$this->setTitle('submission.finalDraft');
	}


	//
	// Public handler methods
	//
	/**
	 * Save 'manage final draft files' form
	 * @param $args array
	 * @param $request PKPRequest
	 * @return string Serialized JSON object
	 */
	function updateFinalDraftFiles($args, $request) {
		$submission = $this->getSubmission();

		import('lib.pkp.controllers.grid.files.final.form.ManageFinalDraftFilesForm');
		$manageFinalDraftFilesForm = new ManageFinalDraftFilesForm($submission->getId());
		$manageFinalDraftFilesForm->readInputData();

		if ($manageFinalDraftFilesForm->validate()) {
			$dataProvider = $this->getDataProvider();
			$manageFinalDraftFilesForm->execute($args, $request, $dataProvider->getCategoryData($this->getStageId()));

			// Let the calling grid reload itself
			return DAO::getDataChangedEvent();
		} else {
			$json = new JSONMessage(false);
			return $json->getString();
		}
	}
}

?>

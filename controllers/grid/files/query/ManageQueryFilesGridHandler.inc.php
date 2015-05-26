<?php

/**
 * @file controllers/grid/files/query/ManageQueryFilesGridHandler.inc.php
 *
 * Copyright (c) 2014-2015 Simon Fraser University Library
 * Copyright (c) 2003-2015 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class ManageQueryFilesGridHandler
 * @ingroup controllers_grid_files_query
 *
 * @brief Handle the query file selection grid
 */

import('lib.pkp.controllers.grid.files.SelectableSubmissionFileListCategoryGridHandler');

class ManageQueryFilesGridHandler extends SelectableSubmissionFileListCategoryGridHandler {
	/**
	 * Constructor
	 */
	function ManageQueryFilesGridHandler() {
		import('lib.pkp.controllers.grid.files.query.QueryFilesCategoryGridDataProvider');
		parent::SelectableSubmissionFileListCategoryGridHandler(
			new QueryFilesCategoryGridDataProvider(),
			WORKFLOW_STAGE_ID_EDITING,
			FILE_GRID_ADD|FILE_GRID_DELETE|FILE_GRID_VIEW_NOTES|FILE_GRID_EDIT
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
				'updateQueryFiles'
			)
		);

		// Set the grid title.
		$this->setTitle('submission.queryFiles');
	}


	//
	// Override methods from SelectableSubmissionFileListCategoryGridHandler
	//
	/**
	 * @copydoc GridHandler::isDataElementInCategorySelected()
	 */
	function isDataElementInCategorySelected($categoryDataId, &$gridDataElement) {
		$submissionFile = $gridDataElement['submissionFile'];

		// Check for special cases when the file needs to be unselected.
		$dataProvider = $this->getDataProvider();
		if ($dataProvider->getFileStage() != $submissionFile->getFileStage()) return false;

		// Passed the checks above. If it's part of the current query, mark selected.
		$query = $this->getAuthorizedContextObject(ASSOC_TYPE_QUERY);
		return ($submissionFile->getAssocType() == ASSOC_TYPE_QUERY && $submissionFile->getAssocId() == $query->getId());
	}

	//
	// Public handler methods
	//
	/**
	 * Save 'manage query files' form
	 * @param $args array
	 * @param $request PKPRequest
	 * @return JSONMessage JSON object
	 */
	function updateQueryFiles($args, $request) {
		$submission = $this->getSubmission();
		$query = $this->getAuthorizedContextObject(ASSOC_TYPE_QUERY);

		import('lib.pkp.controllers.grid.files.query.form.ManageQueryFilesForm');
		$manageQueryFilesForm = new ManageQueryFilesForm($submission->getId(), $query->getId());
		$manageQueryFilesForm->readInputData();

		if ($manageQueryFilesForm->validate()) {
			$manageQueryFilesForm->execute(
				$args, $request,
				$this->getGridCategoryDataElements($request, $this->getStageId())
			);

			// Let the calling grid reload itself
			return DAO::getDataChangedEvent();
		} else {
			return new JSONMessage(false);
		}
	}
}

?>

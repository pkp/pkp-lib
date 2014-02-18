<?php

/**
 * @file controllers/grid/files/productionReady/ProductionReadyFilesGridHandler.inc.php
 *
 * Copyright (c) 2014 Simon Fraser University Library
 * Copyright (c) 2003-2014 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class ProductionReadyFilesGridHandler
 * @ingroup controllers_grid_files_productionready
 *
 * @brief Handle the fair copy files grid (displays copyedited files ready to move to proofreading)
 */

import('lib.pkp.controllers.grid.files.SubmissionFilesGridHandler');
import('lib.pkp.controllers.grid.files.UploaderUserGroupGridColumn');

class ProductionReadyFilesGridHandler extends SubmissionFilesGridHandler {
	/**
	 * Constructor
	 */
	function ProductionReadyFilesGridHandler() {
		import('lib.pkp.controllers.grid.files.SubmissionFilesGridDataProvider');
		parent::SubmissionFilesGridHandler(
			new SubmissionFilesGridDataProvider(SUBMISSION_FILE_PRODUCTION_READY),
			WORKFLOW_STAGE_ID_PRODUCTION,
			FILE_GRID_ADD|FILE_GRID_DELETE|FILE_GRID_VIEW_NOTES
		);

		$this->addRoleAssignment(
			array(
				ROLE_ID_SUB_EDITOR,
				ROLE_ID_MANAGER,
				ROLE_ID_ASSISTANT
			),
			array(
				'fetchGrid', 'fetchRow',
				'addFile',
				'downloadFile',
				'deleteFile',
				'signOffFile'
			)
		);
	}

	/**
	 * @copydoc PKPHandler::initialize()
	 */
	function initialize($request) {
		parent::initialize($request);

		$this->setTitle('editor.submission.production.productionReadyFiles');
		$this->setInstructions('editor.submission.production.productionReadyFilesDescription');

		$currentUser = $request->getUser();

		// Get all the uploader user group id's
		$uploaderUserGroupIds = array();
		$dataElements = $this->getGridDataElements($request);
		foreach ($dataElements as $id => $rowElement) {
			$submissionFile = $rowElement['submissionFile'];
			$uploaderUserGroupIds[] = $submissionFile->getUserGroupId();
		}
		// Make sure each is only present once
		$uploaderUserGroupIds = array_unique($uploaderUserGroupIds);

		// Add a Uploader UserGroup column for each group
		$userGroupDao = DAORegistry::getDAO('UserGroupDAO');
		foreach ($uploaderUserGroupIds as $userGroupId) {
			$userGroup = $userGroupDao->getById($userGroupId);
			assert(is_a($userGroup, 'UserGroup'));
			$this->addColumn(new UploaderUserGroupGridColumn($userGroup));
		}
	}
}

?>

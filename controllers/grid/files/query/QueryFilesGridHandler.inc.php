<?php

/**
 * @file controllers/grid/files/dependent/QueryFilesGridHandler.inc.php
 *
 * Copyright (c) 2014-2015 Simon Fraser University Library
 * Copyright (c) 2003-2015 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class QueryFilesGridHandler
 * @ingroup controllers_grid_files_query
 *
 * @brief Handle query files that are associated with a query
 * The participants of a query have access to the files in this grid.
 */

import('lib.pkp.controllers.grid.files.fileList.FileListGridHandler');

class QueryFilesGridHandler extends FileListGridHandler {
	/**
	 * Constructor
	 */
	function QueryFilesGridHandler() {
		// import app-specific grid data provider for access policies.
		$request = Application::getRequest();
		$stageId = $request->getUservar('stageId'); // authorized in authorize() method.
		import('lib.pkp.controllers.grid.files.dependent.DependentFilesGridDataProvider');
		parent::FileListGridHandler(
			new DependentFilesGridDataProvider($stageId),
			WORKFLOW_STAGE_ID_PRODUCTION,
			FILE_GRID_ADD|FILE_GRID_DELETE|FILE_GRID_VIEW_NOTES|FILE_GRID_EDIT
		);

		$this->addRoleAssignment(
			array(ROLE_ID_MANAGER, ROLE_ID_SUB_EDITOR, ROLE_ID_ASSISTANT, ROLE_ID_AUTHOR),
			array('fetchGrid', 'fetchRow')
		);

		// Set grid title.
		$this->setTitle('submission.queries.attachedFiles');
	}

	/**
	 * @copydoc SubmissionFilesGridHandler::authorize()
	 */
	function authorize($request, $args, $roleAssignments) {
		$stageId = $request->getUserVar('stageId'); // This is being validated in WorkflowStageAccessPolicy
		$this->_stageId = (int)$stageId;

		// Get the stage access policy
		import('classes.security.authorization.WorkflowStageAccessPolicy');
		$workflowStageAccessPolicy = new WorkflowStageAccessPolicy($request, $args, $roleAssignments, 'submissionId', $stageId);
		$this->addPolicy($workflowStageAccessPolicy);
		return parent::authorize($request, $args, $roleAssignments);
	}
}

?>

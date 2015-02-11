<?php

/**
 * @file controllers/grid/files/dependent/QueryFilesGridHandlerinc.php
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
		$fileId = $request->getUservar('fileId'); // authorized in authorize() method.
		import('lib.pkp.controllers.grid.files.dependent.DependentFilesGridDataProvider');
		parent::FileListGridHandler(
			new DependentFilesGridDataProvider($fileId),
			WORKFLOW_STAGE_ID_PRODUCTION,
			FILE_GRID_ADD|FILE_GRID_DELETE|FILE_GRID_VIEW_NOTES|FILE_GRID_EDIT
		);

		$this->addRoleAssignment(
			array(ROLE_ID_MANAGER, ROLE_ID_SUB_EDITOR, ROLE_ID_ASSISTANT, ROLE_ID_AUTHOR),
			array('fetchGrid', 'fetchRow')
		);

		// Set grid title.
		$this->setInstructions('submission.dependent.upload.description');
	}

	/**
	 * @copydoc SubmissionFilesGridHandler::authorize()
	 */
	function authorize($request, $args, $roleAssignments) {
		import('classes.security.authorization.SubmissionFileAccessPolicy');
		$this->addPolicy(new SubmissionFileAccessPolicy($request, $args, $roleAssignments, SUBMISSION_FILE_ACCESS_MODIFY));

		return parent::authorize($request, $args, $roleAssignments);
	}

	/**
	 * @copydoc GridHandler::getRequestArgs()
	 */
	function getRequestArgs() {
		$submissionFile = $this->getAuthorizedContextObject(ASSOC_TYPE_SUBMISSION_FILE);
		return array_merge(
				parent::getRequestArgs(),
				array('fileId' => $submissionFile->getFileId())
		);
	}
}

?>

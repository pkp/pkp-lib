<?php

/**
 * @file controllers/grid/files/copyedit/CopyeditFilesGridHandler.inc.php
 *
 * Copyright (c) 2014-2018 Simon Fraser University
 * Copyright (c) 2003-2018 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class CopyeditFilesGridHandler
 * @ingroup controllers_grid_files_copyedit
 *
 * @brief Handle the copyedited files grid
 */

import('lib.pkp.controllers.grid.files.fileList.FileListGridHandler');

class CopyeditFilesGridHandler extends FileListGridHandler {
	/**
	 * Constructor
	 *  FILE_GRID_* capabilities set.
	 */
	function __construct() {
		import('lib.pkp.controllers.grid.files.copyedit.CopyeditFilesGridDataProvider');
		parent::__construct(
			new CopyeditFilesGridDataProvider(),
			null
		);
		$this->addRoleAssignment(
			array(
				ROLE_ID_SUB_EDITOR,
				ROLE_ID_MANAGER,
				ROLE_ID_ASSISTANT,
				ROLE_ID_AUTHOR,
			),
			array(
				'fetchGrid', 'fetchRow',
			)
		);
		$this->addRoleAssignment(
			array(
				ROLE_ID_SUB_EDITOR,
				ROLE_ID_MANAGER,
				ROLE_ID_ASSISTANT
			),
			array(
				'selectFiles'
			)
		);


		$this->setTitle('submission.copyedited');
	}

	//
	// Public handler methods
	//
	/**
	 * @copydoc GridHandler::initialize()
	 */
	function initialize($request, $args = null) {
		if (0 != count(array_intersect(
			$this->getAuthorizedContextObject(ASSOC_TYPE_USER_ROLES),
			array(ROLE_ID_MANAGER, ROLE_ID_ASSISTANT, ROLE_ID_SUB_EDITOR)
			// Authors may also view this grid, and shouldn't be able to do anything (just view).
		))) {
			$this->setCapabilities(new FilesGridCapabilities(FILE_GRID_EDIT|FILE_GRID_MANAGE|FILE_GRID_VIEW_NOTES));
		}
		parent::initialize($request, $args);
	}

	/**
	 * Show the form to allow the user to select files from previous stages
	 * @param $args array
	 * @param $request PKPRequest
	 * @return JSONMessage JSON object
	 */
	function selectFiles($args, $request) {
		import('lib.pkp.controllers.grid.files.copyedit.form.ManageCopyeditFilesForm');
		$manageCopyeditFilesForm = new ManageCopyeditFilesForm($this->getSubmission()->getId());
		$manageCopyeditFilesForm->initData();
		return new JSONMessage(true, $manageCopyeditFilesForm->fetch($request));
	}
}

?>

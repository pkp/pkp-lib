<?php

/**
 * @file controllers/grid/settings/library/LibraryFileAdminGridHandler.inc.php
 *
 * Copyright (c) 2003-2013 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class LibraryFileAdminGridHandler
 * @ingroup controllers_grid_settings_library
 *
 * @brief Handle library file grid requests.
 */

import('lib.pkp.controllers.grid.files.LibraryFileGridHandler');
import('lib.pkp.controllers.grid.settings.library.LibraryFileAdminGridDataProvider');


class LibraryFileAdminGridHandler extends LibraryFileGridHandler {
	/**
	 * Constructor
	 */
	function LibraryFileAdminGridHandler() {

		parent::LibraryFileGridHandler(new LibraryFileAdminGridDataProvider(true));
		$this->addRoleAssignment(
			array(ROLE_ID_MANAGER),
			array(
				'addFile', 'uploadFile', 'saveFile', // Adding new library files
				'editFile', 'updateFile', // Editing existing library files
				'deleteFile'
			)
		);
	}

	//
	// Overridden template methods
	//

	/*
	 * Configure the grid
	 * @param $request PKPRequest
	 */
	function initialize(&$request) {
		// determine if this grid is read only.
		$this->setCanEdit((boolean) $request->getUserVar('canEdit'));

		// Set instructions
		$this->setInstructions('manager.setup.libraryDescription');
		parent::initialize($request);
	}

	/**
	 * Returns a specific instance of the new form for this grid.
	 * @param $context Context
	 * @return NewLibraryFileForm
	 */
	function &_getNewFileForm($context) {
		import('lib.pkp.controllers.grid.settings.library.form.NewLibraryFileForm');
		$fileForm = new NewLibraryFileForm($context->getId());
		return $fileForm;
	}

	/**
	 * Returns a specific instance of the edit form for this grid.
	 * @param $context Press
	 * @param $fileId int
	 * @return EditLibraryFileForm
	 */
	function &_getEditFileForm($context, $fileId) {
		import('lib.pkp.controllers.grid.settings.library.form.EditLibraryFileForm');
		$fileForm = new EditLibraryFileForm($context->getId(), $fileId);
		return $fileForm;
	}
}

?>

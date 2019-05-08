<?php

/**
 * @file controllers/grid/settings/library/LibraryFileAdminGridHandler.inc.php
 *
 * Copyright (c) 2014-2019 Simon Fraser University
 * Copyright (c) 2003-2019 John Willinsky
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
	function __construct() {

		parent::__construct(new LibraryFileAdminGridDataProvider(true));
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
	 * @see LibraryGridHandler::initialize
	 */
	function initialize($request, $args = null) {
		// determine if this grid is read only.
		$this->setCanEdit((boolean) $request->getUserVar('canEdit'));

		parent::initialize($request, $args);
	}

	/**
	 * Returns a specific instance of the new form for this grid.
	 * @param $context Context
	 * @return NewLibraryFileForm
	 */
	function _getNewFileForm($context) {
		import('lib.pkp.controllers.grid.settings.library.form.NewLibraryFileForm');
		return new NewLibraryFileForm($context->getId());
	}

	/**
	 * Returns a specific instance of the edit form for this grid.
	 * @param $context Context
	 * @param $fileId int
	 * @return EditLibraryFileForm
	 */
	function _getEditFileForm($context, $fileId) {
		import('lib.pkp.controllers.grid.settings.library.form.EditLibraryFileForm');
		return new EditLibraryFileForm($context->getId(), $fileId);
	}
}



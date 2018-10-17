<?php

/**
 * @file controllers/grid/files/SelectableLibraryFileGridHandler.inc.php
 *
 * Copyright (c) 2014-2018 Simon Fraser University
 * Copyright (c) 2003-2018 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class SelectableLibraryFileGridHandler
 * @ingroup controllers_grid_files
 *
 * @brief Handle selectable library file list category grid requests.
 */

// Import library files grid specific classes.
import('lib.pkp.controllers.grid.files.LibraryFileGridHandler');
import('lib.pkp.controllers.grid.settings.library.LibraryFileAdminGridDataProvider');

class SelectableLibraryFileGridHandler extends LibraryFileGridHandler {
	/**
	 * Constructor
	 */
	function __construct() {

		parent::__construct(new LibraryFileAdminGridDataProvider(true));
	}

	/**
	 * @copydoc GridHandler::initFeatures()
	 */
	function initFeatures($request, $args) {
		import('lib.pkp.classes.controllers.grid.feature.selectableItems.SelectableItemsFeature');
		return array(new SelectableItemsFeature());
	}

	/**
	 * @copydoc GridHandler::isDataElementInCategorySelected()
	 */
	function isDataElementInCategorySelected($categoryDataId, &$gridDataElement) {
		return false;
	}

	/**
	 * Get the selection name.
	 * @return string
	 */
	function getSelectName() {
		return 'selectedLibraryFiles';
	}
}



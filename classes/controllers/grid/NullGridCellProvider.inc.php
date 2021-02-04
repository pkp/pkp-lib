<?php

/**
 * @file classes/controllers/grid/NullGridCellProvider.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class NullGridCellProvider
 * @ingroup controllers_grid
 *
 * @brief Class to return null when render method is called by a grid handler.
 * Use this when you want to create a column with no content at all (for layout
 * purposes using flags, for example).
 */

import('lib.pkp.classes.controllers.grid.GridCellProvider');

class NullGridCellProvider extends GridCellProvider {

	//
	// Template methods from GridCellProvider
	//
	/**
	 * @see GridCellProvider::render()
	 */
	function render($request, $row, $column) {
		return null;
	}
}



<?php

/**
 * @file classes/controllers/grid/ColumnBasedGridCellProvider.inc.php
 *
 * Copyright (c) 2000-2011 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class ColumnBasedGridCellProvider
 * @ingroup controllers_grid
 *
 * @brief A cell provider that relies on the column implementation
 *  to provide cell content. Use this cell provider if you have complex
 *  column-specific content. If you want to provide simple labels then
 *  use the ArrayGridCellProvider or DataObjectGridCellProvider.
 *
 * @see ArrayGridCellProvider
 * @see DataObjectGridCellProvider
 */

import('lib.pkp.classes.controllers.grid.GridCellProvider');

class ColumnBasedGridCellProvider extends GridCellProvider {
	/**
	 * Constructor
	 */
	function ColumnBasedGridCellProvider() {
		parent::GridCellProvider();
	}


	//
	// Implement protected template methods from GridCellProvider
	//
	/**
	 * @see GridCellProvider::getTemplateVarsFromRowColumn()
	 */
	function getTemplateVarsFromRowColumn(&$row, $column) {
		// Delegate to the colum to provide template variables.
		return $column->getTemplateVarsFromRow($row);
	}
}

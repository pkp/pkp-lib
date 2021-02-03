<?php

/**
 * @file classes/controllers/grid/LiteralGridCellProvider.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class LiteralGridCellProvider
 * @ingroup controllers_grid
 *
 * @brief A cell provider that passes literal data through directly.
 */

import('lib.pkp.classes.controllers.grid.GridCellProvider');

class LiteralGridCellProvider extends GridCellProvider {

	//
	// Template methods from GridCellProvider
	//
	/**
	 * This implementation assumes a data element that is a literal value.
	 * If desired, the 'id' column can be used to present the row ID.
	 * @see GridCellProvider::getTemplateVarsFromRowColumn()
	 * @param $row GridRow
	 * @param $column GridColumn
	 * @return array
	 */
	function getTemplateVarsFromRowColumn($row, $column) {
		switch ($column->getId()) {
			case 'id':
				return array('label' => $row->getId());
			case 'value':
			default:
				return array('label' => $row->getData());
		}
	}
}



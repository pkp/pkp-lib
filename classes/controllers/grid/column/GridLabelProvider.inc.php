<?php

/**
 * @file classes/controllers/grid/column/GridLabelProvider.inc.php
 *
 * Copyright (c) 2000-2009 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class GridLabelProvider
 * @ingroup controllers_grid_column
 *
 * @brief Base class for a grid column's label provider
 */


class GridLabelProvider {
	/**
	 * Constructor
	 */
	function GridLabelProvider() {
	}

	/**
	 * To be used by a GridCellHandler to generate a string representation of
	 * the element for the given column.
	 * @param $element mixed a single data element
	 * @param $columnId
	 * @return string the string representation of the element for the given column
	 */
	function getLabel($element, $columnId) {
		// Subclasses will implement this to transform a data element
		// into a cell that can be rendered by the cell template.
		return $element[$columnId];
	}
}
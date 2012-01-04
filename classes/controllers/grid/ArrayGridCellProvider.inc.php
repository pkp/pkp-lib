<?php

/**
 * @file classes/controllers/grid/ArrayGridCellProvider.inc.php
 *
 * Copyright (c) 2000-2012 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class ArrayGridCellProvider
 * @ingroup controllers_grid
 *
 * @brief Base class for a cell provider that can retrieve labels from arrays
 */

import('controllers.grid.GridCellProvider');

class ArrayGridCellProvider extends GridCellProvider {
	/**
	 * Constructor
	 */
	function ArrayGridCellProvider() {
		parent::GridCellProvider();
	}

	//
	// Template methods from GridCellProvider
	//
	/**
	 * This implementation assumes a simple data element array that
	 * has column ids as keys.
	 * @see GridCellProvider::getLabel()
	 * @param $element array
	 * @param $columnId string
	 */
	function getTemplateVarsFromElement(&$element, $columnId) {
		assert(is_array($element) && isset($element[$columnId]));
		return array('label' => $element[$columnId]);
	}
}
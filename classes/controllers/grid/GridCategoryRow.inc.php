<?php

/**
 * @file classes/controllers/grid/GridCategoryRow.inc.php
 *
 * Copyright (c) 2000-2012 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class GridCategoryRow
 * @ingroup controllers_grid
 *
 * @brief Class defining basic operations for handling the category row in a grid
 *
 */
import('controllers.grid.GridRow');

class GridCategoryRow extends GridRow {
	/**
	 * Constructor.
	 */
	function GridCategoryRow() {
		parent::GridRow();
	}

	//
	// Getters/Setters
	//

	/**
	 * Get the row template - override base
	 * implementation to provide a sensible default.
	 * @return string
	 */
	function getTemplate() {
		if (is_null($this->_template)) {
			$this->setTemplate('controllers/grid/gridCategoryRow.tpl');
		}

		return $this->_template;
	}

	/**
	 * Category rows only have one cell and one label.  This is it.
	 * @param string $categoryName
	 * return string
	 */
	function getCategoryLabel() {
		return '';
	}
}
?>
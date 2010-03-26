<?php

/**
 * @file classes/controllers/grid/GridCategoryRow.inc.php
 *
 * Copyright (c) 2000-2010 John Willinsky
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
	/** @var categoryName being grouped by**/
	var $_categoryName;

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
	 * Get the category name being grouped by
	 */
	function getCategoryName() {
		return $this->_categoryName;
	}

	/**
	 * Set the category name being grouped by
	 * @param string $categoryName
	 */

	function setCategoryName($categoryName) {
		$this->_categoryName = $categoryName;
	}
	/**
	 * Category rows only have one cell and one label.  This is it.
	 * @param string $categoryName
	 * return string
	 */
	function getLabel() {
		$data = $this->getData();
		//TODO: test this.  Probably need to convert to associative array for this to work.
		return $data[$this->_categoryName];
	}
}
?>
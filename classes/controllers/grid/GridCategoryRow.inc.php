<?php

/**
 * @file classes/controllers/grid/GridCategoryRow.inc.php
 *
 * Copyright (c) 2000-2011 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class GridCategoryRow
 * @ingroup controllers_grid
 *
 * @brief Class defining basic operations for handling the category row in a grid
 *
 */
import('lib.pkp.classes.controllers.grid.GridRow');

class GridCategoryRow extends GridRow {
	/** @var string empty row locale key */
	var $_emptyCategoryRowText = 'grid.noItems';

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
	 * Get the no items locale key
	 */
	function getEmptyCategoryRowText() {
		return $this->_emptyCategoryRowText;
	}

	/**
	 * Set the no items locale key
	 */
	function setEmptyCategoryRowText($emptyCategoryRowText) {
		$this->_emptyCategoryRowText = $emptyCategoryRowText;
	}

	/**
	 * Category rows only have one cell and one label.  This is it.
	 * @param string $categoryName
	 * return string
	 */
	function getCategoryLabel() {
		return '';
	}


	//
	// Public methods
	//
	function initialize($request, $template = 'controllers/grid/gridCategoryRow.tpl') {
		parent::initialize($request, $template);
	}
}

?>

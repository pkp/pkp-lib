<?php

/**
 * @file classes/controllers/grid/GridColumn.inc.php
 *
 * Copyright (c) 2013-2015 Simon Fraser University Library
 * Copyright (c) 2000-2015 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class GridColumn
 * @ingroup controllers_grid
 *
 * @brief Represents a column within a grid. It is used to configure the way
 *  cells within a column are displayed (cell provider) and can also be used
 *  to configure a editing strategy (not yet implemented). Contains all column-
 *  specific configuration (e.g. column title).
 */

define('COLUMN_ALIGNMENT_LEFT', 'left');
define('COLUMN_ALIGNMENT_CENTER', 'center');
define('COLUMN_ALIGNMENT_RIGHT', 'right');

import('lib.pkp.classes.controllers.grid.GridBodyElement');

class GridColumn extends GridBodyElement {
	/** @var string the column title i18n key */
	var $_title;

	/** @var string the column title (translated) */
	var $_titleTranslated;

	/** @var string the controller template for the cells in this column */
	var $_template;

	/**
	 * Constructor
	 */
	function GridColumn($id = '', $title = null, $titleTranslated = null,
			$template = 'controllers/grid/gridCell.tpl', $cellProvider = null, $flags = array()) {

		parent::GridBodyElement($id, $cellProvider, $flags);

		$this->_title = $title;
		$this->_titleTranslated = $titleTranslated;
		$this->_template = $template;
	}

	//
	// Setters/Getters
	//
	/**
	 * Get the column title
	 * @return string
	 */
	function getTitle() {
		return $this->_title;
	}

	/**
	 * Set the column title (already translated)
	 * @param $title string
	 */
	function setTitle($title) {
		$this->_title = $title;
	}

	/**
	 * Set the column title (already translated)
	 * @param $title string
	 */
	function setTitleTranslated($titleTranslated) {
		$this->_titleTranslated = $titleTranslated;
	}

	/**
	 * Get the translated column title
	 * @return string
	 */
	function getLocalizedTitle() {
		if ( $this->_titleTranslated ) return $this->_titleTranslated;
		return __($this->_title);
	}

	/**
	 * get the column's cell template
	 * @return string
	 */
	function getTemplate() {
		return $this->_template;
	}

	/**
	 * set the column's cell template
	 * @param $template string
	 */
	function setTemplate($template) {
		$this->_template = $template;
	}

	/**
	 * @see GridBodyElement::getCellProvider()
	 */
	function getCellProvider() {
		if (is_null(parent::getCellProvider())) {
			// provide a sensible default cell provider
			import('lib.pkp.classes.controllers.grid.ArrayGridCellProvider');
			$cellProvider = new ArrayGridCellProvider();
			$this->setCellProvider($cellProvider);
		}

		return parent::getCellProvider();
	}

	/**
	 * Get cell actions for this column.
	 *
	 * NB: Subclasses have to override this method to
	 * actually provide cell-specific actions. The default
	 * implementation returns an empty array.
	 *
	 * @param $row GridRow The row for which actions are
	 *  being requested.
	 * @return array An array of LinkActions for the cell.
	 */
	function getCellActions(&$request, &$row, $position = GRID_ACTION_POSITION_DEFAULT) {
		// The default implementation returns an empty array
		$actions = array();
		return $actions;
	}
}

?>

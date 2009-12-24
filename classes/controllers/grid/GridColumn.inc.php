<?php

/**
 * @file classes/controllers/grid/column/GridColumn.inc.php
 *
 * Copyright (c) 2000-2009 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class GridColumn
 * @ingroup controllers_grid_column
 *
 * @brief Represents a column within a grid. It is used to configure the way
 *  cells within a column are displayed (cell provider) and can also be used
 *  to configure a editing strategy (not yet implemented). Contains all column-
 *  specific configuration (e.g. column title).
 */

// $Id$

class GridColumn {
	/** @var string the column id */
	var $_id;

	/** @var string the column title */
	var $_title;

	/**
	 * @var array actions of the grid column, the first key represents
	 *  the position of the action in the controller
	 */
	var $_actions;

	/** @var string the controller template for the cells in this column */
	var $_template;

	/** @var GridCellProvider a cell provider for cells in this column */
	var $_cellProvider;

	/**
	 * Constructor
	 */
	function GridColumn($id = '', $title = '', &$actions = array(),
			$template = 'controllers/grid/gridCell.tpl', $cellProvider = null) {
		$this->_id = $id;
		$this->_title = $title;
		$this->_actions = array(GRID_ACTION_POSITION_DEFAULT => &$actions);
		$this->_template = $template;
		$this->_cellProvider =& $cellProvider;
	}

	//
	// Setters/Getters
	//
	/**
	 * Get the column id
	 * @return string
	 */
	function getId() {
		return $this->_id;
	}

	/**
	 * Set the column id
	 * @param $id string
	 */
	function setId($id) {
		$this->_id = $id;
	}


	/**
	 * Get the column title
	 * @return string
	 */
	function getTitle() {
		return $this->_title;
	}

	/**
	 * Set the column title
	 * @param $title string
	 */
	function setTitle($title) {
		$this->_title = $title;
	}

	/**
	 * Get the translated column title
	 * @return string
	 */
	function getLocalizedTitle() {
		return Locale::translate($this->_title);;
	}

	/**
	 * Get all actions for a given position within the column
	 * @param $position string the position of the actions
	 * @return array the GridActions for the given position
	 */
	function getActions($position = GRID_ACTION_POSITION_DEFAULT) {
		assert(isset($this->_actions[$position]));
		return $this->_actions[$position];
	}

	/**
	 * Add an action
	 * @param $position string the position of the action
	 * @param $action mixed a single action
	 */
	function addAction($action, $position = GRID_ACTION_POSITION_DEFAULT) {
		if (!isset($this->_actions[$position])) $this->_actions[$position] = array();
		$this->_actions[$position][] = $action;
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
	 * Get the cell provider
	 * @return GridCellProvider
	 */
	function &getCellProvider() {
		if (is_null($this->_cellProvider)) {
			// provide a sensible default cell provider
			import('controllers.grid.GridCellProvider');
			$cellProvider =& new GridCellProvider();
			$this->setCellProvider($cellProvider);
		}
		return $this->_cellProvider;
	}

	/**
	 * Set the cell provider
	 * @param $cellProvider GridCellProvider
	 */
	function setCellProvider(&$cellProvider) {
		$this->_cellProvider =& $cellProvider;
	}
}
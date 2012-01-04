<?php

/**
 * @file classes/controllers/grid/GridRow.inc.php
 *
 * Copyright (c) 2000-2012 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class GridRow
 * @ingroup controllers_grid
 *
 * @brief Class defining basic operations for handling HTML gridRows.
 *
 * NB: If you want row-level refresh then you must override the getData() method
 *  so that it fetches data (e.g. from the database) when called. The data to be
 *  fetched can be determined from the id (=row id) which is always set.
 */

class GridRow {
	/**
	 * @var string identifier of the row instance - must be unique
	 *  among all row instances within a grid.
	 */
	var $_id;

	/** @var the grid this row belongs to */
	var $_gridId;

	/** @var mixed the row's data source */
	var $_data;

	/**
	 * @var array row actions, the first key represents
	 *  the position of the action in the row template,
	 *  the second key represents the action id.
	 */
	var $_actions = array(GRID_ACTION_POSITION_DEFAULT => array());

	/** @var string the row template */
	var $_template;


	/**
	 * Constructor.
	 */
	function GridRow() {
	}


	//
	// Getters/Setters
	//
	/**
	 * Set the grid id
	 * @param $id string
	 */
	function setId($id) {
		$this->_id = $id;
	}

	/**
	 * Get the grid id
	 * @return string
	 */
	function getId() {
		return $this->_id;
	}

	/**
	 * Set the grid id
	 * @param $gridId string
	 */
	function setGridId($gridId) {
		$this->_gridId = $gridId;
	}

	/**
	 * Get the grid id
	 * @return string
	 */
	function getGridId() {
		return $this->_gridId;
	}

	/**
	 * Set the data element(s) for this controller
	 * @param $data mixed
	 */
	function setData(&$data) {
		$this->_data =& $data;
	}

	/**
	 * Get the data element(s) for this controller
	 * @return mixed
	 */
	function &getData() {
		return $this->_data;
	}

	/**
	 * Get all actions for a given position within the controller
	 * @param $position string the position of the actions
	 * @return array the GridActions for the given position
	 */
	function getActions($position = GRID_ACTION_POSITION_DEFAULT) {
		if(!isset($this->_actions[$position])) return array();
		return $this->_actions[$position];
	}

	/**
	 * Add an action
	 * @param $position string the position of the action
	 * @param $action mixed a single action
	 */
	function addAction($action, $position = GRID_ACTION_POSITION_DEFAULT) {
		if (!isset($this->_actions[$position])) $this->_actions[$position] = array();
		$this->_actions[$position][$action->getId()] = $action;
	}

	/**
	 * Get the row template - override base
	 * implementation to provide a sensible default.
	 * @return string
	 */
	function getTemplate() {
		if (is_null($this->_template)) {
			$this->setTemplate('controllers/grid/gridRow.tpl');
		}

		return $this->_template;
	}

	/**
	 * Set the controller template
	 * @param $template string
	 */
	function setTemplate($template) {
		$this->_template = $template;
	}

	//
	// Public methods
	//
	/**
	 * Initialize a row instance.
	 *
	 * Subclasses can override this method.
	 *
	 * @param $request Request
	 */
	function initialize($request) {
		// Default implementation does nothing
	}
}
?>
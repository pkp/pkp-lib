<?php

/**
 * @file classes/controllers/grid/GridHandler.inc.php
 *
 * Copyright (c) 2000-2010 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class GridHandler
 * @ingroup controllers_grid
 *
 * @brief Class defining the base grid controller handler (grids, grid rows, grid cells)
 */

// $Id$

// import GridActions
import('controllers.grid.GridAction');

// import the base Handler
import('handler.PKPHandler');

// import JSON class for use with all AJAX requests
import('core.JSON');

define('GRID_ACTION_POSITION_DEFAULT', 'default');

class GridHandler extends PKPHandler {
	/** @var boolean true, if grid controller has been initialized */
	var $_initialized = false;

	/**
	 * @var string identifier of the controller instance - must be unique
	 *  among all instances of a given controller type.
	 */
	var $_id;

	/** @var mixed the controller's data source */
	var $_data;

	/**
	 * @var array actions of the grid controller, the first key represents
	 *  the position of the action in the controller, the second key
	 *  represents the action id.
	 */
	var $_actions = array(GRID_ACTION_POSITION_DEFAULT => array());

	/** @var string the controller template */
	var $_template;

	/**
	 * Constructor.
	 */
	function GridHandler() {
		parent::PKPHandler();
	}

	//
	// Getters/Setters
	//
	/**
	 * Protected setter that marks the grid controller as initialized
	 */
	function _setInitialized() {
		$this->_initialized = true;
	}

	/**
	 * Get the initialization state of the grid controller instance
	 * @return boolen true, if already initialized, otherwise false
	 */
	function getInitialized() {
		return $this->_initialized;
	}

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
	 * Get the controller template
	 * @return string
	 */
	function getTemplate() {
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
	// Public methods to be overridden by sub-classes
	// or used by other classes
	//
	/**
	 * Subclasses should override this method to configure the
	 * grid controller (i.e. add columns, set the title, id, etc.)
	 * @param $request PKPRequest
	 */
	function initialize(&$request) {
		// Initialize only once
		if ($this->getInitialized()) return;

		// Load grid-specific translations
		Locale::requireComponents(array(LOCALE_COMPONENT_PKP_GRID));

		// Default implementation only sets internal state variable
		$this->_setInitialized();
	}
}
?>
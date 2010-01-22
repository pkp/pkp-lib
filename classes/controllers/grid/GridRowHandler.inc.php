<?php

/**
 * @file classes/controllers/grid/GridRow.inc.php
 *
 * Copyright (c) 2000-2010 John Willinsky
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

import('controllers.grid.GridHandler');
import('controllers.grid.GridColumn');

class GridRowHandler extends GridHandler {
	/** @var string the id of the grid this row belongs to */
	var $_gridId;

	/** @var array the columns of this grid row */
	var $_columns = array();

	/**
	 * Constructor.
	 */
	function GridRowHandler() {
		parent::GridHandler();
	}

	//
	// Getters/Setters
	//

	/**
	 * Get the id of the grid this row belongs to
	 * @return string
	 */
	function getGridId() {
		return $this->_gridId;
	}

	/**
	 * Set the id of the grid this row belongs to
	 * @param $gridId string
	 */
	function setGridId($gridId) {
		$this->_gridId = $gridId;
	}

	/**
	 * Get all columns
	 * @return array an array of GridColumn instances
	 */
	function &getColumns() {
		return $this->_columns;
	}

	/**
	 * Retrieve a single column by id
	 * @param $columnId
	 * @return GridColumn
	 */
	function &getColumn($columnId) {
		assert(isset($this->_columns[$columnId]));
		return $this->_columns[$columnId];
	}

	/**
	 * Checks whether a column exists
	 * @param $columnId
	 * @return boolean
	 */
	function hasColumn($columnId) {
		return isset($this->_columns[$columnId]);
	}

	/**
	 * Add a column
	 * @param $column mixed a single GridColumn instance
	 */
	function addColumn(&$column) {
		assert(is_a($column, 'GridColumn'));
		$this->_columns[$column->getId()] =& $column;
	}

	/**
	 * Get the row template - override base
	 * implementation to provide a sensible default.
	 * @return string
	 */
	function getTemplate() {
		if (is_null(parent::getTemplate())) {
			$this->setTemplate('controllers/grid/gridRow.tpl');
		}

		return parent::getTemplate();
	}

	/**
	 * @see lib/pkp/classes/handler/PKPHandler#getRemoteOperations()
	 */
	function getRemoteOperations() {
		return array('fetchRow', 'fetchCell');
	}

	//
	// Public handler methods
	//
	/**
	 * Render the whole row
	 * @return string the row HTML
	 */
	function fetchRow(&$args, &$request) {
		//FIXME: add validation here:

		// Render the row
		$this->_configureRow($request, $args);
		return $this->renderRowInternally($request);
	}

	/**
	 * Render a cell only
	 * @return string the row HTML
	 */
	function fetchCell(&$args, &$request) {
		//FIXME: add validation here?

		// Configure the row
		$this->_configureRow($request, $args);

		// Check the column
		if(!isset($args['columnId']) || !$this->hasColumn($args['columnId'])) fatalError('Invalid or missing column id.');

		// Render the cell
		return $this->_renderCellInternally($request, $this->getColumn($args['columnId']));
	}

	//
	// Public methods
	//
	/**
	 * Method that renders the row
	 * @param $request PKPRequest
	 * @return string the row HTML
	 */
	function renderRowInternally(&$request) {
		// Let the subclass configure the grid row instance
		$this->initialize($request);
		$this->_configureRow($request);

		// get an array of the cells
		$cells = array();
		foreach ($this->getColumns() as $column) {
			assert(is_a($column, 'GridColumn'));
			$cells[] = $this->_renderCellInternally($request, $column);
		}

		// Pass control to the view to render the row
		$templateMgr =& TemplateManager::getManager();
		$templateMgr->assign_by_ref('cells', $cells);
		$templateMgr->assign_by_ref('row', $this);
		return $templateMgr->fetch($this->getTemplate());
	}

	//
	// Private helper methods
	//
	/**
	 * Method that renders a cell
	 * @param $request PKPRequest
	 * @param $column GridColumn
	 * @return string the cell HTML
	 */
	function _renderCellInternally(&$request, &$column) {
		// Let the subclass configure the grid row instance
		$this->initialize($request);
		$this->_configureRow($request);

		// Get the cell content
		$cellProvider =& $column->getCellProvider();
		return $cellProvider->render($this, $column);
	}

	/**
	 * Configure a row
	 * if $args is present, retrieve row and grid id from the request arguments
	 * @param $request Request
	 * @param $args array
	 */
	function _configureRow($request, $args = null) {
		// if the $args is present, then it must include at least rowId and gridId
		if ( is_array($args) && count($args) ) {
			$gridId =  isset($args['gridId']) ? $args['gridId'] : null;
			$rowId =  isset($args['rowId']) ? $args['rowId'] : null;

			// Set the grid id
			$this->setGridId($gridId);
			// Set the row id
			$this->setId($rowId);
		}
	}
}

?>
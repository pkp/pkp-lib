<?php

/**
 * @file classes/controllers/grid/GridHandler.inc.php
 *
 * Copyright (c) 2000-2011 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class GridHandler
 * @ingroup classes_controllers_grid
 *
 * @brief Class defining basic operations for handling HTML grids.
 */

// Import the base Handler.
import('lib.pkp.classes.handler.PKPHandler');

// Import action class.
import('lib.pkp.classes.linkAction.LinkAction');
import('lib.pkp.classes.linkAction.LegacyLinkAction');

// Import grid classes.
import('lib.pkp.classes.controllers.grid.GridColumn');
import('lib.pkp.classes.controllers.grid.GridRow');

// Import JSON class for use with all AJAX requests.
import('lib.pkp.classes.core.JSON');

// Grid specific action positions.
define('GRID_ACTION_POSITION_DEFAULT', 'default');
define('GRID_ACTION_POSITION_ABOVE', 'above');
define('GRID_ACTION_POSITION_LASTCOL', 'lastcol');
define('GRID_ACTION_POSITION_BELOW', 'below');

class GridHandler extends PKPHandler {
	/** @var string grid title */
	var $_title = '';

	/**
	 * @var array Grid actions. The first key represents
	 *  the position of the action in the grid, the second key
	 *  represents the action id.
	 */
	var $_actions = array(GRID_ACTION_POSITION_DEFAULT => array());

	/** @var array The GridColumns of this grid. */
	var $_columns = array();

	/** @var ItemIterator The grid's data source. */
	var $_data;

	/** @var string The grid template. */
	var $_template;

	/** @var string Name of the row identifer (passed to getData()). */
	var $_rowIdentifer;

	/**
	 * Constructor.
	 */
	function GridHandler() {
		parent::PKPHandler();
	}


	//
	// Getters and Setters
	//
	/**
	 * Get the grid title.
	 * @return string
	 */
	function getTitle() {
		return $this->_title;
	}

	/**
	 * Set the grid title.
	 * @param $title string
	 */
	function setTitle($title) {
		$this->_title = $title;
	}

	/**
	 * Get all actions for a given position within the grid.
	 * @param $position string The position of the actions.
	 * @return array The LegacyLinkActions for the given position.
	 */
	function getActions($position = GRID_ACTION_POSITION_ABOVE) {
		if(!isset($this->_actions[$position])) return array();
		return $this->_actions[$position];
	}

	/**
	 * Add an action.
	 * @param $position string The position of the action.
	 * @param $action Mixed a single action.
	 */
	function addAction($action, $position = GRID_ACTION_POSITION_ABOVE) {
		if (!isset($this->_actions[$position])) $this->_actions[$position] = array();
		$this->_actions[$position][$action->getId()] = $action;
	}

	/**
	 * Get all columns.
	 * @return array An array of GridColumn instances.
	 */
	function &getColumns() {
		return $this->_columns;
	}

	/**
	 * Retrieve a single column by id.
	 * @param $columnId
	 * @return GridColumn
	 */
	function &getColumn($columnId) {
		assert(isset($this->_columns[$columnId]));
		return $this->_columns[$columnId];
	}

	/**
	 * Checks whether a column exists.
	 * @param $columnId
	 * @return boolean
	 */
	function hasColumn($columnId) {
		return isset($this->_columns[$columnId]);
	}

	/**
	 * Add a column.
	 * @param $column mixed A single GridColumn instance.
	 */
	function addColumn(&$column) {
		assert(is_a($column, 'GridColumn'));
		$this->_columns[$column->getId()] =& $column;
	}

	/**
	 * Get the grid data.
	 * @return ItemIterator
	 */
	function &getData() {
		if (is_null($this->_data)) {
			// initialize data to an empty iterator
			import('lib.pkp.classes.core.ItemIterator');
			$elementIterator = new ItemIterator();
			$this->setData($elementIterator);
		}

		// Make a copy of the iterator (iterators
		// "auto-destroy" after one-time use...)
		assert(is_a($this->_data, 'ItemIterator'));
		$elementIterator =& cloneObject($this->_data);
		return $elementIterator;
	}

	/**
	 * Check whether the grid has rows.
	 * @return boolean
	 */
	function hasData() {
		$data =& $this->getData();
		assert (is_a($data, 'ItemIterator'));
		$hasData = $data->getCount() ? true : false;
		return $hasData;
	}

	/**
	 * Set the grid data.
	 * @param $data mixed an array or ItemIterator with element data
	 */
	function setData(&$data, $rowIdentifier = null) {
		if (is_a($data, 'ItemIterator')) {
			$this->_data =& $data;
		} elseif(is_array($data)) {
			import('lib.pkp.classes.core.ArrayItemIterator');
			$this->_data = new ArrayItemIterator($data);
		} else {
			assert(false);
		}

		if($rowIdentifier) $this->setRowIdentifier($rowIdentifier);
	}

	/**
	 * Get the grid template.
	 * @return string
	 */
	function getTemplate() {
		if (is_null($this->_template)) {
			$this->setTemplate('controllers/grid/grid.tpl');
		}

		return $this->_template;
	}

	/**
	 * Set the grid template.
	 * @param $template string
	 */
	function setTemplate($template) {
		$this->_template = $template;
	}

	/**
	 * Override this method to return true if you want
	 * to use the grid within another component (e.g. to
	 * remove the title or change the layout accordingly).
	 *
	 * @return boolean
	 */
	function getIsSubcomponent() {
		return false;
	}

	/**
	 * Set the custom row identifier.
	 * @param $rowIdentifer string
	 */
	function setRowIdentifier($rowIdentifer) {
	    $this->_rowIdentifer = $rowIdentifer;
	}

	/**
	 * Get the custom row identifier.
	 * @return string
	 */
	function getRowIdentifier() {
	    return $this->_rowIdentifer;
	}


	//
	// Overridden methods from PKPHandler
	//
	/**
	 * @see PKPHandler::initialize()
	 */
	function initialize(&$request, $args = null) {
		parent::initialize($request, $args);

		// Load grid-specific translations
		Locale::requireComponents(array(LOCALE_COMPONENT_PKP_GRID, LOCALE_COMPONENT_APPLICATION_COMMON));
	}


	//
	// Public handler methods
	//
	/**
	 * Render the entire grid controller and send
	 * it to the client.
	 * @param $args array
	 * @param $request Request
	 * @param $fetchParams array additional params to assign to the
	 *  template for the fetch URLs
	 * @return string the serialized grid JSON message
	 */
	function fetchGrid($args, &$request, $fetchParams = array()) {

		// Prepare the template to render the grid
		$templateMgr =& TemplateManager::getManager();
		$templateMgr->assign_by_ref('grid', $this);

		// Add columns to the view
		$columns =& $this->getColumns();
		$templateMgr->assign_by_ref('columns', $columns);
		$templateMgr->assign('numColumns', count($columns));

		// Render the body elements
		$gridBodyParts = $this->_renderGridBodyPartsInternally($request);
		$templateMgr->assign_by_ref('gridBodyParts', $gridBodyParts);

		// Assign additional params for the fetchRow and fetchGrid URLs to use
		$templateMgr->assign('fetchParams', $fetchParams);

		// Let the view render the grid
		$json = new JSON(true, $templateMgr->fetch($this->getTemplate()));
		return $json->getString();
	}

	/**
	 * Render a row and send it to the client. If the row no
	 * longer exists then inform the client.
	 * @param $args array
	 * @param $request Request
	 * @return string the serialized row JSON message or a flag
	 *  that indicates that the row has not been found.
	 */
	function fetchRow(&$args, &$request) {
		// Instantiate the requested row (includes a
		// validity check on the row id).
		$row =& $this->getRequestedRow($request, $args);

		$json = new JSON(true);
		if (is_null($row)) {
			// Inform the client that the row does no longer exist.
			$json->setAdditionalAttributes(array('rowNotFound' => (int)$args['rowId']));
		} else {
			// Render the requested row
			$json->setContent($this->_renderRowInternally($request, $row));
		}

		// Render and return the JSON message.
		return $json->getString();
	}

	/**
	 * Render a cell and send it to the client
	 * @param $args array
	 * @param $request Request
	 * @return string the serialized cell JSON message
	 */
	function fetchCell(&$args, &$request) {
		// Check the requested column
		if(!isset($args['columnId'])) fatalError('Missing column id!');
		if(!$this->hasColumn($args['columnId'])) fatalError('Invalid column id!');
		$column =& $this->getColumn($args['columnId']);

		// Instantiate the requested row
		$row =& $this->getRequestedRow($request, $args);
		if (is_null($row)) fatalError('Row not found!');

		// Render the cell
		$json = new JSON(true, $this->_renderCellInternally($request, $row, $column));
		return $json->getString();
	}


	//
	// Protected methods to be overridden/used by subclasses
	//
	/**
	 * Get a new instance of a grid row. May be
	 * overridden by subclasses if they want to
	 * provide a custom row definition.
	 * @return GridRow
	 */
	function &getRowInstance() {
		//provide a sensible default row definition
		$row = new GridRow();
		return $row;
	}

	/**
	 * Tries to identify the data element in the grids
	 * data source that corresponds to the requested row id.
	 * Raises a fatal error if such an element cannot be
	 * found.
	 * @param $request PKPRequest
	 * @param $args array
	 * @return GridRow the requested grid row, already
	 *  configured with id and data or null if the row
	 *  could not been found.
	 */
	function &getRequestedRow($request, $args) {
		// Try to retrieve a row id from $args if it is present
		if(!isset($args['rowId'])) fatalError('Missing row id!');
		$elementId = $args['rowId'];

		// Retrieve row data for the requested row id
		$dataElement = $this->getRowDataElement($elementId);
		if (is_null($dataElement)) {
			// If the row doesn't exist then
			// return null. It may be that the
			// row has been deleted in the meantime
			// and the client does not yet know about this.
			$nullVar = null;
			return $nullVar;
		}

		// Instantiate a new row
		$row =& $this->_getInitializedRowInstance($request, $elementId, $dataElement);
		return $row;
	}

	/**
	 * Retrieve a single data element from the grid's data
	 * source corresponding to the given row id. If none is
	 * found then return null.
	 * @param $rowId
	 * @return mixed
	 */
	function &getRowDataElement($rowId) {
		$elementIterator =& $this->getData();
		if (is_a($elementIterator, 'DAOResultFactory')) {
			$dataArray =& $elementIterator->toAssociativeArray('id');
		} else {
			$dataArray =& $elementIterator->toArray();
		}
		if (!isset($dataArray[$rowId])) {
			$nullVar = null;
			return $nullVar;
		} else {
			return $dataArray[$rowId];
		}
	}


	//
	// Private helper methods
	//
	/**
	 * Instantiate a new row.
	 * @param $request Request
	 * @param $elementId integer
	 * @param $element mixed
	 * @return GridRow
	 */
	function &_getInitializedRowInstance(&$request, $elementId, &$element) {
		// Instantiate a new row
		$row =& $this->getRowInstance();
		$row->setGridId($this->getId());
		$row->setId($elementId);
		$row->setData($element);

		// Initialize the row before we render it
		$row->initialize($request);
		return $row;
	}

	/**
	 * Method that renders tbodys to go in the grid main body.
	 * @param Request $request
	 * @return array
	 */
	function _renderGridBodyPartsInternally(&$request) {
		$gridBodyParts = array();
		$nullVar = null;
		$renderedRows = $this->_renderRowsInternally($request, $nullVar);
		$templateMgr =& TemplateManager::getManager();
		if ( count($renderedRows) > 0 ) {
			$templateMgr->assign_by_ref('rows', $renderedRows);
			$gridBodyParts[] = $templateMgr->fetch('controllers/grid/gridBodyPart.tpl');
		}
		return $gridBodyParts;
	}

	/**
	 * Cycle through the data and get generate the row HTML.
	 * @param $request PKPRequest
	 * @param $elementIterator ItemIterator
	 * @return array of HTML Strings for Grid Rows.
	 */
	function _renderRowsInternally(&$request, &$elementIterator) {
		// Iterate through the rows and render them according
		// to the row definition.  Uses $rowIterator or gets all the grid data.
		if ( !$elementIterator ) $elementIterator =& $this->_getSortedElements();
		$renderedRows = array();
		while (!$elementIterator->eof()) {
			// If we're not using the default ID field, get the custom one
			$idField = $this->getRowIdentifier() ? $this->getRowIdentifier() : null;

			// Get the element for the row and its key
			list($elementId, $element) = $elementIterator->nextWithKey($idField);

			// Instantiate a new row.
			$row =& $this->_getInitializedRowInstance($request, $elementId, $element);

			// Render the row
			$renderedRows[] = $this->_renderRowInternally($request, $row);
			unset($element);
		}

		return $renderedRows;
	}

	/**
	 * Method that renders a single row.
	 *
	 * NB: You must have initialized the row
	 * before you call this method.
	 *
	 * @param $request PKPRequest
	 * @param $row GridRow
	 * @return string the row HTML
	 */
	function _renderRowInternally(&$request, &$row) {
		// Iterate through the columns and render the
		// cells for the given row.
		$renderedCells = array();
		$columns = $this->getColumns();
		foreach ($columns as $column) {
			assert(is_a($column, 'GridColumn'));
			$renderedCells[] = $this->_renderCellInternally($request, $row, $column);
		}

		// Pass control to the view to render the row
		$templateMgr =& TemplateManager::getManager();
		$templateMgr->assign_by_ref('columns', $columns);
		$templateMgr->assign_by_ref('cells', $renderedCells);
		$templateMgr->assign_by_ref('row', $row);
		return $templateMgr->fetch($row->getTemplate());
	}

	/**
	 * Method that renders a cell.
	 *
	 * NB: You must have initialized the row
	 * before you call this method.
	 *
	 * @param $request PKPRequest
	 * @param $row GridRow
	 * @param $column GridColumn
	 * @return string the cell HTML
	 */
	function _renderCellInternally(&$request, &$row, &$column) {
		// Get the cell content
		$cellProvider =& $column->getCellProvider();
		return $cellProvider->render($request, $row, $column);
	}

	/**
	 * Returns the sorted and filtered data elements
	 * to be displayed.
	 *
	 * @return ItemIterator
	 */
	function &_getSortedElements() {
		// TODO: This method will implement sorting, filtering and
		//  paging strategies.
		return $this->getData();
	}
}
?>

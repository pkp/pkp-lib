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
 * @brief Class defining basic operations for handling HTML grids.
 */

// import the base Handler
import('handler.PKPHandler');

// import grid classes
import('controllers.grid.GridAction');
import('controllers.grid.GridColumn');
import('controllers.grid.GridRow');
import('controllers.grid.GridCategoryRow');

// import JSON class for use with all AJAX requests
import('core.JSON');

// grid specific action positions
define('GRID_ACTION_POSITION_ABOVE', 'above');
define('GRID_ACTION_POSITION_BELOW', 'below');

// empty category constant
define('GRID_CATEGORY_NONE', 'NONE');

class GridHandler extends PKPHandler {
	/** @var string grid title */
	var $_title = '';

	/**
	 * @var array grid actions, the first key represents
	 *  the position of the action in the grid, the second key
	 *  represents the action id.
	 */
	var $_actions = array(GRID_ACTION_POSITION_DEFAULT => array());

	/** @var array the GridColumns of this grid */
	var $_columns = array();

	/** @var ItemIterator the grid's data source */
	var $_data;

	/** @var string the grid template */
	var $_template;

	/** @var ItemIterator the category data source */
	var $_categoryData;

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
	 * Get the grid title
	 * @return string
	 */
	function getTitle() {
		return $this->_title;
	}

	/**
	 * Set the grid title
	 * @param $title string
	 */
	function setTitle($title) {
		$this->_title = $title;
	}


	/**
	 * Get all actions for a given position within the grid
	 * @param $position string the position of the actions
	 * @return array the GridActions for the given position
	 */
	function getActions($position = GRID_ACTION_POSITION_ABOVE) {
		if(!isset($this->_actions[$position])) return array();
		return $this->_actions[$position];
	}

	/**
	 * Add an action
	 * @param $position string the position of the action
	 * @param $action mixed a single action
	 */
	function addAction($action, $position = GRID_ACTION_POSITION_ABOVE) {
		if (!isset($this->_actions[$position])) $this->_actions[$position] = array();
		$this->_actions[$position][$action->getId()] = $action;
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
	 * Get the grid data
	 * @return ItemIterator
	 */
	function &getData() {
		if (is_null($this->_data)) {
			// initialize data to an empty iterator
			import('core.ItemIterator');
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
	 * Set the grid data
	 * @param $data mixed an array or ItemIterator with element data
	 */
	function setData(&$data) {
		if (is_a($data, 'ItemIterator')) {
			$this->_data =& $data;
		} elseif(is_array($data)) {
			import('core.ArrayItemIterator');
			$this->_data = new ArrayItemIterator($data);
		} else {
			assert(false);
		}
	}

	/**
	 * Get the grid data
	 * @return ItemIterator
	 */
	function &getCategoryData() {
		if (is_null($this->_categoryData)) {
			// initialize data to an empty iterator
			import('core.ItemIterator');
			$elementIterator = new ItemIterator();
			$this->setCategoryData($elementIterator);
		}

		// Make a copy of the iterator (iterators
		// "auto-destroy" after one-time use...)
		assert(is_a($this->_categoryData, 'ItemIterator'));
		$elementIterator =& cloneObject($this->_categoryData);
		return $elementIterator;
	}

	/**
	 * Set the grid data
	 * @param $data mixed an array or ItemIterator with element data
	 */
	function setCategoryData(&$categoryData) {
		if (is_a($categoryData, 'ItemIterator')) {
			$this->_categoryData =& $categoryData;
		} elseif(is_array($categoryData)) {
			import('core.ArrayItemIterator');
			$this->_categoryData = new ArrayItemIterator($categoryData);
		} else {
			assert(false);
		}
	}

	/**
	 * Get the grid template
	 * @return string
	 */
	function getTemplate() {
		if (is_null($this->_template)) {
			$this->setTemplate('controllers/grid/grid.tpl');
		}

		return $this->_template;
	}

	/**
	 * Set the grid template
	 * @param $template string
	 */
	function setTemplate($template) {
		$this->_template = $template;
	}

	//
	// Overridden methods from PKPHandler
	//
	/**
	 * @see PKPHandler::getRemoteOperations()
	 */
	function getRemoteOperations() {
		return array('fetchGrid', 'fetchRow', 'fetchCell');
	}

	/**
	 * @see PKPHandler::initialize()
	 * @param $request PKPRequest
	 */
	function initialize(&$request) {
		parent::initialize($request);

		// Load grid-specific translations
		Locale::requireComponents(array(LOCALE_COMPONENT_PKP_GRID));
	}

	//
	// Public handler methods
	//
	/**
	 * Render the entire grid controller and send
	 * it to the client.
	 * @return string the grid HTML
	 */
	function fetchGrid($args, &$request) {

		// Prepare the template to render the grid
		$templateMgr =& TemplateManager::getManager();
		$templateMgr->assign_by_ref('grid', $this);

		// Add columns to the view
		$columns =& $this->getColumns();
		$templateMgr->assign_by_ref('columns', $columns);
		$templateMgr->assign('numColumns', count($columns));

		// Render the body elements (category groupings + rows inside a <tbody>)
		$gridBodyParts = $this->_renderRowsInternally($request);
		$templateMgr->assign_by_ref('gridBodyParts', $gridBodyParts);

		// Let the view render the grid
		return $templateMgr->fetch($this->getTemplate());
	}

	/**
	 * Render a row and send it to the client.
	 * @return string the row HTML
	 */
	function fetchRow(&$args, &$request) {
		// Instantiate the requested row
		$row =& $this->getRequestedRow($request, $args);

		// Render the requested row
		return $this->_renderRowInternally($request, $row);
	}

	/**
	 * Render a cell and send it to the client
	 * @return string the row HTML
	 */
	function fetchCell(&$args, &$request) {
		// Check the requested column
		if(!isset($args['columnId'])) fatalError('Missing column id!');
		if(!$this->hasColumn($args['columnId'])) fatalError('Invalid column id!');
		$column =& $this->getColumn($args['columnId']);

		// Instantiate the requested row
		$row =& $this->getRequestedRow($request, $args);

		// Render the cell
		return $this->_renderCellInternally($request, $row, $column);
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
	 * Get a new instance of a category grid row. May be
	 * overridden by subclasses if they want to
	 * provide a custom row definition.
	 * @return CategoryGridRow
	 */
	function &getCategoryRowInstance() {
		//provide a sensible default category row definition
		$row = new GridCategoryRow();
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
	 *  configured with id and data.
	 */
	function &getRequestedRow($request, $args) {
		// Instantiate a new row
		$row =& $this->getRowInstance();
		$row->setGridId($this->getId());

		// Try to retrieve a row id from $args if it is present
		if(!isset($args['rowId'])) fatalError('Missing row id!');
		$rowId = $args['rowId'];
		$row->setId($rowId);

		// Retrieve row data for the requested row id
		$dataElement = $this->getRowDataElement($rowId);
		if (is_null($dataElement)) fatalError('Invalid row id!');
		$row->setData($dataElement);

		// Initialize the row
		$row->initialize($request);

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
	 * Cycle through the data and get generate the row HTML
	 * @param $request PKPRequest
	 * @return array of HTML Strings for Grid Rows.
	 */
	function _renderRowsInternally(&$request) {
		// Iterate through the rows and render them according
		// to the row definition.

		// now that everything is in its own category grouping
		$gridBodyParts = array();
		$categoryNum = 0;

		$categoryDataIterator =& $this->getCategoryData();

		$templateMgr =& TemplateManager::getManager();
		$rowData =& $this->_getSortedElements();
		// There's no category data loaded.
		// Render all the rows with no category row
		if ( $categoryDataIterator->eof() ) {
			$renderedRows = $this->_renderRowsInternallyFromData($request, $rowData);
			$templateMgr->assign_by_ref('rows', $renderedRows);
			$gridBodyParts[] = $templateMgr->fetch('controllers/grid/gridBodyPart.tpl');
		} else {
			$categoryRowData = array();
			// first, group the data by category in an associative array
			// indexed by the category Id
			while ( !$rowData->eof() ) {
				$element =& $rowData->next();
				$categoryId = $this->getCategoryIdFromElement($element);
				$categoryRowData[$categoryId][] =& $element;
				unset($element);
			}

			import('core.ArrayItemIterator');
			// we have category data.  So loop through and render each grid body part
			// each body part can be made up of rowData plus a category row
			while (!$categoryDataIterator->eof()) {
				// Instantiate a new row
				$categoryRow =& $this->getCategoryRowInstance();
				$categoryRow->setGridId($this->getId());
				// Use the element key as the row id
				list($categoryId, $element) = $categoryDataIterator->nextWithKey();

				$categoryRow->setId($categoryId);
				$categoryRow->setData($element);

				// Initialize the row before we render it
				$categoryRow->initialize($request);

				// if there data for this category, put it in an Iterator
				$renderedRows = array();
				if ( isset($categoryRowData[$categoryId]) ) {
					$rowData =& new ArrayItemIterator($categoryRowData[$categoryId]);
				} else {
					// an empty iterator for categories with now data
					$rowData = new ItemIterator();
				}

				// Render the row
				$gridBodyParts[] = $this->_renderGridBodyPartInternally($request, $rowData, $categoryRow, $categoryNum);

				$categoryNum++;

				unset($element);
			}
		}

		return $gridBodyParts;
	}

	/**
	 * Cycle through an ItemIterator and generate row HTML from the given $rowData
	 * @param PKPRequest $request
	 * @param ItemIterator $rowData
	 * @return array of string (HTML) rows
	 */
	function _renderRowsInternallyFromData(&$request, &$rowData) {
		$renderedRows = array();
		while (!$rowData->eof()) {
			// Instantiate a new row
			$row =& $this->getRowInstance();
			$row->setGridId($this->getId());
			// Use the element key as the row id
			list($key, $element) = $rowData->nextWithKey();

			$row->setId($key);
			$row->setData($element);

			// Initialize the row before we render it
			$row->initialize($request);

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
	 * Optionally render a category row and render its data.  If no category data given, render the rows only
	 * @param PKPRequest $request
	 * @param ItemIterator $rowData
	 * @param GridCategoryRow $categoryRow
	 * @return String HTML for all the rows (including category)
	 */
	function _renderGridBodyPartInternally(&$request, &$rowData, &$categoryRow = null, $categoryNum = 0) {
		$templateMgr =& TemplateManager::getManager();

		// Render the data rows
		// applies for categorized data and non categoriez
		$templateMgr->assign_by_ref('rows', $this->_renderRowsInternallyFromData($request, $rowData));

		// if there's a category row, then use the category template.  else, use the regualr gridBodyPart template
		if ( $categoryRow ) {
			// use to figure out the number of columns
			$columns =& $this->getColumns();

			$templateMgr->assign_by_ref('categoryRow', $categoryRow);
			$templateMgr->assign('numColumns', count($columns));
			$templateMgr->assign('gridCategoryNum', ($categoryNum % 5) + 1);
			return $templateMgr->fetch($categoryRow->getTemplate());
		} else {
			return $templateMgr->fetch('controllers/grid/gridBodyPart.tpl');
		}
	}

	/**
	 * Method that renders a cell
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
		return $cellProvider->render($row, $column);
	}

	/**
	 * Given a category name and a data element, return an id that identifies this category
	 * To be used for sorting data elements into category buckets
	 * @param Data Object $element
	 * @param String $category
	 * return mixed int/string
	 */
	function getCategoryIdFromElement(&$element, $category) {
		// Should be overriden by subclasses
		return GRID_CATEGORY_NONE;
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
<?php

/**
 * @file classes/controllers/grid/GridMainHandler.inc.php
 *
 * Copyright (c) 2000-2009 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class GridMainHandler
 * @ingroup controllers_grid
 *
 * @brief Class defining basic operations for handling HTML grids.
 */

import('controllers.grid.GridHandler');

define('GRID_ACTION_POSITION_ABOVE', 'above');
define('GRID_ACTION_POSITION_BELOW', 'below');

class GridMainHandler extends GridHandler {
	/** @var GridRowHandler the Handler that will take care of building and rendering rows */
	var $_rowHandler;

	/** @var string grid title */
	var $_title = '';

	/**
	 * Constructor.
	 */
	function GridMainHandler() {
		parent::GridHandler();
	}

	//
	// Getters/Setters
	//
	/**
	 * Get the row handler
	 * @return GridRowHandler
	 */
	function &getRowHandler() {
		if (is_null($this->_rowHandler)) {
			//provide a sensible default handler
			import('controllers.grid.GridRowHandler');
			$rowHandler =& new GridRowHandler();
			$this->setRowHandler($rowHandler);
		}
		return $this->_rowHandler;
	}

	/**
	 * Set the row handler
	 * @param $rowHandler GridRowHandler
	 */
	function setRowHandler(&$rowHandler) {
		$this->_rowHandler =& $rowHandler;
	}

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
	 * Get the element data, overrides base class getter
	 * @return ItemIterator
	 */
	function &getData() {
		$elementIterator =& parent::getData();
		assert(is_a($elementIterator, 'ItemIterator'));

		// Make a copy of the iterator (iterators
		// auto-destroy after one-time use...)
		$elementIterator =& cloneObject($elementIterator);
		return $elementIterator;
	}

	/**
	 * Set the element data, overrides base class setter
	 * @param $data mixed an array or ItemIterator with element data
	 */
	function setData(&$data) {
		if (is_array($data)) {
			import('core.ArrayItemIterator');
			$elementIterator = new ArrayItemIterator($data);
		} elseif(is_null($data)) {
			// initialize data to an empty iterator
			import('core.ItemIterator');
			$elementIterator = new ItemIterator();
		} else {
			$elementIterator =& $data;
		}
		assert(is_a($elementIterator, 'ItemIterator'));

		parent::setData($elementIterator);
	}

	/**
	 * Get the controller template - override base
	 * implementation to provide a sensible default.
	 * @return string
	 */
	function getTemplate() {
		if (is_null(parent::getTemplate())) {
			$this->setTemplate('controllers/grid/grid.tpl');
		}

		return parent::getTemplate();
	}

	/**
	 * @see lib/pkp/classes/handler/PKPHandler#getRemoteOperations()
	 */
	function getRemoteOperations() {
		return array('fetchGrid');
	}

	//
	// Public handler methods
	//
	/**
	 * Render the grid controller
	 * @return string the grid HTML
	 */
	function fetchGrid($args, &$request) {
		//FIXME: Add validation here?
		// Let the subclass configure the grid
		$this->initialize($request);

		// Render the rows
		$rows = $this->_renderRowsInternally($request);

		// Pass control to the view to render the grid
		$templateMgr =& TemplateManager::getManager();
		$templateMgr->assign_by_ref('rows', $rows);
		$templateMgr->assign_by_ref('grid', $this);

		$rowHandler =& $this->getRowHandler();
		// initialize to create the columns
		$rowHandler->initialize($request);
		$columns =& $rowHandler->getColumns();
		$templateMgr->assign_by_ref('columns', $columns);
		$templateMgr->assign('numColumns', count($columns));

		return $templateMgr->fetch($this->getTemplate());
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
		$rows = array();

		$elementIterator =& $this->_getSortedElements();
		$rowHandler =& $this->getRowHandler();

		while (!$elementIterator->eof()) {
			list($key, $element) = $elementIterator->nextWithKey();
			// uses the array key as the basis for the $rowId
			$rowHandler->setId($key);
			$rowHandler->setData($element);
			$rowHandler->setGridId($this->getId());
			$rows[] = $rowHandler->renderRowInternally($request);
			unset($element);
		}

		return $rows;
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
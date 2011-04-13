<?php

/**
 * @file classes/controllers/grid/CategoryGridHandler.inc.php
 *
 * Copyright (c) 2000-2011 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class CategoryGridHandler
 * @ingroup controllers_grid
 *
 * @brief Class defining basic operations for handling HTML grids with categories.
 */

// import grid classes
import('lib.pkp.classes.controllers.grid.GridHandler');
import('lib.pkp.classes.controllers.grid.GridCategoryRow');

// empty category constant
define('GRID_CATEGORY_NONE', 'NONE');

class CategoryGridHandler extends GridHandler {
	/**
	 * Constructor.
	 */
	function CategoryGridHandler() {
		parent::GridHandler();
	}


	//
	// Public handler methods
	//
	/**
	 * Render the entire grid controller and send
	 * it to the client.
	 * @param $args array
	 * @param $request PKPRequest
	 * @return string the grid HTML
	 */
	function fetchGrid($args, &$request) {

		// Prepare the template to render the grid
		$templateMgr =& TemplateManager::getManager();
		$templateMgr->assign_by_ref('grid', $this);

		// Add columns to the view
		$columns =& $this->getColumns();
		$templateMgr->assign_by_ref('columns', $columns);

		// Render the body elements (category groupings + rows inside a <tbody>)
		$gridBodyParts = $this->_renderCategoriesInternally($request);
		$templateMgr->assign_by_ref('gridBodyParts', $gridBodyParts);

		// Let the view render the grid
		$json = new JSONMessage(true, $templateMgr->fetch($this->getTemplate()));
		return $json->getString();
	}


	//
	// Protected methods to be overridden/used by subclasses
	//
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


	//
	// Private helper methods
	//
 	/**
 	 * Render all the categories internally
 	 * @param $request PKPRequest
 	 */
	function _renderCategoriesInternally(&$request) {
		// Iterate through the rows and render them according
		// to the row definition.
		$renderedCategories = array();
		$iterator = 1;
		$elements = $this->getGridDataElements($request);
		foreach($elements as $key => $element) {

			// Instantiate a new row
			$categoryRow =& $this->getCategoryRowInstance();
			$categoryRow->setGridId($this->getId());

			// Use the element key as the row id
			$categoryRow->setId($key);
			$categoryRow->setData($element);

			// Initialize the row before we render it
			$categoryRow->initialize($request);

			// Render the row
			$renderedCategories[] = $this->_renderCategoryInternally($request, $categoryRow, $iterator);
			unset($element);
			$iterator = $iterator < 5 ? $iterator+1 : $iterator = 1;
		}

		return $renderedCategories;
	}

	/**
	 * Optionally render a category row and render its data.  If no category data given, render the rows only
	 * @param $request PKPRequest
	 * @param $categoryRow GridCategoryRow
	 * @param $iterator int optional
	 * @return String HTML for all the rows (including category)
	 */
	function _renderCategoryInternally(&$request, &$categoryRow, $iterator = null) {
		$templateMgr =& TemplateManager::getManager();

		$categoryDataElement =& $categoryRow->getData();
		$rowData =& $this->getCategoryData($categoryDataElement);

		// Render the data rows
		$renderedRows = $this->_renderRowsInternally($request, $rowData);
		$templateMgr->assign_by_ref('rows', $renderedRows);

		$columns =& $this->getColumns();
		$templateMgr->assign('iterator', $iterator);
		$templateMgr->assign_by_ref('categoryRow', $categoryRow);
		$renderedCategoryRow = $templateMgr->fetch($categoryRow->getTemplate());

		$templateMgr->assign_by_ref('renderedCategoryRow', $renderedCategoryRow);
		return $templateMgr->fetch('controllers/grid/gridBodyPartWithCategory.tpl');
	}

	/**
	 * Given a category name and a data element, return an id that identifies this category
	 * To be used for sorting data elements into category buckets
	 * @param $element DataObject
	 * @param $category String
	 * return mixed int/string
	 */
	function getCategoryIdFromElement(&$element, $category) {
		// Should be overriden by subclasses
		return GRID_CATEGORY_NONE;
	}
}

?>

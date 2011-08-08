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

		// Add rendered filter
		$renderedFilter = $this->renderFilter($request);
		$templateMgr->assign('gridFilterForm', $renderedFilter);

		// Add columns to the view
		$columns =& $this->getColumns();
		$templateMgr->assign_by_ref('columns', $columns);

		// Render the body elements (category groupings + rows inside a <tbody>)
		$gridBodyParts = $this->_renderCategoriesInternally($request);
		$templateMgr->assign_by_ref('gridBodyParts', $gridBodyParts);

		// Assign additional params for the fetchRow and fetchGrid URLs to use.
		$templateMgr->assign('gridRequestArgs', $this->getRequestArgs());

		// Let the view render the grid
		$json = new JSONMessage(true, $templateMgr->fetch($this->getTemplate()));
		return $json->getString();
	}

	/**
	 * Override GridHandler::fetchRow.
	 * Instead of rendering a row, render a category with all the rows inside of it.
	 * @param $args array
	 * @param $request Request
	 * @return string the serialized row JSON message or a flag
	 *  that indicates that the row has not been found.
	 */
	function fetchRow(&$args, &$request) {
		// Instantiate the requested row (includes a
		// validity check on the row id).
		$row =& $this->getRequestedCategoryRow($request, $args);

		$json = new JSONMessage(true);
		if (is_null($row)) {
			// Inform the client that the row does no longer exist.
			$json->setAdditionalAttributes(array('rowNotFound' => (int)$args['rowId']));
		} else {
			// Render the requested category
			$json->setContent($this->_renderCategoryInternally($request, $row));
		}

		// Render and return the JSON message.
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
	function &getRequestedCategoryRow($request, $args) {
		if (isset($args['rowId'])) {
			// A row ID was specified. Fetch it
			$elementId = $args['rowId'];

			// Retrieve row data for the requested row id
			// (we can use the default getRowData element, works for category grids as well).
			$dataElement = $this->getRowDataElement($request, $elementId);
			if (is_null($dataElement)) {
				// If the row doesn't exist then
				// return null. It may be that the
				// row has been deleted in the meantime
				// and the client does not yet know about this.
				$nullVar = null;
				return $nullVar;
			}
		}

		// Instantiate a new row
		$row =& $this->_getInitializedCategoryRowInstance($request, $elementId, $dataElement);
		return $row;
	}

	//
	// Private helper methods
	//
	/**
	 * Instantiate a new row.
	 * @param $request Request
	 * @param $elementId string
	 * @param $element mixed
	 * @param $isModified boolean optional
	 * @return GridRow
	 */
	function &_getInitializedCategoryRowInstance(&$request, $elementId, &$element) {
		// Instantiate a new row
		$row =& $this->getCategoryRowInstance();
		$row->setGridId($this->getId());
		$row->setId($elementId);
		$row->setData($element);
		$row->setRequestArgs($this->getRequestArgs());

		// Initialize the row before we render it
		$row->initialize($request);
		return $row;
	}

	/**
	 * Render all the categories internally
	 * @param $request PKPRequest
	 */
	function _renderCategoriesInternally(&$request) {
		// Iterate through the rows and render them according
		// to the row definition.
		$renderedCategories = array();

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
			$renderedCategories[] = $this->_renderCategoryInternally($request, $categoryRow);
			unset($element);
		}

		return $renderedCategories;
	}

	/**
	 * Optionally render a category row and render its data.  If no category data given, render the rows only
	 * @param $request PKPRequest
	 * @param $categoryRow GridCategoryRow
	 * @return String HTML for all the rows (including category)
	 */
	function _renderCategoryInternally(&$request, &$categoryRow) {
		$templateMgr =& TemplateManager::getManager();

		$categoryDataElement =& $categoryRow->getData();
		$rowData =& $this->getCategoryData($categoryDataElement);

		// Render the data rows
		$renderedRows = $this->_renderRowsInternally($request, $rowData);
		$templateMgr->assign_by_ref('rows', $renderedRows);

		$templateMgr->assign_by_ref('categoryRow', $categoryRow);
		$renderedCategoryRow = $templateMgr->fetch($categoryRow->getTemplate());

		$templateMgr->assign_by_ref('renderedCategoryRow', $renderedCategoryRow);
		return $templateMgr->fetch('controllers/grid/gridBodyPartWithCategory.tpl');
	}

	/**
	 * FIXME: is this dead code? Leaving in for now, since we do not have many examples of category grids yet.
	 *
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

	/**
	 * Fetch the contents of a category.
	 * @param $categoryDataElement mixed
	 * @return array
	 */
	function getCategoryData(&$categoryDataElement) {
		// Should be overridden by subclasses
		assert(false);
	}
}

?>

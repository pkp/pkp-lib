<?php

/**
 * @file classes/controllers/grid/CategoryGridHandler.inc.php
 *
 * Copyright (c) 2000-2012 John Willinsky
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

	/** @var string empty category row locale key */
	var $_emptyCategoryRowText = 'grid.noItems';

	/**
	 * Constructor.
	 */
	function CategoryGridHandler($dataProvider = null) {
		parent::GridHandler($dataProvider);

		import('lib.pkp.classes.controllers.grid.NullGridCellProvider');
		$this->addColumn(new GridColumn('indent', null, null, null, new NullGridCellProvider(), array('indent' => true)));
	}


	//
	// Getters and setters.
	//
	/**
	 * Get the empty rows text for a category.
	 * @return string
	 */
	function getEmptyCategoryRowText() {
		return $this->_emptyCategoryRowText;
	}

	/**
	 * Set the empty rows text for a category.
	 * @param string $translationKey
	 */
	function setEmptyCategoryRowText($translationKey) {
		$this->_emptyCategoryRowText = $translationKey;
	}


	//
	// Public handler methods
	//
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
			$this->setRowActionToggleColumn();
			$json->setContent($this->_renderCategoryInternally($request, $row));
		}

		// Render and return the JSON message.
		return $json->getString();
	}


	//
	// Extended methods from GridHandler
	//
	/**
	 * @see GridHandler::getJSHandler()
	 */
	function getJSHandler() {
		return '$.pkp.controllers.grid.CategoryGridHandler';
	}

	/**
	 * @see GridHandler::doSpecificFetchGridActions($args, $request)
	 */
	function doSpecificFetchGridActions($args, $request, &$templateMgr) {
		// Render the body elements (category groupings + rows inside a <tbody>)
		$gridBodyParts = $this->_renderCategoriesInternally($request);
		$templateMgr->assign_by_ref('gridBodyParts', $gridBodyParts);
	}

	/**
	 * @see GridHandler::setRowActionToggleColumn()
	 */
	function setRowActionToggleColumn() {
		$columns =& $this->getColumns();
		reset($columns);
		// Category grids will always have indent column firstly,
		// so we need to add the row action toggle inside
		// the seconde one.
		$secondColumn =& next($columns); /* @var $secondColumn GridColumn */
		$secondColumn->addFlag('hasRowActionsToggle', true);
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
	 * Fetch the contents of a category.
	 * @param $categoryDataElement mixed
	 * @return array
	 */
	function &getCategoryData(&$categoryDataElement, $filter = null) {
		$gridData = array();
		$dataProvider =& $this->getDataProvider();
		if (is_a($dataProvider, 'CategoryGridDataProvider')) {
			// Populate the grid with data from the
			// data provider.
			$gridData =& $dataProvider->getCategoryData($categoryDataElement, $filter);
		}
		return $gridData;
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

	/**
	 * Get the category data element sequence value.
	 * @param $gridDataElement mixed
	 * @return int
	 */
	function getCategoryDataElementSequence(&$gridDataElement) {
		assert(false);
	}

	/**
	 * Operation to save the category data element new sequence.
	 * @param $gridDataElement mixed
	 * @param $newSequence int
	 */
	function saveCategoryDataElementSequence(&$gridDataElement, $newSequence) {
		assert(false);
	}

	/**
	 * @see GridHandler::saveRowDataElementSequence()
	 */
	function saveRowDataElementSequence($gridDataElement, $categoryId, $newSequence) {
		assert(false);
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
		$this->callFeaturesHook('getInitializedCategoryRowInstance',
			array('request' => &$request,
				'grid' => &$this,
				'row' => &$row));
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
			$categoryRow =& $this->_getInitializedCategoryRowInstance($request, $key, $element);

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
		$filter = $this->getFilterSelectionData($request);
		$rowData =& $this->getCategoryData($categoryDataElement, $filter);

		// Render the data rows
		$templateMgr->assign_by_ref('categoryRow', $categoryRow);
		$renderedRows = $this->_renderRowsInternally($request, $rowData);
		$templateMgr->assign_by_ref('rows', $renderedRows);

		$templateMgr->assign_by_ref('categoryRow', $categoryRow);
		$renderedCategoryRow = $templateMgr->fetch($categoryRow->getTemplate());

		$templateMgr->assign_by_ref('renderedCategoryRow', $renderedCategoryRow);
		return $templateMgr->fetch('controllers/grid/gridBodyPartWithCategory.tpl');
	}
}

?>

<?php

/**
 * @file classes/controllers/grid/CategoryGridHandler.inc.php
 *
 * Copyright (c) 2014 Simon Fraser University Library
 * Copyright (c) 2000-2014 John Willinsky
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

	/** @var string The category id that this grid is currently rendering. */
	var $_currentCategoryId = null;

	/**
	 * Constructor.
	 */
	function CategoryGridHandler($dataProvider = null) {
		parent::GridHandler($dataProvider);

		import('lib.pkp.classes.controllers.grid.NullGridCellProvider');
		$this->addColumn(new GridColumn('indent', null, null, null,
			new NullGridCellProvider(), array('indent' => true)));
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

	/**
	 * Check whether the passed category has grid rows.
	 * @param $categoryDataElement mixed The category data element
	 * that will be checked.
	 * @param $request PKPRequest
	 * @return boolean
	 */
	function hasGridDataElementsInCategory($categoryDataElement, $request) {
		$filter = $this->getFilterSelectionData($request);
		$data =& $this->getCategoryData($categoryDataElement, $filter);
		assert (is_array($data));
		return (boolean) count($data);
	}

	/**
	 * Get the category id that this grid is currently rendering.
	 * @param int
	 */
	function getCurrentCategoryId() {
		return $this->_currentCategoryId;
	}

	/**
	 * Override to return the data element sequence value
	 * inside the passed category, if needed.
	 * @param $categoryId int The data element category id.
	 * @param $gridDataElement mixed The element to return the
	 * sequence.
	 * @return int
	 */
	function getDataElementInCategorySequence($categoryId, &$gridDataElement) {
		assert(false);
	}

	/**
	 * Override to set the data element new sequence inside
	 * the passed category, if needed.
	 * @param $categoryId int The data element category id.
	 * @param $gridDataElement mixed The element to set the
	 * new sequence.
	 * @param $newSequence int The new sequence value.
	 */
	function setDataElementInCategorySequence($categoryId, &$gridDataElement, $newSequence) {
		assert(false);
	}

	/**
	 * Override to define whether the data element inside the passed
	 * category is selected or not.
	 * @param $categoryId int
	 * @param $gridDataElement mixed
	 */
	function isDataElementInCategorySelected($categoryId, &$gridDataElement) {
		assert(false);
	}


	//
	// Public handler methods
	//
	/**
	 * Render a category with all the rows inside of it.
	 * @param $args array
	 * @param $request Request
	 * @return string the serialized row JSON message or a flag
	 *  that indicates that the row has not been found.
	 */
	function fetchCategory(&$args, $request) {
		// Instantiate the requested row (includes a
		// validity check on the row id).
		$row = $this->getRequestedCategoryRow($request, $args);

		$json = new JSONMessage(true);
		if (is_null($row)) {
			// Inform the client that the category does no longer exist.
			$json->setAdditionalAttributes(array('elementNotFound' => (int)$args['rowId']));
		} else {
			// Render the requested category
			$this->setFirstDataColumn();
			$json->setContent($this->_renderCategoryInternally($request, $row));
		}

		// Render and return the JSON message.
		return $json->getString();
	}


	//
	// Extended methods from GridHandler
	//
	function initialize($request) {
		parent::initialize($request);

		if (!is_null($request->getUserVar('rowCategoryId'))) {
			$this->_currentCategoryId = (string) $request->getUserVar('rowCategoryId');
		}
	}

	/**
	 * @see GridHandler::getRequestArgs()
	 */
	function getRequestArgs() {
		$args = parent::getRequestArgs();

		// If grid is rendering grid rows inside category,
		// add current category id value so rows will also know
		// their parent category.
		if (!is_null($this->_currentCategoryId)) {
			if ($this->getCategoryRowIdParameterName()) {
				$args[$this->getCategoryRowIdParameterName()] = $this->_currentCategoryId;
			}
		}

		return $args;
	}


	/**
	 * @see GridHandler::getJSHandler()
	 */
	public function getJSHandler() {
		return '$.pkp.controllers.grid.CategoryGridHandler';
	}

	/**
	 * @see GridHandler::setUrls()
	 */
	function setUrls($request) {
		$router = $request->getRouter();
		$url = array('fetchCategoryUrl' => $router->url($request, null, null, 'fetchCategory', null, $this->getRequestArgs()));
		parent::setUrls($request, $url);
	}

	/**
	 * @see GridHandler::getRowsSequence()
	 */
	protected function getRowsSequence($request) {
		$filter = $this->getFilterSelectionData($request);
		return array_keys($this->getCategoryData($this->getCurrentCategoryId(), $filter));
	}

	/**
	 * @see GridHandler::doSpecificFetchGridActions($args, $request)
	 */
	protected function doSpecificFetchGridActions($args, $request, &$templateMgr) {
		// Render the body elements (category groupings + rows inside a <tbody>)
		$gridBodyParts = $this->_renderCategoriesInternally($request);
		$templateMgr->assign('gridBodyParts', $gridBodyParts);
	}

	/**
	 * @see GridHandler::getRowDataElement()
	 */
	protected function getRowDataElement($request, $rowId) {
		$rowData = parent::getRowDataElement($request, $rowId);
		$rowCategoryId = $request->getUserVar('rowCategoryId');

		if (is_null($rowData) && !is_null($rowCategoryId)) {
			// Try to get row data inside category.
			$categoryRowData = parent::getRowDataElement($request, $rowCategoryId);
			if (!is_null($categoryRowData)) {
				$categoryElements = $this->getCategoryData($categoryRowData, null);

				assert(is_array($categoryElements));
				if (!isset($categoryElements[$rowId])) return null;

				// Let grid (and also rows) knowing the current category id.
				// This value will be published by the getRequestArgs method.
				$this->_currentCategoryId = $rowCategoryId;

				return $categoryElements[$rowId];
			}
		} else {
			return $rowData;
		}
	}

	/**
	 * @see GridHandler::setFirstDataColumn()
	 */
	protected function setFirstDataColumn() {
		$columns =& $this->getColumns();
		reset($columns);
		// Category grids will always have indent column firstly,
		// so we need to consider the first column the second one.
		$secondColumn = next($columns); /* @var $secondColumn GridColumn */
		$secondColumn->addFlag('firstColumn', true);
	}

	/**
	 * @see GridHandler::renderRowInternally()
	 */
	protected function renderRowInternally($request, $row) {
		if ($this->getCategoryRowIdParameterName()) {
			$param = $this->getRequestArg($this->getCategoryRowIdParameterName());
			$templateMgr = TemplateManager::getManager($request);
			$templateMgr->assign('categoryId', $param);
		}

		return parent::renderRowInternally($request, $row);
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
	protected function getRequestedCategoryRow($request, $args) {
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
		return $this->_getInitializedCategoryRowInstance($request, $elementId, $dataElement);
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
	protected function getCategoryRowInstance() {
		//provide a sensible default category row definition
		return new GridCategoryRow();
	}

	/**
	 * Get the category row id parameter name.
	 * @return string
	 */
	protected function getCategoryRowIdParameterName() {
		// Must be implemented by subclasses.
		return null;
	}

	/**
	 * Fetch the contents of a category.
	 * @param $categoryDataElement mixed
	 * @return array
	 */
	protected function &getCategoryData(&$categoryDataElement, $filter = null) {
		$gridData = array();
		$dataProvider = $this->getDataProvider();
		if (is_a($dataProvider, 'CategoryGridDataProvider')) {
			// Populate the grid with data from the
			// data provider.
			$gridData =& $dataProvider->getCategoryData($categoryDataElement, $filter);
		}
		return $gridData;
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
	private function _getInitializedCategoryRowInstance($request, $elementId, $element) {
		// Instantiate a new row
		$row = $this->getCategoryRowInstance();
		$row->setGridId($this->getId());
		$row->setId($elementId);
		$row->setData($element);
		$row->setRequestArgs($this->getRequestArgs());

		// Initialize the row before we render it
		$row->initialize($request);
		$this->callFeaturesHook('getInitializedCategoryRowInstance',
			array('request' => $request,
				'grid' => $this,
				'categoryId' => $this->_currentCategoryId,
				'row' => $row));
		return $row;
	}

	/**
	 * Render all the categories internally
	 * @param $request PKPRequest
	 */
	private function _renderCategoriesInternally($request) {
		// Iterate through the rows and render them according
		// to the row definition.
		$renderedCategories = array();

		$elements = $this->getGridDataElements($request);
		foreach($elements as $key => $element) {

			// Instantiate a new row
			$categoryRow = $this->_getInitializedCategoryRowInstance($request, $key, $element);

			// Render the row
			$renderedCategories[] = $this->_renderCategoryInternally($request, $categoryRow);
		}

		return $renderedCategories;
	}

	/**
	 * Render a category row and its data.
	 * @param $request PKPRequest
	 * @param $categoryRow GridCategoryRow
	 * @return String HTML for all the rows (including category)
	 */
	private function _renderCategoryInternally($request, $categoryRow) {
		// Prepare the template to render the category.
		$templateMgr = TemplateManager::getManager($request);
		$templateMgr->assign('grid', $this);
		$columns = $this->getColumns();
		$templateMgr->assign('columns', $columns);

		$categoryDataElement = $categoryRow->getData();
		$filter = $this->getFilterSelectionData($request);
		$rowData = $this->getCategoryData($categoryDataElement, $filter);

		// Render the data rows
		$templateMgr->assign('categoryRow', $categoryRow);

		// Let grid (and also rows) knowing the current category id.
		// This value will be published by the getRequestArgs method.
		$this->_currentCategoryId = $categoryRow->getId();

		$renderedRows = $this->renderRowsInternally($request, $rowData);
		$templateMgr->assign('rows', $renderedRows);

		$renderedCategoryRow = $this->renderRowInternally($request, $categoryRow);

		// Finished working with this category, erase the current id value.
		$this->_currentCategoryId = null;

		$templateMgr->assign('renderedCategoryRow', $renderedCategoryRow);
		return $templateMgr->fetch('controllers/grid/gridBodyPartWithCategory.tpl');
	}
}

?>

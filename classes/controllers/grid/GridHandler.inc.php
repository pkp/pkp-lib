<?php

/**
 * @file classes/controllers/grid/GridHandler.inc.php
 *
 * Copyright (c) 2013-2014 Simon Fraser University Library
 * Copyright (c) 2000-2014 John Willinsky
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
import('lib.pkp.classes.core.JSONMessage');

// Grid specific action positions.
define('GRID_ACTION_POSITION_DEFAULT', 'default');
define('GRID_ACTION_POSITION_ABOVE', 'above');
define('GRID_ACTION_POSITION_LASTCOL', 'lastcol');
define('GRID_ACTION_POSITION_BELOW', 'below');

class GridHandler extends PKPHandler {

	/** @var string grid title locale key */
	var $_title = '';

	/** @var string empty row locale key */
	var $_emptyRowText = 'grid.noItems';

	/** @var GridDataProvider */
	var $_dataProvider;

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

	/** @var array The urls that will be used in JS handler. */
	var $_urls;

	/** @var array The grid features. */
	var $_features;


	/**
	 * Constructor.
	 * @param $dataProvider GridDataProvider An optional data provider
	 *  for the grid. If no data provider is given then the grid
	 *  assumes that child classes will override default method
	 *  implementations.
	 */
	function GridHandler($dataProvider = null) {
		$this->_dataProvider =& $dataProvider;
		parent::PKPHandler();
	}


	//
	// Getters and Setters
	//
	/**
	 * Get the data provider.
	 * @return FilesGridDataProvider
	 */
	function &getDataProvider() {
		return $this->_dataProvider;
	}

	/**
	 * Get the grid request parameters. These
	 * are the parameters that uniquely identify the
	 * data within a grid.
	 *
	 * NB: You should make sure to authorize and/or
	 * validate parameters before you publish them
	 * through this interface. Callers will assume that
	 * data accessed through this method will not have
	 * to be sanitized.
	 *
	 * The default implementation tries to retrieve
	 * request parameters from a data provider if there
	 * is one.
	 *
	 * @return array
	 */
	function getRequestArgs() {
		$dataProvider =& $this->getDataProvider();
		$requestArgs = array();
		if (is_a($dataProvider, 'GridDataProvider')) {
			$requestArgs = $dataProvider->getRequestArgs();
		}
		return $requestArgs;
	}

	/**
	 * Get a single grid request parameter.
	 * @see getRequestArgs()
	 *
	 * @param $key string The name of the parameter to retrieve.
	 * @return mixed
	 */
	function getRequestArg($key) {
		$requestArgs = $this->getRequestArgs();
		assert(isset($requestArgs[$key]));
		return $requestArgs[$key];
	}

	/**
	 * Get the grid title.
	 * @return string locale key
	 */
	function getTitle() {
		return $this->_title;
	}

	/**
	 * Set the grid title.
	 * @param $title string locale key
	 */
	function setTitle($title) {
		$this->_title = $title;
	}

	/**
	 * Get the no items locale key
	 */
	function getEmptyRowText() {
		return $this->_emptyRowText;
	}

	/**
	 * Set the no items locale key
	 */
	function setEmptyRowText($emptyRowText) {
		$this->_emptyRowText = $emptyRowText;
	}

	/**
	 * Get the grid instructions.
	 * @return string locale key
	 */
	function getInstructions() {
		return $this->_instructions;
	}

	/**
	 * Set the grid instructions.
	 * @param $instructions string locale key
	 */
	function setInstructions($instructions) {
		$this->_instructions = $instructions;
	}

	/**
	 * Get the grid foot note.
	 * @return string locale key
	 */
	function getFootNote() {
		return $this->_footNote;
	}

	/**
	 * Set the grid foot note.
	 * @param $footNote string locale key
	 */
	function setFootNote($footNote) {
		$this->_footNote = $footNote;
	}

	/**
	 * Get all actions for a given position within the grid.
	 * @param $position string The position of the actions.
	 * @return array The LinkActions for the given position.
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
	 * Get columns by flag.
	 * @param $flag string
	 * @return array
	 */
	function &getColumnsByFlag($flag) {
		$columns = array();
		foreach ($this->getColumns() as $column) {
			if ($column->hasFlag($flag)) {
				$columns[$column->getId()] = $column;
			}
		}

		return $columns;
	}

	/**
	 * Get columns number. If a flag is passed, the columns
	 * using it will not be counted.
	 * @param $flag string
	 * @return int
	 */
	function getColumnsCount($flag) {
		$count = 0;
		foreach ($this->getColumns() as $column) {
			if (!$column->hasFlag($flag)) {
				$count++;
			}
		}

		return $count;
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
	 * @param $request PKPRequest
	 * @return array
	 */
	function &getGridDataElements($request) {
		// Try to load data if it has not yet been loaded.
		if (is_null($this->_data)) {
			$filter = $this->getFilterSelectionData($request);
			$data = $this->loadData($request, $filter);

			if (is_null($data)) {
				// Initialize data to an empty array.
				$data = array();
			}

			$this->setGridDataElements($data);
		}

		return $this->_data;
	}

	/**
	 * Check whether the grid has rows.
	 * @return boolean
	 */
	function hasGridDataElements($request) {
		$data =& $this->getGridDataElements($request);
		assert (is_array($data));
		return (boolean) count($data);
	}

	/**
	 * Set the grid data.
	 * @param $data mixed an array or ItemIterator with element data
	 */
	function setGridDataElements(&$data) {
		// FIXME: We go to arrays for all types of iterators because
		// iterators cannot be re-used, see #6498.
		if (is_array($data)) {
			$this->_data =& $data;
		} elseif(is_a($data, 'DAOResultFactory')) {
			$this->_data = $data->toAssociativeArray();
		} elseif(is_a($data, 'ItemIterator')) {
			$this->_data = $data->toArray();
		} else {
			assert(false);
		}
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
	 * Return all grid urls that will be used
	 * in JS handler.
	 * @return array
	 */
	function getUrls() {
		return $this->_urls;
	}

	/**
	 * Define the urls that will be used
	 * in JS handler.
	 * @param $request Request
	 * @param $extraUrls array Optional extra urls.
	 */
	function setUrls(&$request, $extraUrls = array()) {
		$router =& $request->getRouter();
		$urls = array(
			'fetchGridUrl' => $router->url($request, null, null, 'fetchGrid', null, $this->getRequestArgs()),
			'fetchRowUrl' => $router->url($request, null, null, 'fetchRow', null, $this->getRequestArgs())
		);
		$this->_urls = array_merge($urls, $extraUrls);
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
	 * Get all grid attached features.
	 * @return array
	 */
	function getFeatures() {
		return $this->_features;
	}

	/**
	 * Get "publish data changed" event list.
	 * @return array
	 */
	function getPublishChangeEvents() {
		return array();
	}

	//
	// Overridden methods from PKPHandler
	//
	/**
	 * @see PKPHandler::authorize()
	 */
	function authorize(&$request, &$args, $roleAssignments) {
		$dataProvider =& $this->getDataProvider();
		$hasDataProvider = is_a($dataProvider, 'GridDataProvider');
		if ($hasDataProvider) {
			$this->addPolicy($dataProvider->getAuthorizationPolicy($request, $args, $roleAssignments));
		}

		$success = parent::authorize($request, $args, $roleAssignments);

		if ($hasDataProvider && $success === true) {
			$dataProvider->setAuthorizedContext($this->getAuthorizedContext());
		}

		return $success;
	}

	/**
	 * @see PKPHandler::initialize()
	 */
	function initialize(&$request, $args = null) {
		parent::initialize($request, $args);

		// Load grid-specific translations
		AppLocale::requireComponents(LOCALE_COMPONENT_PKP_GRID, LOCALE_COMPONENT_APPLICATION_COMMON);

		// Give a chance to grid add features before calling hooks.
		// Because we must control when features are added to a grid,
		// this is the only place that should use the _addFeature() method.
		$this->_addFeatures($this->initFeatures($request, $args));
		$this->callFeaturesHook('gridInitialize', array('grid' => &$this));
	}


	//
	// Public handler methods
	//
	/**
	 * Render the entire grid controller and send
	 * it to the client.
	 * @param $args array
	 * @param $request Request
	 * @return string the serialized grid JSON message
	 */
	function fetchGrid($args, &$request) {
		$this->setUrls($request);

		// Prepare the template to render the grid.
		$templateMgr =& TemplateManager::getManager();
		$templateMgr->assign_by_ref('grid', $this);

		// Add rendered filter
		$renderedFilter = $this->renderFilter($request);
		$templateMgr->assign('gridFilterForm', $renderedFilter);

		// Add columns.
		$this->setFirstDataColumn();
		$columns =& $this->getColumns();
		$templateMgr->assign_by_ref('columns', $columns);

		// Do specific actions to fetch this grid.
		$this->doSpecificFetchGridActions($args, $request, $templateMgr);

		// Assign additional params for the fetchRow and fetchGrid URLs to use.
		$templateMgr->assign('gridRequestArgs', $this->getRequestArgs());

		$this->callFeaturesHook('fetchGrid', array('grid' => &$this, 'request' => &$request));

		// Assign features.
		$templateMgr->assign_by_ref('features', $this->getFeatures());

		// Let the view render the grid.
		$json = new JSONMessage(true, $templateMgr->fetch($this->getTemplate()));
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

		$json = new JSONMessage(true);
		if (is_null($row)) {
			// Inform the client that the row does no longer exist.
			$json->setAdditionalAttributes(array('elementNotFound' => (int)$args['rowId']));
		} else {
			// Render the requested row
			$this->setFirstDataColumn();
			$json->setContent($this->renderRowInternally($request, $row));
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
		$this->setFirstDataColumn();
		$column =& $this->getColumn($args['columnId']);

		// Instantiate the requested row
		$row =& $this->getRequestedRow($request, $args);
		if (is_null($row)) fatalError('Row not found!');

		// Render the cell
		$json = new JSONMessage(true, $this->_renderCellInternally($request, $row, $column));
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
	 * Get the js handler for this component.
	 * @return string
	 */
	function getJSHandler() {
		return '$.pkp.controllers.grid.GridHandler';
	}

	/**
	 * Create a data element from a request. This is used to format
	 * new rows prior to their insertion or existing rows that have
	 * been edited but not saved.
	 * @param $request PKPRequest
	 * @param $elementId int Reference to be filled with element
	 *  ID (if one is to be used)
	 * @return object
	 */
	function &getDataElementFromRequest(&$request, &$elementId) {
		fatalError('Grid does not support data element creation!');
	}

	/**
	 * FIXME: temporary shadow method of parent to disable paging on all grids.
	 * @see PKPHandler::getRangeInfo()
	 */
	function getRangeInfo($rangeName, $contextData = null) {
		import('lib.pkp.classes.db.DBResultRange');
		$returner = new DBResultRange(-1, -1);
		return $returner;
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
		$isModified = isset($args['modify']);
		if (isset($args['rowId']) && !$isModified) {
			// A row ID was specified. Fetch it
			$elementId = $args['rowId'];

			// Retrieve row data for the requested row id
			$dataElement = $this->getRowDataElement($request, $elementId);
			if (is_null($dataElement)) {
				// If the row doesn't exist then
				// return null. It may be that the
				// row has been deleted in the meantime
				// and the client does not yet know about this.
				$nullVar = null;
				return $nullVar;
			}
		} elseif ( $isModified ) {
			// The row is modified. The client may be asking
			// for a formatted new entry, to be saved later, or
			// for a representation of a modified row.
			$dataElement = $this->getRowDataElement($request, null);
			if ( isset($args['rowId']) ) {
				// the rowId holds the elementId being modified
				$elementId = $args['rowId'];
			} else {
				// no rowId means that there is no element being modified.
				$elementId = null;
			}
		}

		// Instantiate a new row
		$row =& $this->_getInitializedRowInstance($request, $elementId, $dataElement, $isModified);
		return $row;
	}

	/**
	 * Retrieve a single data element from the grid's data
	 * source corresponding to the given row id. If none is
	 * found then return null.
	 * @param $rowId
	 * @return mixed
	 */
	function getRowDataElement($request, $rowId) {
		$elements =& $this->getGridDataElements($request);

		assert(is_array($elements));
		if (!isset($elements[$rowId])) return null;

		return $elements[$rowId];
	}

	/**
	 * Implement this method to load data into the grid.
	 * @param $request Request
	 * @param $filter array An associative array with filter data as returned by
	 *  getFilterSelectionData(). If no filter has been selected by the user
	 *  then the array will be empty.
	 * @return null
	 */
	function &loadData(&$request, $filter) {
		$gridData = null;
		$dataProvider =& $this->getDataProvider();
		if (is_a($dataProvider, 'GridDataProvider')) {
			// Populate the grid with data from the
			// data provider.
			$gridData =& $dataProvider->loadData();
		}
		return $gridData;
	}

	/**
	 * Returns a Form object or the path name of a filter template.
	 * @return Form|string
	 */
	function getFilterForm() {
		return null;
	}

	/**
	 * Method that extracts the user's filter selection from the request either
	 * by instantiating the filter's Form object or by reading the request directly
	 * (if using a simple filter template only).
	 * @param $request PKPRequest
	 * @return array
	 */
	function getFilterSelectionData($request) {
		return null;
	}

	/**
	 * Render the filter (a template or a Form).
	 * @param $request PKPRequest
	 * @param $filterData Array Data to be used by the filter template.
	 * @return string
	 */
	function renderFilter($request, $filterData = array()) {
		$form = $this->getFilterForm();
		assert(is_null($form) || is_a($form, 'Form') || is_string($form));

		$renderedForm = '';
		switch(true) {
			case is_a($form, 'Form'):
				// Only read form data if the clientSubmit flag has been checked
				$clientSubmit = (boolean) $request->getUserVar('clientSubmit');
				if($clientSubmit) {
					$form->readInputData();
					$form->validate();
				}

				$form->initData($filterData, $request);
				$renderedForm = $form->fetch($request);
				break;
			case is_string($form):
				$templateMgr =& TemplateManager::getManager();

				// Assign data to the filter.
				$templateMgr->assign('filterData', $filterData);

				// Assign current selected filter data.
				$filterSelectionData = $this->getFilterSelectionData($request);
				$templateMgr->assign('filterSelectionData', $filterSelectionData);

				$renderedForm = $templateMgr->fetch($form);
				break;
		}

		return $renderedForm;
	}

	/**
	 * Returns a common 'no matches' result when subclasses find no results for
	 * AJAX autocomplete requests.
	 * @return string Serialized JSON object
	 */
	function noAutocompleteResults() {
		$returner = array();
		$returner[] = array('label' => __('common.noMatches'), 'value' => '');

		$json = new JSONMessage(true, $returner);
		return $json->getString();
	}

	/**
	 * Save all data elements new sequence.
	 * @param $args array
	 * @param $request Request
	 */
	function saveSequence($args, &$request) {
		$this->callFeaturesHook('saveSequence', array('request' => &$request, 'grid' => &$this));

		return DAO::getDataChangedEvent();
	}

	/**
	 * Get the row data element sequence value.
	 * @param $gridDataElement mixed
	 * @return int
	 */
	function getRowDataElementSequence(&$gridDataElement) {
		assert(false);
	}

	/**
	 * Operation to save the row data element new sequence.
	 * @param $gridDataElement mixed
	 * @param $newSequence int
	 */
	function saveRowDataElementSequence(&$request, $rowId, &$gridDataElement, $newSequence) {
		assert(false);
	}

	/**
	 * Override this method if your subclass needs to perform
	 * different actions than the ones implemented here.
	 * This method is called by GridHandler::fetchGrid()
	 * @param $args array
	 * @param $request Request
	 */
	function doSpecificFetchGridActions($args, &$request, &$templateMgr) {
		$this->_fixColumnWidths();

		// Render the body elements.
		$gridBodyParts = $this->_renderGridBodyPartsInternally($request);
		$templateMgr->assign_by_ref('gridBodyParts', $gridBodyParts);
	}

	/**
	 * Define the first column that will contain
	 * grid data.
	 *
	 * Override this method to define a different column
	 * than the first one.
	 */
	function setFirstDataColumn() {
		$columns =& $this->getColumns();
		$firstColumn =& reset($columns);
		$firstColumn->addFlag('firstColumn', true);
	}

	/**
	 * Override to init grid features.
	 * This method is called by GridHandler::initialize()
	 * method that use the returned array with the initialized
	 * features to add them to grid.
	 * @param $request Request
	 * @param $args array
	 * @return array Array with initialized grid features objects.
	 */
	function initFeatures(&$request, $args) {
		return array();
	}

	/**
	 * Call the passed hook in all attached features.
	 * @param $hookName string
	 * @param $args array Arguments provided by this handler.
	 */
	function callFeaturesHook($hookName, $args) {
		$features = $this->getFeatures();
		if (is_array($features)) {
			foreach ($features as &$feature) {
				if (is_callable(array($feature, $hookName))) {
					$feature->$hookName($args);
				} else {
					assert(false);
				}
			}
		}
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
	function renderRowInternally(&$request, &$row) {
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
		$templateMgr->assign_by_ref('grid', $this);
		$templateMgr->assign_by_ref('columns', $columns);
		$templateMgr->assign_by_ref('cells', $renderedCells);
		$templateMgr->assign_by_ref('row', $row);
		return $templateMgr->fetch($row->getTemplate());
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
	function &_getInitializedRowInstance(&$request, $elementId, &$element, $isModified = false) {
		// Instantiate a new row
		$row =& $this->getRowInstance();
		$row->setGridId($this->getId());
		$row->setId($elementId);
		$row->setData($element);
		$row->setRequestArgs($this->getRequestArgs());
		$row->setIsModified($isModified);

		// Initialize the row before we render it
		$row->initialize($request);
		$this->callFeaturesHook('getInitializedRowInstance', array('row' => &$row));
		return $row;
	}

	/**
	 * Method that renders tbodys to go in the grid main body.
	 * @param Request $request
	 * @return array
	 */
	function _renderGridBodyPartsInternally(&$request) {
		// Render the rows.
		$elements = $this->getGridDataElements($request);
		$renderedRows = $this->_renderRowsInternally($request, $elements);

		// Render the body part.
		$templateMgr =& TemplateManager::getManager();
		$gridBodyParts = array();
		if ( count($renderedRows) > 0 ) {
			$templateMgr->assign_by_ref('grid', $this);
			$templateMgr->assign_by_ref('rows', $renderedRows);
			$gridBodyParts[] = $templateMgr->fetch('controllers/grid/gridBodyPart.tpl');
		}
		return $gridBodyParts;
	}

	/**
	 * Cycle through the data and get generate the row HTML.
	 * @param $request PKPRequest
	 * @param $elements array The grid data elements to be rendered.
	 * @return array of HTML Strings for Grid Rows.
	 */
	function _renderRowsInternally(&$request, &$elements) {
		// Iterate through the rows and render them according
		// to the row definition.
		$renderedRows = array();
		foreach ($elements as $elementId => $element) {
			// Instantiate a new row.
			$row =& $this->_getInitializedRowInstance($request, $elementId, $element);

			// Render the row
			$renderedRows[] = $this->renderRowInternally($request, $row);
			unset($element);
		}

		return $renderedRows;
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
		// If there is no object, then we want to return an empty row.
		// override the assigned GridCellProvider and provide the default.
		$element =& $row->getData();
		if ( is_null($element) && $row->getIsModified() ) {
			import('lib.pkp.classes.controllers.grid.GridCellProvider');
			$cellProvider = new GridCellProvider();
			return $cellProvider->render($request, $row, $column);
		}

		// Otherwise, get the cell content.
		// If row defines a cell provider, use it.
		$cellProvider =& $row->getCellProvider();
		if (!is_a($cellProvider, 'GridCellProvider')) {
			// Remove reference to the row variable.
			unset($cellProvider);
			// Get cell provider from column.
			$cellProvider =& $column->getCellProvider();
		}

		return $cellProvider->render($request, $row, $column);
	}

	/**
	 * Method that grabs all the existing columns and makes sure the column widths add to exactly 100
	 * N.B. We do some extra column fetching because PHP makes copies of arrays with foreach.
	 */
	function _fixColumnWidths() {
		$columns =& $this->getColumns();
		$width = 0;
		$noSpecifiedWidthCount = 0;
		// Find the total width and how many columns do not specify their width.
		foreach ($columns as $column) {
			if ($column->hasFlag('width')) {
				$width += $column->getFlag('width');
			} else {
				$noSpecifiedWidthCount++;
			}
		}

		// Four cases: we have to add or remove some width, and either we have wiggle room or not.
		// We will try just correcting the first case, width less than 100 and some unspecified columns to add it to.
		if ($width < 100) {
			if ($noSpecifiedWidthCount > 0) {
				// We need to add width to columns that did not specify it.
				foreach ($columns as $column) {
					if (!$column->hasFlag('width')) {
						$modifyColumn =& $this->getColumn($column->getId());
						$modifyColumn->addFlag('width', round((100 - $width)/$noSpecifiedWidthCount));
						unset($modifyColumn);
					}
				}
			}
		}
	}

	/**
	 * Add grid features.
	 * @param $features array
	 */
	function _addFeatures($features) {
		assert(is_array($features));
		foreach ($features as &$feature) {
			assert(is_a($feature, 'GridFeature'));
			$this->_features[$feature->getId()] =& $feature;
		}
	}
}
?>

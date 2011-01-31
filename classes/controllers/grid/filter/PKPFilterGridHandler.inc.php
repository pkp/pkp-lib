<?php

/**
 * @file classes/controllers/grid/filter/PKPFilterGridHandler.inc.php
 *
 * Copyright (c) 2000-2011 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class PKPFilterGridHandler
 * @ingroup classes_controllers_grid_filter
 *
 * @brief Manage filter administration and settings.
 */

// import grid base classes
import('lib.pkp.classes.controllers.grid.GridHandler');

// import filter grid specific classes
import('lib.pkp.classes.controllers.grid.filter.PKPFilterGridRow');
import('lib.pkp.classes.controllers.grid.filter.FilterGridCellProvider');

// import metadata framework classes
import('lib.pkp.classes.metadata.MetadataDescription');


class PKPFilterGridHandler extends GridHandler {
	/** @var object the context (journal, press, conference) for which we manage filters */
	var $_context;

	/** @var string the description text to be displayed in the filter form */
	var $_formDescription;

	/** @var mixed sample input object required to identify compatible filters */
	var $_inputSample;

	/** @var mixed sample output object required to identify compatible filters */
	var $_outputSample;

	/**
	 * Constructor
	 */
	function PKPFilterGridHandler() {
		parent::GridHandler();
	}

	//
	// Getters/Setters
	//
	/**
	 * Set the context that filters are being managed for.
	 * This object must implement the getId() and getSettings()
	 * methods.
	 *
	 * @param $context DataObject The context (journal, press,
	 *  conference) for which we manage filters.
	 */
	function setContext(&$context) {
		$this->_context =& $context;
	}

	/**
	 * Get the context that filters are being managed for.
	 *
	 * @return DataObject The context (journal, press,
	 *  conference) for which we manage filters.
	 */
	function &getContext() {
		return $this->_context;
	}

	/**
	 * Set the form description text
	 * @param $formDescription string
	 */
	function setFormDescription($formDescription) {
		$this->_formDescription = $formDescription;
	}

	/**
	 * Get the form description text
	 * @return string
	 */
	function getFormDescription() {
		return $this->_formDescription;
	}

	/**
	 * Set the input sample object
	 * @param $inputSample mixed
	 */
	function setInputSample(&$inputSample) {
		$this->_inputSample =& $inputSample;
	}

	/**
	 * Get the input sample object
	 * @return mixed
	 */
	function &getInputSample() {
		return $this->_inputSample;
	}

	/**
	 * Set the output sample object
	 * @param $outputSample mixed
	 */
	function setOutputSample(&$outputSample) {
		$this->_outputSample =& $outputSample;
	}

	/**
	 * Get the output sample object
	 * @return mixed
	 */
	function &getOutputSample() {
		return $this->_outputSample;
	}


	//
	// Overridden methods from PKPHandler
	//
	/**
	 * Configure the grid
	 * @see PKPHandler::initialize()
	 */
	function initialize(&$request) {
		parent::initialize($request);

		// Load manager-specific translations
		// FIXME: the submission translation component can be removed
		// once all filters have been moved to plug-ins (see submission.xml).
		Locale::requireComponents(array(LOCALE_COMPONENT_PKP_MANAGER, LOCALE_COMPONENT_PKP_SUBMISSION));

		// Retrieve the filters to be displayed in the grid
		$router =& $request->getRouter();
		$context =& $router->getContext($request);
		$contextId = (is_null($context)?0:$context->getId());
		$filterDao =& DAORegistry::getDAO('FilterDAO');
		$data =& $filterDao->getCompatibleObjects($this->getInputSample(), $this->getOutputSample(), $contextId);
		$this->setData($data);

		// Grid action
		$router =& $request->getRouter();
		$this->addAction(
			new LinkAction(
				'addFilter',
				LINK_ACTION_MODE_MODAL,
				LINK_ACTION_TYPE_APPEND,
				$router->url($request, null, null, 'addFilter'),
				'grid.action.addItem'
			)
		);

		// Columns
		$cellProvider = new FilterGridCellProvider();
		$this->addColumn(
			new GridColumn(
				'displayName',
				'manager.setup.filter.grid.filterDisplayName',
				false,
				'controllers/grid/gridCell.tpl',
				$cellProvider
			)
		);
		$this->addColumn(
			new GridColumn(
				'settings',
				'manager.setup.filter.grid.filterSettings',
				false,
				'controllers/grid/gridCell.tpl',
				$cellProvider
			)
		);
	}


	//
	// Overridden methods from GridHandler
	//
	/**
	 * @see GridHandler::getRowInstance()
	 */
	function &getRowInstance() {
		// Return a filter row
		$row = new PKPFilterGridRow();
		return $row;
	}


	//
	// Public Filter Grid Actions
	//
	/**
	 * An action to manually add a new filter
	 * @param $args array
	 * @param $request PKPRequest
	 */
	function addFilter(&$args, &$request) {
		// Calling editFilter() to edit a new filter.
		return $this->editFilter($args, $request, true);
	}

	/**
	 * Edit a filter
	 * @param $args array
	 * @param $request PKPRequest
	 */
	function editFilter(&$args, &$request, $newFilter = false) {
		// Identify the filter to be edited
		if ($newFilter) {
			$filter = null;
		} else {
			$filter =& $this->getFilterFromArgs($args, true);
		}

		// Form handling
		import('lib.pkp.classes.controllers.grid.filter.form.FilterForm');
		$filterForm = new FilterForm($filter, $this->getTitle(), $this->getFormDescription(),
				$this->getInputSample(), $this->getOutputSample());

		$filterForm->initData($this->getData());

		$json = new JSON('true', $filterForm->fetch($request));
		return $json->getString();
	}

	/**
	 * Update a filter
	 * @param $args array
	 * @param $request PKPRequest
	 * @return string
	 */
	function updateFilter(&$args, &$request) {
		if(!$request->isPost()) fatalError('Cannot update filter via GET request!');

		// Identify the citation to be updated
		$filter =& $this->getFilterFromArgs($args, true);

		// Form initialization
		import('lib.pkp.classes.controllers.grid.filter.form.FilterForm');
		$nullVar = null;
		$filterForm = new FilterForm($filter, $this->getTitle(), $this->getFormDescription(),
				$nullVar, $nullVar); // No input/output samples required here.
		$filterForm->readInputData();

		// Form validation
		if ($filterForm->validate()) {
			// Persist the filter.
			$filterForm->execute($request);

			// Render the updated filter row into
			// a JSON response
			$row =& $this->getRowInstance();
			$row->setGridId($this->getId());
			$row->setId($filter->getId());
			$row->setData($filter);
			$row->initialize($request);
			$json = new JSON('true', $this->_renderRowInternally($request, $row));
		} else {
			// Re-display the filter form with error messages
			// so that the user can fix it.
			$json = new JSON('false', $filterForm->fetch($request));
		}

		// Return the serialized JSON response
		return $json->getString();
	}

	/**
	 * Delete a filter
	 * @param $args array
	 * @param $request PKPRequest
	 * @return string
	 */
	function deleteFilter(&$args, &$request) {
		// Identify the filter to be deleted
		$filter =& $this->getFilterFromArgs($args);

		$filterDAO = DAORegistry::getDAO('FilterDAO');
		$result = $filterDAO->deleteObject($filter);

		if ($result) {
			$json = new JSON('true');
		} else {
			$json = new JSON('false', Locale::translate('manager.setup.filter.grid.errorDeletingFilter'));
		}
		return $json->getString();
	}


	//
	// Protected helper functions
	//
	/**
	 * This will retrieve a filter object from the
	 * grids data source based on the request arguments.
	 * If no filter can be found then this will raise
	 * a fatal error.
	 * @param $args array
	 * @param $mayBeTemplate boolean whether filter templates
	 *  should be considered.
	 * @return Filter
	 */
	function &getFilterFromArgs(&$args, $mayBeTemplate = false) {
		if (isset($args['filterId'])) {
			// Identify the filter id and retrieve the
			// corresponding element from the grid's data source.
			$filter =& $this->getRowDataElement($args['filterId']);
			if (!is_a($filter, 'Filter')) fatalError('Invalid filter id!');
		} elseif ($mayBeTemplate && isset($args['filterTemplateId'])) {
			// We need to instantiate a new filter from a
			// filter template.
			$filterTemplateId = $args['filterTemplateId'];
			$filterDao =& DAORegistry::getDAO('FilterDAO');
			$filter =& $filterDao->getObjectById($filterTemplateId);
			if (!is_a($filter, 'Filter')) fatalError('Invalid filter template id!');

			// Reset the filter id and template flag so that the
			// filter form correctly handles this filter as a new filter.
			$filter->setId(null);
			$filter->setIsTemplate(false);
		} else {
			fatalError('Missing filter id!');
		}
		return $filter;
	}
}

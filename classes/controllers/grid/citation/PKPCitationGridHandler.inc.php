<?php

/**
 * @file controllers/grid/citation/PKPCitationGridHandler.inc.php
 *
 * Copyright (c) 2000-2010 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class PKPCitationGridHandler
 * @ingroup controllers_grid_citation
 *
 * @brief Handle generic parts of citation grid requests.
 */

// import grid base classes
import('lib.pkp.classes.controllers.grid.GridHandler');
import('lib.pkp.classes.controllers.grid.citation.PKPCitationGridCellProvider');

// import citation grid specific classes
import('lib.pkp.classes.controllers.grid.citation.PKPCitationGridRow');

class PKPCitationGridHandler extends GridHandler {
	/** @var DataObject */
	var $_assocObject;

	/** @var integer */
	var $_assocType;

	/**
	 * Constructor
	 */
	function PKPCitationGridHandler() {
		parent::GridHandler();
	}

	//
	// Getters/Setters
	//
	/**
	 * Set the object that citations are associated to
	 * This object must implement the getId() and getCitations()
	 * methods.
	 *
	 * @param $assocObject DataObject an object that implements
	 *  getId() and getCitations(). The getCitations() method
	 *  must return a plain text list of all citations associated
	 *  with the object to be processed.
	 *
	 * FIXME: Use a dedicated interface rather than DataObject
	 * once we drop PHP4 support.
	 */
	function setAssocObject(&$assocObject) {
		$this->_assocObject =& $assocObject;
	}

	/**
	 * Get the object that citations are associated to.
	 *
	 * @see PKPCitationGridHandler::setAssocObject() for more details.
	 *
	 * @return DataObject
	 */
	function &getAssocObject() {
		return $this->_assocObject;
	}

	/**
	 * Set the type of the object that citations are associated to.
	 *
	 * @param integer one of the ASSOC_TYPE_* constants
	 */
	function setAssocType($assocType) {
		$this->_assocType = $assocType;
	}

	/**
	 * Get the type of the object that citations are associated to.
	 *
	 * @return integer one of the ASSOC_TYPE_* constants
	 */
	function getAssocType() {
		return $this->_assocType;
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

		// Load submission-specific translations
		Locale::requireComponents(array(LOCALE_COMPONENT_PKP_SUBMISSION));

		// Basic grid configuration
		$this->setTitle('submission.citations.grid.title');

		// Retrieve the associated citations to be displayed in the grid
		$citationDao =& DAORegistry::getDAO('CitationDAO');
		$data =& $citationDao->getObjectsByAssocId($this->getAssocType(), $this->_getAssocId());
		$this->setData($data);

		// Grid actions
		$router =& $request->getRouter();
		$actionArgs = array('assocId' => $this->_getAssocId());
		$this->addAction(
			new LinkAction(
				'addCitation',
				LINK_ACTION_MODE_AJAX,
				LINK_ACTION_TYPE_GET,
				$router->url($request, null, null, 'addCitation', null, $actionArgs),
				'submission.citations.grid.newCitation', null, 'add', null,
				'citationEditorDetailCanvas'
			),
			GRID_ACTION_POSITION_LASTCOL
		);
		/* $this->addAction(
			new LinkAction(
				'exportCitations',
				LINK_ACTION_MODE_MODAL,
				LINK_ACTION_TYPE_NOTHING,
				$router->url($request, null, null, 'exportCitations', null, $actionArgs),
				'submission.citations.grid.exportCitations'
			)
		); */

		// Columns
		$cellProvider = new PKPCitationGridCellProvider();
		$this->addColumn(
			new GridColumn(
				'rawCitation',
				'submission.citations.grid.columnHeaderRawCitation',
				false,
				'controllers/grid/citation/citationGridCell.tpl',
				$cellProvider,
				array('multiline' => true)
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
		// Return a citation row
		$row = new PKPCitationGridRow();
		return $row;
	}

	/**
	 * @see GridHandler::getIsSubcomponent()
	 */
	function getIsSubcomponent() {
		return true;
	}


	//
	// Public Citation Grid Actions
	//
	/**
	 * Export a list of formatted citations
	 * @param $args array
	 * @param $request PKPRequest
	 * @return string
	 */
	function exportCitations(&$args, &$request) {
		// We currently only support the NLM citation schema.
		import('lib.pkp.classes.metadata.nlm.NlmCitationSchema');
		$nlmCitationSchema = new NlmCitationSchema();

		// Retrieve the currently selected filter from the database.
		$router =& $request->getRouter();
		$context =& $router->getContext($request);
		assert(is_object($context));
		$citationOutputFilterId = $context->getSetting('metaCitationOutputFilterId');
		$filterDao =& DAORegistry::getDAO('FilterDAO');
		$citationOutputFilter = $filterDao->getObjectById($citationOutputFilterId);
		assert(is_a($citationOutputFilter, 'Filter'));

		$formattedCitations = array();
		$citations =& $this->_getSortedElements();
		while (!$citations->eof()) {
			// Retrieve NLM citation meta-data
			$citation =& $citations->next();
			$metadataDescription =& $citation->extractMetadata($nlmCitationSchema);
			assert(!is_null($metadataDescription));

			// Apply the citation output format filter
			$formattedCitations[] = $citationOutputFilter->execute($metadataDescription);

			unset($citation, $metadataDescription);
		}

		// Render the citation list
		$templateMgr = TemplateManager::getManager($request);
		$templateMgr->assign_by_ref('formattedCitations', $formattedCitations);
		$json = new JSON('true', $templateMgr->fetch('controllers/grid/citation/citationExport.tpl'));
		return $json->getString();
	}

	/**
	 * An action to manually add a new citation
	 * @param $args array
	 * @param $request PKPRequest
	 */
	function addCitation(&$args, &$request) {
		// Calling editCitation() with an empty row id will add
		// a new citation.
		return $this->editCitation($args, $request);
	}

	/**
	 * Edit a citation
	 * @param $args array
	 * @param $request PKPRequest
	 */
	function editCitation(&$args, &$request) {
		// Identify the citation to be edited
		$citation =& $this->getCitationFromArgs($args, true);

		// Form handling
		import('lib.pkp.classes.controllers.grid.citation.form.CitationForm');
		$citationForm = new CitationForm($citation);
		if ($citationForm->isLocaleResubmit()) {
			$citationForm->readInputData();
		} else {
			$citationForm->initData();
		}
		$json = new JSON('true', $citationForm->fetch($request));
		return $json->getString();
	}

	/**
	 * Check (parse and lookup) a citation
	 * @param $args array
	 * @param $request PKPRequest
	 */
	function checkCitation(&$args, &$request) {
		if ($request->isPost()) {
			// We update the citation with the user's manual settings
			$citationForm =& $this->_saveCitation($args, $request);

			if (!$citationForm->isValid()) {
				// The citation cannot be persisted, so we cannot
				// process it.

				// Re-display the form without processing so that the
				// user can fix the errors that kept us from persisting
				// the citation.
				$json = new JSON('false', $citationForm->fetch($request));
				return $json->getString();
			}

			// We retrieve the citation to be checked from the form.
			$originalCitation =& $citationForm->getCitation();
			unset($citationForm);
		} else {
			// We retrieve the citation to be checked from the database.
			$originalCitation =& $this->getCitationFromArgs($args, true);
		}

		// Find the request context
		$router =& $request->getRouter();
		$context =& $router->getContext($request);
		assert(is_object($context));

		// Do the actual filtering of the citation.
		$citationDAO =& DAORegistry::getDAO('CitationDAO');
		$filteredCitation =& $citationDAO->checkCitation($originalCitation, $context->getId());

		// Immediately persist intermediate results.
		$citationDAO->updateCitationSourceDescriptions($filteredCitation);

		// Crate a new form for the filtered (but yet unsaved) citation data
		import('lib.pkp.classes.controllers.grid.citation.form.CitationForm');
		$citationForm = new CitationForm($filteredCitation);

		// Transport filtering errors to form (if any).
		foreach($filteredCitation->getErrors() as $errorMessage) {
			$citationForm->addError('rawCitation', $errorMessage);
		}

		// Return the rendered form
		$citationForm->initData();
		$json = new JSON('true', $citationForm->fetch($request));
		return $json->getString();
	}

	/**
	 * Update a citation
	 * @param $args array
	 * @param $request PKPRequest
	 * @return string
	 */
	function updateCitation(&$args, &$request) {
		// Try to persist the data in the request.
		$citationForm =& $this->_saveCitation($args, $request);
		if (!$citationForm->isValid()) {
			// Re-display the citation form with error messages
			// so that the user can fix it.
			$json = new JSON('false', $citationForm->fetch($request));
		} else {
			// Update the citation's grid row.
			$savedCitation =& $citationForm->getCitation();
			$row =& $this->getRowInstance();
			$row->setGridId($this->getId());
			$row->setId($savedCitation->getId());
			$row->setData($savedCitation);
			$row->initialize($request);

			// Render the row into a JSON response
			$json = new JSON('true', $this->_renderRowInternally($request, $row));
		}

		// Return the serialized JSON response
		return $json->getString();
	}

	/**
	 * Delete a citation
	 * @param $args array
	 * @param $request PKPRequest
	 * @return string
	 */
	function deleteCitation(&$args, &$request) {
		// Identify the citation to be deleted
		$citation =& $this->getCitationFromArgs($args);

		$citationDAO = DAORegistry::getDAO('CitationDAO');
		$result = $citationDAO->deleteObject($citation);

		if ($result) {
			$json = new JSON('true');
		} else {
			$json = new JSON('false', Locale::translate('submission.citations.grid.errorDeletingCitation'));
		}
		return $json->getString();
	}

	//
	// Protected helper functions
	//
	/**
	 * This will retrieve a citation object from the
	 * grids data source based on the request arguments.
	 * If no citation can be found then this will raise
	 * a fatal error.
	 * @param $args array
	 * @param $createIfMissing boolean If this is set to true
	 *  then a citation object will be instantiated if no
	 *  citation id is in the request.
	 * @return Citation
	 */
	function &getCitationFromArgs(&$args, $createIfMissing = false) {
		// Identify the citation id and retrieve the
		// corresponding element from the grid's data source.
		if (isset($args['citationId'])) {
			$citation =& $this->getRowDataElement($args['citationId']);
			if (is_null($citation)) fatalError('Invalid citation id!');
		} else {
			if ($createIfMissing) {
				// It seems that a new citation is being edited/updated
				import('lib.pkp.classes.citation.Citation');
				$citation = new Citation();
				$citation->setAssocType($this->getAssocType());
				$citation->setAssocId($this->_getAssocId());
			} else {
				fatalError('Missing citation id!');
			}
		}
		return $citation;
	}

	//
	// Private helper functions
	//
	function _getAssocId() {
		$assocObject =& $this->getAssocObject();
		return $assocObject->getId();
	}

	/**
	 * Update citation with POST request data.
	 * @param $args array
	 * @param $request PKPRequest
	 * @return CitationForm the citation form for further processing
	 */
	function &_saveCitation(&$args, &$request) {
		if(!$request->isPost()) fatalError('Cannot update citation via GET request!');

		// Identify the citation to be updated
		$citation =& $this->getCitationFromArgs($args, true);

		// Form initialization
		import('lib.pkp.classes.controllers.grid.citation.form.CitationForm');
		$citationForm = new CitationForm($citation);
		$citationForm->readInputData();

		// Form validation
		if ($citationForm->validate()) {
			// Persist the citation.
			$citationForm->execute();
		}
		return $citationForm;
	}
}

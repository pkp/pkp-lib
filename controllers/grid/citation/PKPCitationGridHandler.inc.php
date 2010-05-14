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
import('lib.pkp.classes.controllers.grid.DataObjectGridCellProvider');

// import citation grid specific classes
import('lib.pkp.controllers.grid.citation.PKPCitationGridRow');

// filter option constants
// FIXME: Make filter options configurable.
define('CROSSREF_TEMP_ACCESS_EMAIL', 'pkp.contact@gmail.com');
define('ISBNDB_TEMP_APIKEY', '4B5GQSQ4');

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
	 * @see PKPHandler::getRemoteOperations()
	 * @return array
	 */
	function getRemoteOperations() {
		return array_merge(parent::getRemoteOperations(), array('addCitation', 'importCitations', 'exportCitations', 'editCitation', 'checkCitation', 'updateCitation', 'deleteCitation'));
	}

	/**
	 * Application-independent validation checks.
	 * @see PKPHandler::validate()
	 */
	function validate($requiredContexts, &$request, &$context) {
		// NB: Error messages are in plain English as they directly go to fatal errors
		// which are not directed to end users. (Validation errors in components are
		// either programming errors or somebody trying to call components directly
		// which is no legal use case.)

		// Restricted site access
		if ( isset($context) && $context->getSetting('restrictSiteAccess')) {
			import('lib.pkp.classes.handler.validation.HandlerValidatorCustom');
			$this->addCheck(new HandlerValidatorCustom($this, false, 'Restricted site access!', null, create_function('', 'if (!Validation::isLoggedIn()) return false; else return true;')));
		}

		// Execute standard checks
		return parent::validate($requiredContexts, $request);
	}

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
			new GridAction(
				'importCitations',
				GRID_ACTION_MODE_AJAX,
				GRID_ACTION_TYPE_GET,
				$router->url($request, null, null, 'importCitations', null, $actionArgs),
				'submission.citations.grid.importCitations'
			)
		);
		$this->addAction(
			new GridAction(
				'addCitation',
				GRID_ACTION_MODE_MODAL,
				GRID_ACTION_TYPE_APPEND,
				$router->url($request, null, null, 'addCitation', null, $actionArgs),
				'grid.action.addItem'
			)
		);
		$this->addAction(
			new GridAction(
				'exportCitations',
				GRID_ACTION_MODE_MODAL,
				GRID_ACTION_TYPE_NOTHING,
				$router->url($request, null, null, 'exportCitations', null, $actionArgs),
				'submission.citations.grid.exportCitations'
			)
		);

		// Columns
		$emptyColumnActions = array();
		$cellProvider = new DataObjectGridCellProvider();
		$this->addColumn(
			new GridColumn(
				'editedCitation',
				'submission.citations.grid.editedCitation',
				null,
				$emptyColumnActions,
				'controllers/grid/gridCellInSpan.tpl',
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


	//
	// Public Citation Grid Actions
	//
	/**
	 * Import citations from an associated object
	 * @param $args array
	 * @param $request PKPRequest
	 * @return string
	 */
	function importCitations(&$args, &$request) {
		// Delete existing citations
		$citationDAO =& DAORegistry::getDAO('CitationDAO');
		$citationDAO->deleteObjectsByAssocId($this->getAssocType(), $this->_getAssocId());

		// Tokenize raw citations
		import('lib.pkp.classes.citation.CitationListTokenizerFilter');
		$citationTokenizer = new CitationListTokenizerFilter();
		$citationStrings = $citationTokenizer->execute($rawCitationList);

		// Instantiate and persist citations
		import('lib.pkp.classes.citation.Citation');
		$citations = array();
		foreach($citationStrings as $citationString) {
			$citation = new Citation($citationString);

			// Initialize the edited citation with the raw
			// citation string.
			$citation->setEditedCitation($citationString);

			// Set the object association
			$citation->setAssocType($this->getAssocType());
			$citation->setAssocId($this->_getAssocId());

			$citationDAO->insertObject($citation);
			// FIXME: Database error handling.
			$citations[$citation->getId()] = $citation;
			unset($citation);
		}
		$this->setData($citations);

		// Re-display the grid
		$json = new JSON('true', $this->fetchGrid($args,$request));
		return $json->getString();
	}

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

		// We currently only support the ABNT citation output schema
		import('lib.pkp.classes.citation.output.apa.NlmCitationSchemaApaFilter');
		$citationOutputFilter = new NlmCitationSchemaApaFilter($request);

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
		import('lib.pkp.controllers.grid.citation.form.CitationForm');
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

		// Initialize the filter errors array
		$filterErrors = array();

		// Only parse the citation if it's not been parsed before.
		// Otherwise we risk to overwrite manual user changes.
		$filteredCitation =& $originalCitation;
		if (!is_null($filteredCitation) && $filteredCitation->getCitationState() < CITATION_PARSED) {
			// Parse the requested citation
			$filterCallback = array(&$this, '_instantiateParserFilters');
			$filteredCitation =& $this->_filterCitation($filteredCitation, $filterCallback, CITATION_PARSED, $filterErrors);
		}

		// Always re-lookup the citation even if it's been looked-up
		// before. The user asked us to re-check so there's probably
		// additional manual information in the citation fields.
		// Also make sure that we get the intermediate results of look-ups
		// for the user to choose from.
		$filterCallback = array(&$this, '_instantiateLookupFilters');
		$filteredCitation =& $this->_filterCitation($filteredCitation, $filterCallback, CITATION_LOOKED_UP, $filterErrors, true);

		if (is_null($filteredCitation)) {
			$filteredCitation =& $originalCitation;
			$unsavedChanges = false;
		} else {
			$unsavedChanges = true;
		}

		// Crate a new form for the filtered (but yet unsaved) citation data
		import('lib.pkp.controllers.grid.citation.form.CitationForm');
		$citationForm = new CitationForm($filteredCitation, $unsavedChanges);

		// Add errors to form (if any)
		foreach($filterErrors as $errorMessage) {
			$citationForm->addError('editedCitation', $errorMessage);
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
		if (!isset($args['citationId'])) {
			if ($createIfMissing) {
				// It seems that a new citation is being edited/updated
				import('lib.pkp.classes.citation.Citation');
				$citation = new Citation();
				$citation->setAssocType($this->getAssocType());
				$citation->setAssocId($this->_getAssocId());
			} else {
				fatalError('Missing citation id!');
			}
		} else {
			$citation =& $this->getRowDataElement($args['citationId']);
			if (is_null($citation)) fatalError('Invalid citation id!');
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
		assert($request->isPost());

		// Identify the citation to be updated
		$citation =& $this->getCitationFromArgs($args, true);

		// Form initialization
		import('lib.pkp.controllers.grid.citation.form.CitationForm');
		$citationForm = new CitationForm($citation);
		$citationForm->readInputData();

		// Form validation
		if ($citationForm->validate()) {
			// Persist the citation.
			$citationForm->execute();
		}
		return $citationForm;
	}

	/**
	 * Instantiates filters that can parse a citation.
	 * FIXME: Make the filter selection configurable and retrieve
	 * filter candidates from the filter registry.
	 * @param $citation Citation
	 * @param $metadataDescription MetadataDescription
	 * @return array a list of filters and the filter input data
	 *  as the last entry in the array.
	 */
	function &_instantiateParserFilters(&$citation, &$metadataDescription) {
		// Extract the edited citation string from the citation
		$citationString = $citation->getEditedCitation();

		// Instantiate the supported parsers
		import('lib.pkp.classes.citation.parser.paracite.ParaciteRawCitationNlmCitationSchemaFilter');
		$paraciteFilter = new ParaciteRawCitationNlmCitationSchemaFilter();
		import('lib.pkp.classes.citation.parser.parscit.ParscitRawCitationNlmCitationSchemaFilter');
		$parscitFilter = new ParscitRawCitationNlmCitationSchemaFilter();
		import('lib.pkp.classes.citation.parser.regex.RegexRawCitationNlmCitationSchemaFilter');
		$regexFilter = new RegexRawCitationNlmCitationSchemaFilter();

		$parserFilters = array(&$paraciteFilter, &$parscitFilter, &$regexFilter, $citationString);
		return $parserFilters;
	}

	/**
	 * Instantiates filters that can validate and amend citations
	 * with information from external data sources.
	 * FIXME: Make the filter selection configurable and retrieve
	 * filter candidates from the filter registry.
	 * @param $citation Citation
	 * @param $metadataDescription MetadataDescription
	 * @return array a list of filters and the filter input data
	 *  as the last entry in the array.
	 */
	function &_instantiateLookupFilters(&$citation, &$metadataDescription) {
		$lookupFilters = array();

		// Instantiate CrossRef filter
		import('lib.pkp.classes.citation.lookup.crossref.CrossrefNlmCitationSchemaFilter');
		$crossrefFilter = new CrossrefNlmCitationSchemaFilter(CROSSREF_TEMP_ACCESS_EMAIL);

		// Instantiate and sequence ISBNdb filters
		import('lib.pkp.classes.citation.lookup.isbndb.IsbndbNlmCitationSchemaIsbnFilter');
		import('lib.pkp.classes.citation.lookup.isbndb.IsbndbIsbnNlmCitationSchemaFilter');
		$nlmToIsbnFilter = new IsbndbNlmCitationSchemaIsbnFilter(ISBNDB_TEMP_APIKEY);
		if ($nlmToIsbnFilter->supportsAsInput($metadataDescription)) {
			$isbnToNlmFilter = new IsbndbIsbnNlmCitationSchemaFilter(ISBNDB_TEMP_APIKEY);
			import('lib.pkp.classes.filter.GenericSequencerFilter');
			$isbndbFilter = new GenericSequencerFilter();
			$isbndbFilter->addFilter($nlmToIsbnFilter, $metadataDescription);
			$isbnSampleData = '1234567890123';
			$isbndbFilter->addFilter($isbnToNlmFilter, $isbnSampleData);
			$lookupFilters[] =& $isbndbFilter;
		}

		// Instantiate the pubmed filter
		import('lib.pkp.classes.citation.lookup.pubmed.PubmedNlmCitationSchemaFilter');
		$pubmedFilter = new PubmedNlmCitationSchemaFilter();

		// Instantiate the WorldCat filter without API key for public usage
		import('lib.pkp.classes.citation.lookup.worldcat.WorldcatNlmCitationSchemaFilter');
		$worldcatFilter = new WorldcatNlmCitationSchemaFilter();

		$lookupFilters = array_merge($lookupFilters, array(&$crossrefFilter, &$pubmedFilter, &$worldcatFilter, $metadataDescription));
		return $lookupFilters;
	}

	/**
	 * Call the callback to filter the citation. If errors occur
	 * they'll be added to the citation form.
	 * @param $citation Citation
	 * @param $filterCallback callable
	 * @param $citationStateAfterFiltering integer the state the citation will
	 *  be set to after the filter was executed.
	 * @param $filterErrors array A reference to a variable that will receive an array of filter errors
	 * @param $saveIntermediateResults boolean
	 * @return Citation the filtered citation or null if an error occurred
	 */
	function &_filterCitation(&$citation, &$filterCallback, $citationStateAfterFiltering, &$filterErrors, $saveIntermediateResults = false) {
		// Make sure that the citation implements the
		// meta-data schema. (We currently only support
		// NLM citation.)
		$supportedMetadataSchemas =& $citation->getSupportedMetadataSchemas();
		assert(count($supportedMetadataSchemas) == 1);
		$metadataSchema =& $supportedMetadataSchemas[0];
		assert(is_a($metadataSchema, 'NlmCitationSchema'));

		// Extract the meta-data description from the citation
		$metadataDescription =& $citation->extractMetadata($metadataSchema);

		// Let the callback build the filter network
		$filterList = call_user_func_array($filterCallback, array(&$citation, &$metadataDescription));

		// The last entry in the filter list is the
		// input data for the returned filters.
		$muxInputData =& array_pop($filterList);

		// Initialize the sample demux input data array.
		$sampleDemuxInputData = array();

		// Instantiate the citation multiplexer filter
		import('lib.pkp.classes.filter.GenericMultiplexerFilter');
		$citationMultiplexer = new GenericMultiplexerFilter();
		$nullVar = null;
		foreach($filterList as $seq => $citationFilter) {
			// Set a sequence id so that we can address the
			// filter results with a unique id when persisting
			// them. This will also determine the sort order
			// on result display.
			$citationFilter->setSeq($seq + 1);

			if ($citationFilter->supports($muxInputData, $nullVar)) {
				$citationMultiplexer->addFilter($citationFilter);
				unset($citationFilter);

				// We expect one citation description per filter
				// in the multiplexer result.
				$sampleDemuxInputData[] = &$metadataDescription;
			}
		}

		// Instantiate the citation de-multiplexer filter
		import('lib.pkp.classes.citation.NlmCitationDemultiplexerFilter');
		$citationDemultiplexer = new NlmCitationDemultiplexerFilter();
		$citationDemultiplexer->setOriginalCitation($citation);

		// Combine multiplexer and de-multiplexer to form the
		// final citation filter network.
		import('lib.pkp.classes.filter.GenericSequencerFilter');
		$citationFilterNet = new GenericSequencerFilter();
		$citationFilterNet->addFilter($citationMultiplexer, $muxInputData);
		$citationFilterNet->addFilter($citationDemultiplexer, $sampleDemuxInputData);

		// Send the input through the citation filter network.
		$filteredCitation =& $citationFilterNet->execute($muxInputData);

		// Add filtering errors (if any) to error list
		$filterErrors = array_merge($filterErrors, $citationFilterNet->getErrors());

		// Return the original citation if the filters
		// did not produce any results.
		if (is_null($filteredCitation)) {
			$filterErrors[] = Locale::translate('submission.citations.form.filterError');
			$filteredCitation =& $citation;
		} else {
			// Copy unfiltered data from the original citation to the filtered citation
			$filteredCitation->setId($citation->getId());
			$filteredCitation->setRawCitation($citation->getRawCitation());
			$filteredCitation->setEditedCitation($citation->getEditedCitation());

			// Associate the citation with the object associated
			// to the citation editor.
			$filteredCitation->setAssocId($this->_getAssocId());
			$filteredCitation->setAssocType($this->getAssocType());

			// Set the citation state
			$filteredCitation->setCitationState($citationStateAfterFiltering);
		}

		// Retrieve the results of intermediate filters for direct
		// user inspection.
		if ($saveIntermediateResults) {
			$intermediateFilterResults =& $citationMultiplexer->getLastOutput();
			// Remove empty results (e.g. if a lookup filter didn't find anything
			// for a given citation).
			$intermediateFilterResults =& arrayClean($intermediateFilterResults);
			$filteredCitation->setSourceDescriptions($intermediateFilterResults);

			// Immediately persist the intermediate results
			$citationDAO =& DAORegistry::getDAO('CitationDAO');
			$citationDAO->updateCitationSourceDescriptions($filteredCitation);
		}

		return $filteredCitation;
	}
}

<?php

/**
 * @file controllers/grid/citation/PKPCitationGridHandler.inc.php
 *
 * Copyright (c) 2013-2015 Simon Fraser University Library
 * Copyright (c) 2000-2015 John Willinsky
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
	// Getters and Setters
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

	/**
	 * Get the assoc id
	 * @return integer one of the ASSOC_TYPE_* values
	 */
	function getAssocId() {
		$assocObject =& $this->getAssocObject();
		return $assocObject->getId();
	}


	//
	// Overridden methods from PKPHandler
	//
	/**
	 * Configure the grid
	 * @see PKPHandler::initialize()
	 */
	function initialize(&$request, $args) {
		parent::initialize($request, $args);

		// Load submission-specific translations
		AppLocale::requireComponents(LOCALE_COMPONENT_PKP_SUBMISSION);

		// Basic grid configuration
		$this->setTitle('submission.citations.editor.citationlist.title');

		// Retrieve the associated citations to be displayed in the grid.
		// Only citations that have already been parsed will be displayed.
		$citationDao =& DAORegistry::getDAO('CitationDAO');
		$data =& $citationDao->getObjectsByAssocId($this->getAssocType(), $this->getAssocId(), CITATION_PARSED);
		$this->setGridDataElements($data);

		// If the refresh flag is set in the request then trigger
		// citation parsing. This is necessary to make sure that
		// processing of citations is re-triggered if one of the
		// background processes dies due to an error. The spawning of
		// processes is idempotent. So it is not a problem if this is
		// called while all processes are still running.
		if (isset($args['refresh'])) {
			$noOfProcesses = (int)Config::getVar('general', 'citation_checking_max_processes');
			$processDao =& DAORegistry::getDAO('ProcessDAO');
			$processDao->spawnProcesses($request, 'api.citation.CitationApiHandler', 'checkAllCitations', PROCESS_TYPE_CITATION_CHECKING, $noOfProcesses);
		}

		// Grid actions
		$router =& $request->getRouter();
		$this->addAction(
			new LegacyLinkAction(
				'addCitation',
				LINK_ACTION_MODE_AJAX,
				LINK_ACTION_TYPE_GET,
				$router->url(
					$request, null, null, 'addCitation', null,
					array('assocId' => $this->getAssocId())
				),
				'submission.citations.editor.citationlist.newCitation', null, 'add', null,
				'citationEditorDetailCanvas'
			),
			GRID_ACTION_POSITION_LASTCOL
		);

		// Columns
		$cellProvider = new PKPCitationGridCellProvider();
		$this->addColumn(
			new GridColumn(
				'rawCitation',
				null,
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
	// Public grid actions
	//
	/**
	 * Export a list of formatted citations
	 * @param $args array
	 * @param $request PKPRequest
	 * @param $noCitationsFoundMessage string an app-specific help message
	 * @return string a serialized JSON message
	 */
	function exportCitations(&$args, &$request, $noCitationsFoundMessage) {
		$router =& $request->getRouter();
		$context =& $router->getContext($request);
		$templateMgr = TemplateManager::getManager($request);

		$errorMessage = null;
		$citations =& $this->getGridDataElements($request);
		if (empty($citations)) {
			$errorMessage = $noCitationsFoundMessage;
		} else {
			// Check whether we have any unapproved citations.
			foreach($citations as $citation) {
				// Retrieve NLM citation meta-data
				if ($citation->getCitationState() < CITATION_APPROVED) {
					// Oops, found an unapproved citation, won't be able to
					// export then.
					$errorMessage = __('submission.citations.editor.export.foundUnapprovedCitationsMessage');
					break;
				}
			}

			// Only go on when we've no error so far
			if (is_null($errorMessage)) {
				// Provide the assoc id to the template.
				$templateMgr->assign_by_ref('assocId', $this->getAssocId());

				// Identify export filters.
				$filterDao =& DAORegistry::getDAO('FilterDAO'); /* @var $filterDao FilterDAO */
				$allowedFilterIds = array();

				// Retrieve export filters.
				$exportFilter = null;
				$exportFilters = array();
				$exportFilterConfiguration = $this->_getExportFilterConfiguration();
				foreach($exportFilterConfiguration as $selectListHeading => $outputType) {
					// All filters that take a submission and one of the supported
					// output types will be displayed.
					$exportFilterObjects =& $filterDao->getObjectsByTypeDescription('class::lib.pkp.classes.submission.Submission', $outputType);

					// Build the array for the template.
					$exportFilters[$selectListHeading] = array();
					foreach($exportFilterObjects as $exportFilterObject) { /* @var $exportFilterObject PersistableFilter */
						$filterId = $exportFilterObject->getId();

						// Use the first filter as default export filter.
						if (is_null($exportFilter)) {
							$exportFilter =& $exportFilterObject;
							$exportFilterId = $filterId;
						}

						// FIXME: Move &nbsp; to the template.
						$exportFilters[$selectListHeading][$filterId] = '&nbsp;'.$exportFilterObject->getDisplayName();
						$allowedFilterIds[$filterId] = $outputType;

						unset($exportFilterObject);
					}

					unset($exportFilterObjects);
				}
				$templateMgr->assign_by_ref('exportFilters', $exportFilters);

				// Did the user choose a custom filter?
				if (isset($args['filterId'])) {
					$exportFilterId = (int)$args['filterId'];
					if (isset($allowedFilterIds[$exportFilterId])) {
						$exportFilter =& $filterDao->getObjectById($exportFilterId);
					}
				}

				// Prepare the export output if a filter has been identified.
				$exportOutputString = '';
				if (is_a($exportFilter, 'Filter')) {
					// Make the template aware of the selected filter.
					$templateMgr->assign('exportFilterId', $exportFilterId);

					// Save the export filter type to the template.
					$exportType = $allowedFilterIds[$exportFilterId];
					$templateMgr->assign('exportFilterType', $exportType);

					// Apply the citation output format filter.
					$exportOutput = $exportFilter->execute($this->getAssocObject());

					// Generate an error message if the export was not successful.
					if (empty($exportOutput)) {
						$errorMessage = __('submission.citations.editor.export.noExportOutput', array('filterName' => $exportFilter->getDisplayName()));
					}

					if (is_null($errorMessage)) {
						switch (substr($exportType, 0, 5)) {
							case 'xml::':
								// Pretty-format XML output.
								$xmlDom = new DOMDocument();
								$xmlDom->preserveWhiteSpace = false;
								$xmlDom->formatOutput = true;
								$xmlDom->loadXml($exportOutput);
								$exportOutputString = $xmlDom->saveXml($xmlDom->documentElement);
								break;

							default:
								assert(is_a($exportOutput, 'PlainTextReferencesList'));
								$exportOutputString = $exportOutput->getListContent();
						}
					}
				}
				$templateMgr->assign_by_ref('exportOutput', $exportOutputString);
			}
		}

		// Render the citation list
		$templateMgr->assign('errorMessage', $errorMessage);
		return $templateMgr->fetchJson('controllers/grid/citation/citationExport.tpl');
	}

	/**
	 * An action to manually add a new citation
	 * @param $args array
	 * @param $request PKPRequest
	 * @return string a serialized JSON message
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
	 * @return string a serialized JSON message
	 */
	function editCitation(&$args, &$request) {
		// Identify the citation to be edited
		$citation =& $this->getCitationFromArgs($request, $args, true);

		// Form handling
		import('lib.pkp.classes.controllers.grid.citation.form.CitationForm');
		$citationForm = new CitationForm($request, $citation, $this->getAssocObject());
		if ($citationForm->isLocaleResubmit()) {
			$citationForm->readInputData();
		} else {
			$citationForm->initData();
		}
		$json = new JSONMessage(true, $citationForm->fetch($request));
		return $json->getString();
	}

	/**
	 * Change the raw text of a citation and re-process it.
	 * @param $args array
	 * @param $request PKPRequest
	 * @return string a serialized JSON message
	 */
	function updateRawCitation(&$args, &$request) {
		// Retrieve the citation to be changed from the database.
		$citation =& $this->getCitationFromArgs($request, $args, true);

		// Now retrieve the raw citation from the request.
		$citation->setRawCitation(strip_tags($request->getUserVar('rawCitation')));

		// Resetting the citation state to "raw" will trigger re-parsing.
		$citation->setCitationState(CITATION_RAW);

		return $this->_recheckCitation($request, $citation, false);
	}

	/**
	 * Check (parse and lookup) a citation
	 * @param $args array
	 * @param $request PKPRequest
	 * @return string a serialized JSON message
	 */
	function checkCitation(&$args, &$request) {
		if ($request->isPost()) {
			// We update the citation with the user's manual settings
			$citationForm =& $this->_handleCitationForm($args, $request);

			if (!$citationForm->isValid()) {
				// The citation cannot be persisted, so we cannot
				// process it.

				// Re-display the form without processing so that the
				// user can fix the errors that kept us from persisting
				// the citation.
				$json = new JSONMessage(false, $citationForm->fetch($request));
				return $json->getString();
			}

			// We retrieve the citation to be checked from the form.
			$originalCitation =& $citationForm->getCitation();
			unset($citationForm);
		} else {
			// We retrieve the citation to be checked from the database.
			$originalCitation =& $this->getCitationFromArgs($request, $args, true);
		}

		return $this->_recheckCitation($request, $originalCitation, false);
	}

	/**
	 * Update a citation
	 * @param $args array
	 * @param $request PKPRequest
	 * @return string a serialized JSON message
	 */
	function updateCitation(&$args, &$request) {
		// Try to persist the data in the request.
		$citationForm =& $this->_handleCitationForm($args, $request);
		if (!$citationForm->isValid()) {
			// Re-display the citation form with error messages
			// so that the user can fix it.
			$json = new JSONMessage(false, $citationForm->fetch($request));
		} else {
			// Get the persisted citation from the form.
			$savedCitation =& $citationForm->getCitation();

			// If the citation is not yet parsed then
			// parse it now (should happen on citation
			// creation only)
			if ($savedCitation->getCitationState() < CITATION_PARSED) {
				// Assert that this is a new citation.
				assert(!isset($args['citationId']));
				$savedCitation =& $this->_recheckCitation($request, $savedCitation, true);
				assert(is_a($savedCitation, 'Citation'));
			}

			// Update the citation's grid row.
			$row =& $this->getRowInstance();
			$row->setGridId($this->getId());
			$row->setId($savedCitation->getId());
			$row->setData($savedCitation);
			if (isset($args['remainsCurrentItem']) && $args['remainsCurrentItem'] == 'yes') {
				$row->setIsCurrentItem(true);
			}
			$row->initialize($request);

			// Render the row into a JSON response
			$json = new JSONMessage(true, $this->renderRowInternally($request, $row));
		}

		// Return the serialized JSON response
		return $json->getString();
	}

	/**
	 * Delete a citation
	 * @param $args array
	 * @param $request PKPRequest
	 * @return string a serialized JSON message
	 */
	function deleteCitation(&$args, &$request) {
		// Identify the citation to be deleted
		$citation =& $this->getCitationFromArgs($request, $args);

		$citationDao = DAORegistry::getDAO('CitationDAO');
		$result = $citationDao->deleteObject($citation);

		if ($result) {
			$json = new JSONMessage(true);
		} else {
			$json = new JSONMessage(false, __('submission.citations.editor.citationlist.errorDeletingCitation'));
		}
		return $json->getString();
	}

	/**
	 * Fetch the posted citation as a citation string with
	 * calculated differences between the field based and the
	 * raw version.
	 * @param $args array
	 * @param $request PKPRequest
	 * @return string a serialized JSON message
	 */
	function fetchCitationFormErrorsAndComparison(&$args, &$request) {
		// Read the data in the request into
		// the form without persisting the data.
		$citationForm =& $this->_handleCitationForm($args, $request, false);

		// Render the form with the citation diff.
		$output = $citationForm->fetch($request, CITATION_FORM_COMPARISON_TEMPLATE);

		// Render the row into a JSON response
		$json = new JSONMessage(true, $output);
		return $json->getString();
	}

	/**
	 * Send an author query based on the posted data.
	 * @param $args array
	 * @param $request PKPRequest
	 * @return string a serialized JSON message
	 */
	function sendAuthorQuery(&$args, &$request) {
		// Instantiate the email to the author.
		import('lib.pkp.classes.mail.Mail');
		$mail = new Mail();

		// Recipient
		$assocObject =& $this->getAssocObject();
		$author =& $assocObject->getUser();
		$mail->addRecipient($author->getEmail(), $author->getFullName());

		// The message
		$mail->setSubject(strip_tags($request->getUserVar('authorQuerySubject')));
		$mail->setBody(strip_tags($request->getUserVar('authorQueryBody')));

		$mail->send();

		// In principle we should use a template here but this seems exaggerated
		// for such a small message.
		$json = new JSONMessage(true,
			'<div id="authorQueryResult"><span class="pkp_form_error">'
			.__('submission.citations.editor.details.sendAuthorQuerySuccess')
			.'</span></div>');
		return $json->getString();
	}


	//
	// Protected helper methods
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
	function &getCitationFromArgs($request, &$args, $createIfMissing = false) {
		// Identify the citation id and retrieve the
		// corresponding element from the grid's data source.
		if (isset($args['citationId'])) {
			$citation =& $this->getRowDataElement($request, $args['citationId']);
			if (is_null($citation)) fatalError('Invalid citation id!');
		} else {
			if ($createIfMissing) {
				// It seems that a new citation is being edited/updated
				import('lib.pkp.classes.citation.Citation');
				$citation = new Citation();
				$citation->setAssocType($this->getAssocType());
				$citation->setAssocId($this->getAssocId());
			} else {
				fatalError('Missing citation id!');
			}
		}
		return $citation;
	}

	//
	// Private helper methods
	//
	/**
	 * This method returns the texts and filter groups that should be
	 * presented for citation reference list export.
	 *
	 * FIXME: We either have to move that somewhere to the configuration
	 * or create more generic filter groups if we want to support several
	 * meta-data schemas.
	 *
	 * @return array keys are translation keys that point to the heading
	 *  to be displayed in the select list, the values are the type description
	 *  wildcards to be used for filter selection in this group.
	 */
	function _getExportFilterConfiguration() {
		return array(
			'submission.citations.editor.export.pleaseSelectXmlFilter' => 'xml::%',
			'submission.citations.editor.export.pleaseSelectPlaintextFilter' => 'class::lib.pkp.classes.citation.PlainTextReferencesList'
		);
	}

	/**
	 * Create and validate a citation form with POST
	 * request data and (optionally) persist the citation.
	 * @param $args array
	 * @param $request PKPRequest
	 * @param $persist boolean
	 * @return CitationForm the citation form for further processing
	 */
	function &_handleCitationForm(&$args, &$request, $persist = true) {
		if(!$request->isPost()) fatalError('Cannot update citation via GET request!');

		// Identify the citation to be updated
		$citation =& $this->getCitationFromArgs($request, $args, true);

		// Form initialization
		import('lib.pkp.classes.controllers.grid.citation.form.CitationForm');
		$citationForm = new CitationForm($request, $citation, $this->getAssocObject());
		$citationForm->readInputData();

		// Form validation
		if ($citationForm->validate() && $persist) {
			// Persist the citation.
			$citationForm->execute();
		} else {
			// Mark the citation form "dirty".
			$citationForm->setUnsavedChanges(true);
		}
		return $citationForm;
	}


	/**
	 * Internal method that re-checks the given citation and
	 * returns a rendered citation editing form with the changes.
	 * @param $request PKPRequest
	 * @param $originalCitation Citation
	 * @param $persist boolean whether to save (true) or render (false)
	 * @return string|Citation a serialized JSON message with the citation
	 *  form when $persist is false, else the persisted citation object.
	 */
	function _recheckCitation(&$request, &$originalCitation, $persist = true) {
		// Extract filters to be applied from request
		$requestedFilters = $request->getUserVar('citationFilters');
		$filterIds = array();
		if (is_array($requestedFilters)) {
			foreach($requestedFilters as $filterId => $value) {
				$filterIds[] = (int)$filterId;
			}
		}

		// Do the actual filtering of the citation.
		$citationDao =& DAORegistry::getDAO('CitationDAO');
		$filteredCitation =& $citationDao->checkCitation($request, $originalCitation, $filterIds);

		// Crate a new form for the filtered (but yet unsaved) citation data
		import('lib.pkp.classes.controllers.grid.citation.form.CitationForm');
		$citationForm = new CitationForm($request, $filteredCitation, $this->getAssocObject());

		// Transport filtering errors to form (if any).
		foreach($filteredCitation->getErrors() as $index => $errorMessage) {
			$citationForm->addError('rawCitation['.$index.']', $errorMessage);
		}

		if ($persist) {
			// Persist the checked citation.
			$citationDao->updateObject($filteredCitation);

			// Return the persisted citation.
			return $filteredCitation;
		} else {
			// Only persist intermediate results.
			$citationDao->updateCitationSourceDescriptions($filteredCitation);

			// Mark the citation form "dirty".
			$citationForm->setUnsavedChanges(true);

			// Return the rendered form.
			$citationForm->initData();
			$json = new JSONMessage(true, $citationForm->fetch($request));
			return $json->getString();
		}
	}
}

?>

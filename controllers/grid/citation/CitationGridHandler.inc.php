<?php

/**
 * @file controllers/grid/citation/CitationGridHandler.inc.php
 *
 * Copyright (c) 2000-2009 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class CitationGridHandler
 * @ingroup controllers_grid_citation
 *
 * @brief Handle citation grid requests.
 */

// import grid base classes
import('controllers.grid.GridHandler');
import('controllers.grid.DataObjectGridCellProvider');

// import citation grid specific classes
import('controllers.grid.citation.CitationGridRow');

// import validation classes
import('handler.validation.HandlerValidatorJournal');
import('handler.validation.HandlerValidatorRoles');

class CitationGridHandler extends GridHandler {
	/** @var Article */
	var $_article;

	/**
	 * Constructor
	 */
	function CitationGridHandler() {
		parent::GridHandler();
	}

	//
	// Getters/Setters
	//
	/**
	 * @see PKPHandler::getRemoteOperations()
	 */
	function getRemoteOperations() {
		return array_merge(parent::getRemoteOperations(), array('addCitation', 'importCitations', 'editCitation', 'parseCitation', 'updateCitation', 'deleteCitation'));
	}

	/**
	 * Get the article associated with this citation grid.
	 * @return Article
	 */
	function &getArticle() {
		return $this->_article;
	}


	//
	// Overridden methods from PKPHandler
	//
	/**
	 * Validate that the user is the assigned section editor for
	 * the citation's article, or is a managing editor. Raises a
	 * fatal error if validation fails.
	 * @param $requiredContexts array
	 * @param $request PKPRequest
	 */
	function validate($requiredContexts, $request) {
		// Retrieve the request context
		$router =& $request->getRouter();
		$journal =& $router->getContext($request);

		// Authorization and validation checks
		// 1) restricted site access
		if ( isset($journal) && $journal->getSetting('restrictSiteAccess')) {
			import('handler.validation.HandlerValidatorCustom');
			$this->addCheck(new HandlerValidatorCustom($this, false, 'Restricted site access!', null, create_function('', 'if (!Validation::isLoggedIn()) return false; else return true;')));
		}

		// 2) we need a journal
		$this->addCheck(new HandlerValidatorJournal($this, false, 'No journal in context!'));

		// 3) only editors or section editors may access
		$this->addCheck(new HandlerValidatorRoles($this, false, 'Insufficient privileges!', null, array(ROLE_ID_EDITOR, ROLE_ID_SECTION_EDITOR)));

		// Execute standard checks
		if (!parent::validate($requiredContexts, $request)) return false;

		// Retrieve and validate the article id
		$articleId =& $request->getUserVar('articleId');
		if (!is_numeric($articleId)) return false;

		// Retrieve the article associated with this citation grid
		$articleDAO =& DAORegistry::getDAO('ArticleDAO');
		$article =& $articleDAO->getArticle($articleId);

		// Article and editor validation
		if (!is_a($article, 'Article')) return false;
		if ($article->getJournalId() != $journal->getId()) return false;

		// Editors have access to all articles, section editors will be
		// checked individually.
		if (!Validation::isEditor()) {
			// Retrieve the edit assignments
			$editAssignmentDao =& DAORegistry::getDAO('EditAssignmentDAO');
			$editAssignments =& $editAssignmentDao->getEditAssignmentsByArticleId($article->getId());
			assert(is_a($editAssignments, 'DAOResultFactory'));
			$editAssignmentsArray =& $editAssignments->toArray();

			// Check whether the user is the article's editor,
			// otherwise deny access.
			$user =& $request->getUser();
			$userId = $user->getId();
			$wasFound = false;
			foreach ($editAssignmentsArray as $editAssignment) {
				if ($editAssignment->getEditorId() == $userId) {
					if ($editAssignment->getCanEdit()) $wasFound = true;
					break;
				}
			}

			if (!$wasFound) return false;
		}

		// Validation successful
		$this->_article =& $article;
		return true;
	}

	/*
	 * Configure the grid
	 * @param PKPRequest $request
	 */
	function initialize(&$request) {
		parent::initialize($request);

		// Load submission-specific translations
		Locale::requireComponents(array(LOCALE_COMPONENT_PKP_SUBMISSION));

		// Basic grid configuration
		$this->setTitle('submission.citations.grid.title');

		// Get the article id
		$article =& $this->getArticle();
		assert(is_a($article, 'Article'));
		$articleId = $article->getId();

		// Retrieve the citations associated with this article to be displayed in the grid
		$citationDao =& DAORegistry::getDAO('CitationDAO');
		$data =& $citationDao->getCitationsByAssocId(ASSOC_TYPE_ARTICLE, $articleId);
		$this->setData($data);

		// Grid actions
		$router =& $request->getRouter();
		$actionArgs = array('articleId' => $articleId);
		$this->addAction(
			new GridAction(
				'importCitations',
				GRID_ACTION_MODE_AJAX,
				GRID_ACTION_TYPE_NOTHING,
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

		// Columns
		$emptyColumnActions = array();
		$cellProvider = new DataObjectGridCellProvider();
		$this->addColumn(
			new GridColumn(
				'editedCitation',
				'submission.citations.grid.editedCitation',
				$emptyColumnActions,
				'controllers/grid/gridCellInSpan.tpl',
				$cellProvider
			)
		);
	}


	//
	// Overridden methods from GridHandler
	//
	/**
	 * @see GridHandler::getRowInstance()
	 * @return CitationGridRow
	 */
	function &getRowInstance() {
		// Return a citation row
		$row = new CitationGridRow();
		return $row;
	}


	//
	// Public Citation Grid Actions
	//
	/**
	 * Import citations from the article
	 * @param $args array
	 * @param $request PKPRequest
	 */
	function importCitations(&$args, &$request) {
		$article =& $this->getArticle();
		assert(is_a($article, 'Article'));

		// Delete existing citations
		$citationDAO =& DAORegistry::getDAO('CitationDAO');
		$citationDAO->deleteCitationsByAssocId(ASSOC_TYPE_ARTICLE, $article->getId());

		// (Re-)import raw citations from the article
		$rawCitationList = $article->getCitations();
		import('citation.CitationListTokenizerFilter');
		$citationTokenizer = new CitationListTokenizerFilter();
		$citationStrings = $citationTokenizer->execute($rawCitationList);

		// Instantiate and persist citations
		import('citation.Citation');
		$citations = array();
		foreach($citationStrings as $citationString) {
			$citation = new Citation($citationString);

			// Initialize the edited citation with the raw
			// citation string.
			$citation->setEditedCitation($citationString);

			// Set the article association
			$citation->setAssocType(ASSOC_TYPE_ARTICLE);
			$citation->setAssocId($article->getId());

			$citationDAO->insertCitation($citation);
			// FIXME: Database error handling.
			$citations[] = $citation;
			unset($citation);
		}
		$this->setData($citations);

		// Re-display the grid
		return $this->fetchGrid($args,$request);
	}

	/**
	 * An action to manually add a new citation
	 * @param $args array
	 * @param $request PKPRequest
	 */
	function addCitation(&$args, &$request) {
		// Calling editCitation() with an empty row id will add
		// a new citation.
		$this->editCitation($args, $request);
	}

	/**
	 * Edit a citation
	 * @param $args array
	 * @param $request PKPRequest
	 */
	function editCitation(&$args, &$request) {
		// Instantiate the citation to be edited
		if (!isset($args['citationId'])) {
			// It seems that a new citation is being edited
			import('citation.Citation');
			$citation = new Citation();
		} else {
			// Edit an existing citation
			$citation =& $this->_getCitationFromArgs($args);
		}

		// Form handling
		import('controllers.grid.citation.form.CitationForm');
		$citationForm = new CitationForm($citation);
		if ($citationForm->isLocaleResubmit()) {
			$citationForm->readInputData();
		} else {
			$citationForm->initData();
		}
		$citationForm->display($request);

		// The form has already been displayed.
		return '';
	}

	/**
	 * Parse a citation
	 * @param $args array
	 * @param $request PKPRequest
	 */
	function parseCitation(&$args, &$request) {
		// Identify the citation to be parsed
		$citation =& $this->_getCitationFromArgs($args);

		// Make sure that the citation implements the
		// meta-data schema. (We currently only support
		// NLM citation.)
		$supportedMetadataSchemas =& $citation->getSupportedMetadataSchemas();
		assert(count($supportedMetadataSchemas) == 1);
		$metadataSchema =& $supportedMetadataSchemas[0];
		assert(is_a($metadataSchema, 'NlmCitationSchema'));

		// Extract the edited citation string from the citation
		$citationString = $citation->getEditedCitation();

		// Instantiate the supported parsers
		//import('citation.parser.freecite.FreeciteRawCitationNlmCitationSchemaFilter');
		//$freeciteFilter = new FreeciteRawCitationNlmCitationSchemaFilter();
		import('citation.parser.paracite.ParaciteRawCitationNlmCitationSchemaFilter');
		$paraciteFilter = new ParaciteRawCitationNlmCitationSchemaFilter();
		import('citation.parser.parscit.ParscitRawCitationNlmCitationSchemaFilter');
		$parscitFilter = new ParscitRawCitationNlmCitationSchemaFilter();
		import('citation.parser.regex.RegexRawCitationNlmCitationSchemaFilter');
		$regexFilter = new RegexRawCitationNlmCitationSchemaFilter();

		// Instantiate the citation parser multiplexer filter
		import('filter.GenericMultiplexerFilter');
		$citationParserMultiplexer = new GenericMultiplexerFilter();
		//$citationParserMultiplexer->addFilter($freeciteFilter);
		$citationParserMultiplexer->addFilter($paraciteFilter);
		$citationParserMultiplexer->addFilter($parscitFilter);
		$citationParserMultiplexer->addFilter($regexFilter);

		// Instantiate the citation de-multiplexer filter
		import('citation.NlmCitationParserDemultiplexerFilter');
		$citationParserDemultiplexer = new NlmCitationParserDemultiplexerFilter();

		// Sequence filters to form the final citation parser filter
		import('filter.GenericSequencerFilter');
		$citationParser = new GenericSequencerFilter();
		$citationParser->addFilter($citationParserMultiplexer, $citationString);
		$sampleMetadataDescription =& $citation->extractMetadata($metadataSchema);
		$demuxSampleData = array(&$sampleMetadataDescription, &$sampleMetadataDescription, &$sampleMetadataDescription);
		$citationParser->addFilter($citationParserDemultiplexer, $demuxSampleData);

		// Parse the citation string
		$parsedCitation =& $citationParser->execute($citationString);
		if (is_null($parsedCitation)) fatalError('Parsing error!');

		// Persist the parsed citation
		$parsedCitation->setId($citation->getId());
		$article =& $this->_article;
		$parsedCitation->setAssocId($article->getId());
		$parsedCitation->setAssocType(ASSOC_TYPE_ARTICLE);
		$parsedCitation->setRawCitation($citation->getRawCitation());
		$parsedCitation->setEditedCitation($citationString);
		$citationDAO =& DAORegistry::getDAO('CitationDAO');
		$citationDAO->updateCitation($parsedCitation);

		// Re-display the form
		import('controllers.grid.citation.form.CitationForm');
		$citationForm = new CitationForm($parsedCitation);
		$citationForm->initData();
		$citationForm->display($request);

		// The form has already been displayed.
		return '';
	}

	/**
	 * Update a citation
	 * @param $args array
	 * @param $request PKPRequest
	 * @return string
	 */
	function updateCitation(&$args, &$request) {
		// Identify the citation to be updated
		$citation =& $this->_getCitationFromArgs($args);

		// Form initialization
		import('controllers.grid.citation.form.CitationForm');
		$citationForm = new CitationForm($citation);
		$citationForm->readInputData();

		// Form validation
		if ($citationForm->validate()) {
			$citationForm->execute();

			// Prepare the grid row data
			$row =& $this->getRowInstance();
			$row->setGridId($this->getId());
			$row->setId($citation->getId());
			$row->setData($citation);

			// Render the row into a JSON response
			$json = new JSON('true', $this->_renderRowInternally($request, $row));
		} else {
			// Return an error
			$json = new JSON('false');
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
		$citation =& $this->_getCitationFromArgs($args);

		$citationDAO = DAORegistry::getDAO('CitationDAO');
		$result = $citationDAO->deleteCitation($citation);

		if ($result) {
			$json = new JSON('true');
		} else {
			$json = new JSON('false', Locale::translate('submission.citations.grid.errorDeletingCitation'));
		}
		echo $json->getString();
	}

	//
	// Private helper function
	//
	/**
	 * This will retrieve a citation object from the
	 * grids data source based on the request arguments.
	 * If no citation can be found then this will raise
	 * a fatal error.
	 * @param $args array
	 * @return Citation
	 */
	function &_getCitationFromArgs(&$args) {
		// Identify the citation id and retrieve the
		// corresponding element from the grid's data source.
		if (!isset($args['citationId'])) fatalError('Missing citation id!');
		$citation =& $this->getRowDataElement($args['citationId']);
		if (is_null($citation)) fatalError('Invalid citation id!');
		return $citation;
	}
}
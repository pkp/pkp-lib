<?php

/**
 * @file CitationDAO.inc.php
 *
 * Copyright (c) 2000-2011 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class CitationDAO
 * @ingroup citation
 * @see Citation
 *
 * @brief Operations for retrieving and modifying Citation objects
 */

import('lib.pkp.classes.citation.Citation');

class CitationDAO extends DAO {
	/**
	 * Insert a new citation.
	 * @param $citation Citation
	 * @return integer the new citation id
	 */
	function insertObject(&$citation) {
		$seq = $citation->getSeq();
		if (!(is_numeric($seq) && $seq > 0)) {
			// Find the latest sequence number
			$result =& $this->retrieve(
				'SELECT MAX(seq) AS lastseq FROM citations
				 WHERE assoc_type = ? AND assoc_id = ?',
				array(
					(integer)$citation->getAssocType(),
					(integer)$citation->getAssocId(),
				)
			);

			if ($result->RecordCount() != 0) {
				$row =& $result->GetRowAssoc(false);
				$seq = $row['lastseq'] + 1;
			} else {
				$seq = 1;
			}
			$citation->setSeq($seq);
		}

		$this->update(
			sprintf('INSERT INTO citations
				(assoc_type, assoc_id, citation_state, raw_citation, seq)
				VALUES
				(?, ?, ?, ?, ?)'),
			array(
				(integer)$citation->getAssocType(),
				(integer)$citation->getAssocId(),
				(integer)$citation->getCitationState(),
				$citation->getRawCitation(),
				(integer)$seq
			)
		);
		$citation->setId($this->getInsertId());
		$this->_updateObjectMetadata($citation, false);
		$this->updateCitationSourceDescriptions($citation);
		return $citation->getId();
	}

	/**
	 * Retrieve a citation by id.
	 * @param $citationId integer
	 * @return Citation
	 */
	function &getObjectById($citationId) {
		$result =& $this->retrieve(
			'SELECT * FROM citations WHERE citation_id = ?', $citationId
		);

		$citation = null;
		if ($result->RecordCount() != 0) {
			$citation =& $this->_fromRow($result->GetRowAssoc(false));
		}
		$result->Close();

		return $citation;
	}

	/**
	 * Import citations from a raw citation list to the object
	 * described by the given association type and id.
	 * @param $request Request
	 * @param $assocType int
	 * @param $assocId int
	 * @param $rawCitationList string
	 * @return integer the number of spawned citation checking processes
	 */
	function importCitations(&$request, $assocType, $assocId, $rawCitationList) {
		assert(is_numeric($assocType) && is_numeric($assocId));
		$assocType = (int) $assocType;
		$assocId = (int) $assocId;

		// Remove existing citations.
		$this->deleteObjectsByAssocId($assocType, $assocId);

		// Tokenize raw citations
		import('lib.pkp.classes.citation.CitationListTokenizerFilter');
		$citationTokenizer = new CitationListTokenizerFilter();
		$citationStrings = $citationTokenizer->execute($rawCitationList);

		// Instantiate and persist citations
		$citations = array();
		if (is_array($citationStrings)) foreach($citationStrings as $seq => $citationString) {
			$citation = new Citation($citationString);

			// Initialize the citation with the raw
			// citation string.
			$citation->setRawCitation($citationString);

			// Set the object association
			$citation->setAssocType($assocType);
			$citation->setAssocId($assocId);

			// Set the counter
			$citation->setSeq($seq+1);

			$this->insertObject($citation);
			$citations[$citation->getId()] = $citation;
			unset($citation);
		}

		// Check new citations in parallel.
		$noOfProcesses = (int)Config::getVar('general', 'citation_checking_max_processes');
		$processDao =& DAORegistry::getDAO('ProcessDAO');
		return $processDao->spawnProcesses($request, 'api.citation.CitationApiHandler', 'checkAllCitations', PROCESS_TYPE_CITATION_CHECKING, $noOfProcesses);
	}

	/**
	 * Parses and looks up the given citation.
	 *
	 * NB: checking the citation will not automatically
	 * persist the changes. This has to be done by the caller.
	 *
	 * @param $request Request
	 * @param $originalCitation Citation
	 * @param $filterIds array a custom selection of filters to be applied
	 * @return Citation the checked citation. If checking
	 *  was not successful then the original citation
	 *  will be returned unchanged.
	 */
	function &checkCitation(&$request, &$originalCitation, $filterIds = array()) {
		assert(is_a($originalCitation, 'Citation'));

		// Only parse the citation if it has not been parsed before.
		// Otherwise we risk to overwrite manual user changes.
		$filteredCitation =& $originalCitation;
		if ($filteredCitation->getCitationState() < CITATION_PARSED) {
			// Parse the requested citation
			$filterCallback = array(&$this, '_instantiateParserFilters');
			$filteredCitation =& $this->_filterCitation($request, $filteredCitation, $filterCallback, CITATION_PARSED, $filterIds);
		}

		// Always re-lookup the citation even if it's been looked-up
		// before. The user asked us to re-check so there's probably
		// additional manual information in the citation fields.
		$filterCallback = array(&$this, '_instantiateLookupFilters');
		$filteredCitation =& $this->_filterCitation($request, $filteredCitation, $filterCallback, CITATION_LOOKED_UP, $filterIds);

		// Return the filtered citation.
		return $filteredCitation;
	}

	/**
	 * Claims (locks) the next raw (unparsed) citation found in the
	 * database and checks it. This method is idempotent and parallelisable.
	 * It uses an atomic locking strategy to avoid race conditions.
	 *
	 * @param $request Request
	 * @param $lockId string a globally unique id that
	 *  identifies the calling process.
	 * @return boolean true if a citation was found and checked, otherwise
	 *  false.
	 */
	function checkNextRawCitation(&$request, $lockId) {
		// NB: We implement an atomic locking strategy to make
		// sure that no two parallel background processes can claim the
		// same citation.
		$rawCitation = null;
		for ($try = 0; $try < 3; $try++) {
			// We use three statements (read, write, read) rather than
			// MySQL's UPDATE ... LIMIT ... to guarantee compatibility
			// with ANSI SQL.

			// Get the ID of the next raw citation.
			$result =& $this->retrieve(
				'SELECT citation_id
				FROM citations
				WHERE citation_state = ?
				LIMIT 1',
				CITATION_RAW
			);
			if ($result->RecordCount() > 0) {
				$nextRawCitation = $result->GetRowAssoc(false);
				$nextRawCitationId = $nextRawCitation['citation_id'];
			} else {
				// Nothing to do.
				$result->Close();
				return false;
			}
			$result->Close();
			unset($result);

			// Lock the citation.
			$this->update(
				'UPDATE citations
				SET citation_state = ?, lock_id = ?
				WHERE citation_id = ? AND citation_state = ?',
				array(CITATION_CHECKED, $lockId, $nextRawCitationId, CITATION_RAW)
			);

			// Make sure that no other concurring process
			// has claimed this citation before we could
			// lock it.
			$result =& $this->retrieve(
				'SELECT *
				FROM citations
				WHERE lock_id = ?',
				$lockId
			);
			if ($result->RecordCount() > 0) {
				$rawCitation =& $this->_fromRow($result->GetRowAssoc(false));
				break;
			}
		}
		$result->Close();
		if (!is_a($rawCitation, 'Citation')) return false;

		// Check the citation.
		$filteredCitation =& $this->checkCitation($request, $rawCitation);

		// Updating the citation will also release the lock.
		$this->updateObject($filteredCitation);

		return true;
	}

	/**
	 * Retrieve an array of citations matching a particular association id.
	 * @param $assocType int
	 * @param $assocId int
	 * @param $minCitationState int one of the CITATION_* constants,
	 *  leaving this unset will show all citation states.
	 * @param $maxCitationState int one of the CITATION_* constants,
	 *  leaving this unset will show all citation states.
	 * @param $dbResultRange DBResultRange the desired range
	 * @return DAOResultFactory containing matching Citations
	 */
	function &getObjectsByAssocId($assocType, $assocId, $minCitationState = 0, $maxCitationState = CITATION_APPROVED, $rangeInfo = null) {
		$result =& $this->retrieveRange(
			'SELECT *
			FROM citations
			WHERE assoc_type = ? AND assoc_id = ? AND citation_state >= ? AND citation_state <= ?
			ORDER BY seq, citation_id',
			array((int)$assocType, (int)$assocId, (int)$minCitationState, (int)$maxCitationState),
			$rangeInfo
		);

		$returner = new DAOResultFactory($result, $this, '_fromRow', array('id'));
		return $returner;
	}

	/**
	 * Instantiate citation filters according to
	 * the given selection rules.
	 *
	 * NB: Optional citation filters will only be included when
	 * a specific set of filter ids is being given or when the
	 * $includeOptionalFilters flag is set to true.
	 *
	 * @param $contextId integer the context for which the filters should be
	 *  retrieved (journal, conference, press, etc.)
	 * @param $parserFilters boolean whether to include parser type filters
	 * @param $lookupFilters boolean whether to include lookup type filters
	 * @param $fromFilterIds array restrict results to those with the given ids
	 * @param $includeOptionalFilters boolean
	 * @return array an array of NlmCitationSchemaFilters
	 */
	function &getCitationFilterInstances($contextId, $parserFilters = true, $lookupFilters = true, $fromFilterIds = array(), $includeOptionalFilters = false) {
		$filterDao =& DAORegistry::getDAO('FilterDAO');
		$filterList = array();

		// All citation filters have NLM output.
		$outputSample = new MetadataDescription('lib.pkp.classes.metadata.nlm.NlmCitationSchema', ASSOC_TYPE_CITATION);

		// Parser filters
		if ($parserFilters) {
			// Instantiate all configured filters that take a string
			// as input (=parser filters).
			$inputSample = 'arbitrary strings';
			$filterList =& $filterDao->getCompatibleObjects($inputSample, $outputSample, $contextId);
		}

		// Lookup filters
		if ($lookupFilters) {
			// Instantiate all configured filters that transform NLM-citation
			// schemas (=lookup filters).
			$inputSample = $outputSample;
			$filterList =& array_merge($filterList, $filterDao->getCompatibleObjects($inputSample, $outputSample, $contextId));
		}

		// Filter the result list:
		// 1) If the filter id list is empty and optional filters
		//    should not be included then return only non-optional
		//    (=default) filters.
		$finalFilterList = array();
		if (empty($fromFilterIds)) {
			if ($includeOptionalFilters) {
				// Return all filters including optional filters.
				$finalFilterList =& $filterList;
			} else {
				// Only return default filters.
				foreach($filterList as $filter) {
					if (!$filter->getData('isOptional')) $finalFilterList[] =& $filter;
					unset($filter);
				}
			}
		// 2) If specific filter ids are given then only filters in that
		//    list will be returned (even if they are non-default filters).
		} else {
			foreach($filterList as $filter) {
				if (in_array($filter->getId(), $fromFilterIds)) $finalFilterList[] =& $filter;
				unset($filter);
			}
		}

		return $finalFilterList;
	}

	/**
	 * Update an existing citation.
	 * @param $citation Citation
	 */
	function updateObject(&$citation) {
		// Update the citation and release the lock
		// on it (if one is present).
		$returner = $this->update(
			'UPDATE	citations
			SET	assoc_type = ?,
				assoc_id = ?,
				citation_state = ?,
				raw_citation = ?,
				seq = ?,
				lock_id = NULL
			WHERE	citation_id = ?',
			array(
				(integer)$citation->getAssocType(),
				(integer)$citation->getAssocId(),
				(integer)$citation->getCitationState(),
				$citation->getRawCitation(),
				(integer)$citation->getSeq(),
				(integer)$citation->getId()
			)
		);
		$this->_updateObjectMetadata($citation);
		$this->updateCitationSourceDescriptions($citation);
	}

	/**
	 * Delete a citation.
	 * @param $citation Citation
	 * @return boolean
	 */
	function deleteObject(&$citation) {
		return $this->deleteObjectById($citation->getId());
	}

	/**
	 * Delete a citation by id.
	 * @param $citationId int
	 * @return boolean
	 */
	function deleteObjectById($citationId) {
		assert(!empty($citationId));

		// Delete citation sources
		$metadataDescriptionDao =& DAORegistry::getDAO('MetadataDescriptionDAO');
		$metadataDescriptionDao->deleteObjectsByAssocId(ASSOC_TYPE_CITATION, $citationId);

		// Delete citation
		$params = array((int)$citationId);
		$this->update('DELETE FROM citation_settings WHERE citation_id = ?', $params);
		return $this->update('DELETE FROM citations WHERE citation_id = ?', $params);
	}

	/**
	 * Delete all citations matching a particular association id.
	 * @param $assocType int
	 * @param $assocId int
	 * @return boolean
	 */
	function deleteObjectsByAssocId($assocType, $assocId) {
		$citations =& $this->getObjectsByAssocId($assocType, $assocId);
		while (($citation =& $citations->next())) {
			$this->deleteObjectById($citation->getId());
			unset($citation);
		}
		return true;
	}

	/**
	 * Update the source descriptions of an existing citation.
	 *
	 * @param $citation Citation
	 */
	function updateCitationSourceDescriptions(&$citation) {
		$metadataDescriptionDao =& DAORegistry::getDAO('MetadataDescriptionDAO');

		// Clear all existing citation sources first
		$citationId = $citation->getId();
		assert(!empty($citationId));
		$metadataDescriptionDao->deleteObjectsByAssocId(ASSOC_TYPE_CITATION, $citationId);

		// Now add the new citation sources
		foreach ($citation->getSourceDescriptions() as $sourceDescription) {
			// Make sure that this source description is correctly associated
			// with the citation so that we can recover it later.
			assert($sourceDescription->getAssocType() == ASSOC_TYPE_CITATION);
			$sourceDescription->setAssocId($citationId);
			$metadataDescriptionDao->insertObject($sourceDescription);
		}
	}

	/**
	 * Instantiates the citation output format filter currently
	 * configured for the context.
	 * @param $context object journal, press or conference
	 * @return NlmCitationSchemaCitationOutputFormatFilter
	 */
	function &instantiateCitationOutputFilter(&$context) {
		// The filter is stateless so we can instantiate
		// it once for all requests.
		static $citationOutputFilter = null;
		if (is_null($citationOutputFilter)) {
			// Retrieve the currently selected citation output
			// filter from the database.
			$citationOutputFilterId = $context->getSetting('metaCitationOutputFilterId');
			$filterDao =& DAORegistry::getDAO('FilterDAO');
			$citationOutputFilter = $filterDao->getObjectById($citationOutputFilterId);
			assert(is_a($citationOutputFilter, 'NlmCitationSchemaCitationOutputFormatFilter'));
		}

		return $citationOutputFilter;
	}

	//
	// Protected helper methods
	//
	/**
	 * Get the id of the last inserted citation.
	 * @return int
	 */
	function getInsertId() {
		return parent::getInsertId('citations', 'citation_id');
	}


	//
	// Private helper methods
	//
	/**
	 * Construct a new citation object.
	 * @return Citation
	 */
	function &_newDataObject() {
		$citation = new Citation();
		return $citation;
	}

	/**
	 * Internal function to return a citation object from a
	 * row.
	 * @param $row array
	 * @return Citation
	 */
	function &_fromRow(&$row) {
		$citation =& $this->_newDataObject();
		$citation->setId((integer)$row['citation_id']);
		$citation->setAssocType((integer)$row['assoc_type']);
		$citation->setAssocId((integer)$row['assoc_id']);
		$citation->setCitationState($row['citation_state']);
		$citation->setRawCitation($row['raw_citation']);
		$citation->setSeq((integer)$row['seq']);

		$this->getDataObjectSettings('citation_settings', 'citation_id', $row['citation_id'], $citation);

		// Add citation source descriptions
		$sourceDescriptions =& $this->_getCitationSourceDescriptions($citation->getId());
		while ($sourceDescription =& $sourceDescriptions->next()) {
			$citation->addSourceDescription($sourceDescription);
		}

		return $citation;
	}

	/**
	 * Update the citation meta-data
	 * @param $citation Citation
	 */
	function _updateObjectMetadata(&$citation) {
		// Persist citation meta-data
		$this->updateDataObjectSettings('citation_settings', $citation,
				array('citation_id' => $citation->getId()));
	}

	/**
	 * Get the source descriptions of an existing citation.
	 *
	 * @param $citationId integer
	 * @return array an array of MetadataDescriptions
	 */
	function _getCitationSourceDescriptions($citationId) {
		$metadataDescriptionDao =& DAORegistry::getDAO('MetadataDescriptionDAO');
		$sourceDescriptions =& $metadataDescriptionDao->getObjectsByAssocId(ASSOC_TYPE_CITATION, $citationId);
		return $sourceDescriptions;
	}

	/**
	 * Instantiates filters that can parse a citation.
	 * @param $citation Citation
	 * @param $metadataDescription MetadataDescription
	 * @param $contextId integer
	 * @param $fromFilterIds array restrict results to those with the given ids
	 * @return array everything needed to define the transformation:
	 *  - the display name of the transformation
	 *  - the input/output type definition
	 *  - input data
	 *  - a filter list
	 */
	function &_instantiateParserFilters(&$citation, &$metadataDescription, $contextId, $fromFilterIds) {
		$displayName = 'Citation Parser Filters';

		// Parsing takes a raw citation and transforms it
		// into a array of meta-data descriptions.
		$transformation = array(
			'primitive::string',
			'metadata::lib.pkp.classes.metadata.nlm.NlmCitationSchema(CITATION)[]'
		);

		// Extract the raw citation string from the citation
		$inputData = $citation->getRawCitation();

		// Instantiate parser filters.
		$filterList =& $this->getCitationFilterInstances($contextId, true, false, $fromFilterIds);

		$transformationDefinition = compact('displayName', 'transformation', 'inputData', 'filterList');
		return $transformationDefinition;
	}

	/**
	 * Instantiates filters that can validate and amend citations
	 * with information from external data sources.
	 * @param $citation Citation
	 * @param $metadataDescription MetadataDescription
	 * @param $contextId integer
	 * @param $fromFilterIds array restrict results to those with the given ids
	 * @return array everything needed to define the transformation:
	 *  - the display name of the transformation
	 *  - the input/output type definition
	 *  - input data
	 *  - a filter list
	 */
	function &_instantiateLookupFilters(&$citation, &$metadataDescription, $contextId, $fromFilterIds) {
		$displayName = 'Citation Parser Filters';

		// Lookup takes a single meta-data description and
		// checks it against several lookup-sources resulting
		// in an array of meta-data descriptions.
		$transformation = array(
			'metadata::lib.pkp.classes.metadata.nlm.NlmCitationSchema(CITATION)',
			'metadata::lib.pkp.classes.metadata.nlm.NlmCitationSchema(CITATION)[]'
		);

		// Define the input for this transformation.
		$inputData =& $metadataDescription;

		// Instantiate lookup filters.
		$filterList =& $this->getCitationFilterInstances($contextId, false, true, $fromFilterIds);

		$transformationDefinition = compact('displayName', 'transformation', 'inputData', 'filterList');
		return $transformationDefinition;
	}

	/**
	 * Call the callback to filter the citation. If errors occur
	 * they'll be added to the citation form.
	 * @param $request Request
	 * @param $citation Citation
	 * @param $filterCallback callable
	 * @param $citationStateAfterFiltering integer the state the citation will
	 *  be set to after the filter was executed.
	 * @param $fromFilterIds only use filters with the given ids
	 * @return Citation the filtered citation or null if an error occurred
	 */
	function &_filterCitation(&$request, &$citation, &$filterCallback, $citationStateAfterFiltering, $fromFilterIds = array()) {
		// Get the context.
		$router =& $request->getRouter();
		$context =& $router->getContext($request);
		assert(is_object($context));

		// Make sure that the citation implements the
		// meta-data schema. (We currently only support
		// NLM citation.)
		$supportedMetadataSchemas =& $citation->getSupportedMetadataSchemas();
		assert(count($supportedMetadataSchemas) == 1);
		$metadataSchema =& $supportedMetadataSchemas[0];
		assert(is_a($metadataSchema, 'NlmCitationSchema'));

		// Extract the meta-data description from the citation.
		$originalDescription =& $citation->extractMetadata($metadataSchema);

		// Let the callback configure the transformation.
		$transformationDefinition = call_user_func_array($filterCallback, array(&$citation, &$originalDescription, $context->getId(), $fromFilterIds));
		if (empty($transformationDefinition['filterList'])) {
			// We didn't find any applicable filter.
			$filteredCitation = $citationMultiplexer = $citationFilterNet = null;
		} else {
			// Get the input into the transformation.
			$muxInputData =& $transformationDefinition['inputData'];

			// Instantiate the citation multiplexer filter.
			import('lib.pkp.classes.filter.GenericMultiplexerFilter');
			$citationMultiplexer = new GenericMultiplexerFilter(
					$transformationDefinition['displayName'], $transformationDefinition['transformation']);

			// Don't fail just because one of the web services
			// fails. They are much too unstable to rely on them.
			$citationMultiplexer->setTolerateFailures(true);

			// Add sub-filters to the multiplexer.
			$nullVar = null;
			foreach($transformationDefinition['filterList'] as $citationFilter) {
				if ($citationFilter->supports($muxInputData, $nullVar)) {
					$citationMultiplexer->addFilter($citationFilter);
					unset($citationFilter);
				}
			}

			// Instantiate the citation de-multiplexer filter
			import('lib.pkp.classes.citation.NlmCitationDemultiplexerFilter');
			$citationDemultiplexer = new NlmCitationDemultiplexerFilter();
			$citationDemultiplexer->setOriginalDescription($originalDescription);
			$citationDemultiplexer->setOriginalRawCitation($citation->getRawCitation());
			$citationDemultiplexer->setCitationOutputFilter($this->instantiateCitationOutputFilter($context));

			// Combine multiplexer and de-multiplexer to form the
			// final citation filter network.
			$sequencerTransformation = array(
				$transformationDefinition['transformation'][0], // The multiplexer input type
				'class::lib.pkp.classes.citation.Citation'
			);
			import('lib.pkp.classes.filter.GenericSequencerFilter');
			$citationFilterNet = new GenericSequencerFilter('Citation Filter Network', $sequencerTransformation);
			$citationFilterNet->addFilter($citationMultiplexer);
			$citationFilterNet->addFilter($citationDemultiplexer);

			// Send the input through the citation filter network.
			$filteredCitation =& $citationFilterNet->execute($muxInputData);
		}

		if (is_null($filteredCitation)) {
			// Return the original citation if the filters
			// did not produce any results and add an error message.
			$filteredCitation =& $citation;
			if (!empty($transformationDefinition['filterList'])) {
				$filteredCitation->addError(Locale::translate('submission.citations.filter.noResultFromFilterError'));
			}
		} else {
			// Copy data from the original citation to the filtered citation.
			$filteredCitation->setId($citation->getId());
			$filteredCitation->setSeq($citation->getSeq());
			$filteredCitation->setRawCitation($citation->getRawCitation());
			$filteredCitation->setAssocId($citation->getAssocId());
			$filteredCitation->setAssocType($citation->getAssocType());
			foreach($citation->getErrors() as $errorMessage) {
				$filteredCitation->addError($errorMessage);
			}
			foreach($citation->getSourceDescriptions() as $sourceDescription) {
				$filteredCitation->addSourceDescription($sourceDescription);
				unset($sourceDescription);
			}
		}

		// Set the target citation state.
		$filteredCitation->setCitationState($citationStateAfterFiltering);

		if (is_a($citationMultiplexer, 'CompositeFilter')) {
			// Retrieve the results of intermediate filters and add
			// them to the citation for inspection by the end user.
			$lastOutput =& $citationMultiplexer->getLastOutput();
			if (is_array($lastOutput)) {
				foreach($lastOutput as $sourceDescription) {
					$filteredCitation->addSourceDescription($sourceDescription);
					unset($sourceDescription);
				}
			}
		}

		if (is_a($citationFilterNet, 'CompositeFilter')) {
			// Add filtering errors (if any) to the citation's error list.
			foreach($citationFilterNet->getErrors() as $filterError) {
				$filteredCitation->addError($filterError);
			}
		}

		return $filteredCitation;
	}
}

?>

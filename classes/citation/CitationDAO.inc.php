<?php

/**
 * @file classes/citation/CitationDAO.inc.php
 *
 * Copyright (c) 2014-2017 Simon Fraser University
 * Copyright (c) 2000-2017 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class CitationDAO
 * @ingroup citation
 * @see Citation
 *
 * @brief Operations for retrieving and modifying Citation objects
 */


// FIXME: We currently have direct dependencies on specific filter groups.
// We have to make this configurable if we want to support different meta-data
// standards in the citation assistant (e.g. MODS).
define('CITATION_PARSER_FILTER_GROUP', 'plaintext=>nlm30-element-citation');
define('CITATION_LOOKUP_FILTER_GROUP', 'nlm30-element-citation=>nlm30-element-citation');

import('lib.pkp.classes.citation.Citation');

class CitationDAO extends DAO {

	/**
	 * Insert a new citation.
	 * @param $citation Citation
	 * @return integer the new citation id
	 */
	function insertObject(&$citation) {
		$seq = $citation->getSequence();
		if (!(is_numeric($seq) && $seq > 0)) {
			// Find the latest sequence number
			$result = $this->retrieve(
				'SELECT MAX(seq) AS lastseq FROM citations
				 WHERE assoc_type = ? AND assoc_id = ?',
				array(
					(integer)$citation->getAssocType(),
					(integer)$citation->getAssocId(),
				)
			);

			if ($result->RecordCount() != 0) {
				$row = $result->GetRowAssoc(false);
				$seq = $row['lastseq'] + 1;
			} else {
				$seq = 1;
			}
			$citation->setSequence($seq);
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
		$result = $this->retrieve(
			'SELECT * FROM citations WHERE citation_id = ?', $citationId
		);

		$citation = null;
		if ($result->RecordCount() != 0) {
			$citation = $this->_fromRow($result->GetRowAssoc(false));
		}
		$result->Close();

		return $citation;
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
	function checkNextRawCitation($request, $lockId) {
		// NB: We implement an atomic locking strategy to make
		// sure that no two parallel background processes can claim the
		// same citation.
		$rawCitation = null;
		for ($try = 0; $try < 3; $try++) {
			// We use three statements (read, write, read) rather than
			// MySQL's UPDATE ... LIMIT ... to guarantee compatibility
			// with ANSI SQL.

			// Get the ID of the next raw citation.
			$result = $this->retrieve(
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
			$result = $this->retrieve(
				'SELECT *
				FROM citations
				WHERE lock_id = ?',
				$lockId
			);
			if ($result->RecordCount() > 0) {
				$rawCitation = $this->_fromRow($result->GetRowAssoc(false));
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
	function getObjectsByAssocId($assocType, $assocId, $minCitationState = 0, $maxCitationState = CITATION_APPROVED, $rangeInfo = null) {
		$result = $this->retrieveRange(
			'SELECT *
			FROM citations
			WHERE assoc_type = ? AND assoc_id = ? AND citation_state >= ? AND citation_state <= ?
			ORDER BY seq, citation_id',
			array((int)$assocType, (int)$assocId, (int)$minCitationState, (int)$maxCitationState),
			$rangeInfo
		);

		return new DAOResultFactory($result, $this, '_fromRow', array('id'));
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
	 * @param $filterGroups string|array the symbolic name(s) of the filter group(s)
	 *  to be loaded.
	 * @param $fromFilterIds array restrict results to those with the given ids
	 * @param $includeOptionalFilters boolean
	 * @return array an array of PersistableFilters
	 */
	function &getCitationFilterInstances($contextId, $filterGroups, $fromFilterIds = array(), $includeOptionalFilters = false) {
		$filterDao = DAORegistry::getDAO('FilterDAO'); /* @var $filterDao FilterDAO */
		$filterList = array();

		// Retrieve the requested filter group(s).
		if (is_scalar($filterGroups)) $filterGroups = array($filterGroups);
		foreach($filterGroups as $filterGroupSymbolic) {
			$filterList =& array_merge($filterList, $filterDao->getObjectsByGroup($filterGroupSymbolic, $contextId));
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
					if (!$filter->getData('isOptional')) $finalFilterList[] = $filter;
				}
			}
		// 2) If specific filter ids are given then only filters in that
		//    list will be returned (even if they are non-default filters).
		} else {
			foreach($filterList as $filter) {
				if (in_array($filter->getId(), $fromFilterIds)) $finalFilterList[] = $filter;
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
				(integer)$citation->getSequence(),
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
		$metadataDescriptionDao = DAORegistry::getDAO('MetadataDescriptionDAO');
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
		$citations = $this->getObjectsByAssocId($assocType, $assocId);
		while ($citation = $citations->next()) {
			$this->deleteObjectById($citation->getId());
		}
		return true;
	}

	/**
	 * Update the source descriptions of an existing citation.
	 *
	 * @param $citation Citation
	 */
	function updateCitationSourceDescriptions(&$citation) {
		$metadataDescriptionDao = DAORegistry::getDAO('MetadataDescriptionDAO');

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

	//
	// Protected helper methods
	//
	/**
	 * Get the id of the last inserted citation.
	 * @return int
	 */
	function getInsertId() {
		return parent::_getInsertId('citations', 'citation_id');
	}


	//
	// Private helper methods
	//
	/**
	 * Construct a new citation object.
	 * @return Citation
	 */
	function _newDataObject() {
		return new Citation();
	}

	/**
	 * Internal function to return a citation object from a
	 * row.
	 * @param $row array
	 * @return Citation
	 */
	function _fromRow($row) {
		$citation = $this->_newDataObject();
		$citation->setId((integer)$row['citation_id']);
		$citation->setAssocType((integer)$row['assoc_type']);
		$citation->setAssocId((integer)$row['assoc_id']);
		$citation->setCitationState($row['citation_state']);
		$citation->setRawCitation($row['raw_citation']);
		$citation->setSequence((integer)$row['seq']);

		$this->getDataObjectSettings('citation_settings', 'citation_id', $row['citation_id'], $citation);

		// Add citation source descriptions
		$sourceDescriptions = $this->_getCitationSourceDescriptions($citation->getId());
		while ($sourceDescription = $sourceDescriptions->next()) {
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
		$metadataDescriptionDao = DAORegistry::getDAO('MetadataDescriptionDAO');
		return $metadataDescriptionDao->getObjectsByAssocId(ASSOC_TYPE_CITATION, $citationId);
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
		$displayName = 'Citation Parser Filters'; // Only for internal debugging, no display to user.

		// Extract the raw citation string from the citation
		$inputData = $citation->getRawCitation();

		// Instantiate parser filters.
		$filterList =& $this->getCitationFilterInstances($contextId, CITATION_PARSER_FILTER_GROUP, $fromFilterIds);

		$transformationDefinition = compact('displayName', 'inputData', 'filterList');
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
		$displayName = 'Citation Lookup Filters'; // Only for internal debugging, no display to user.

		// Define the input for this transformation.
		$inputData =& $metadataDescription;

		// Instantiate lookup filters.
		$filterList =& $this->getCitationFilterInstances($contextId, CITATION_LOOKUP_FILTER_GROUP, $fromFilterIds);

		$transformationDefinition = compact('displayName', 'inputData', 'filterList');
		return $transformationDefinition;
	}

}

?>

<?php

/**
 * @file CitationDAO.inc.php
 *
 * Copyright (c) 2000-2010 John Willinsky
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
		$this->update(
			sprintf('INSERT INTO citations
				(assoc_type, assoc_id, citation_state, raw_citation, edited_citation, parse_score, lookup_score)
				VALUES
				(?, ?, ?, ?, ?, ?, ?)'),
			array(
				(integer)$citation->getAssocType(),
				(integer)$citation->getAssocId(),
				(integer)$citation->getCitationState(),
				$citation->getRawCitation(),
				$citation->getEditedCitation(),
				$citation->getParseScore(),
				$citation->getLookupScore()
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
		unset($result);

		return $citation;
	}

	/**
	 * Retrieve an array of citations matching a particular association id.
	 * @param $assocType int
	 * @param $assocId int
	 * @param $dbResultRange DBResultRange the desired range
	 * @return DAOResultFactory containing matching Citations
	 */
	function &getObjectsByAssocId($assocType, $assocId, $rangeInfo = null) {
		$result =& $this->retrieveRange(
			'SELECT *
			FROM citations
			WHERE assoc_type = ? AND assoc_id = ?
			ORDER BY citation_id DESC',
			array((int)$assocType, (int)$assocId),
			$rangeInfo
		);

		$returner = new DAOResultFactory($result, $this, '_fromRow', array('id'));
		return $returner;
	}

	/**
	 * Update an existing citation.
	 * @param $citation Citation
	 */
	function updateObject(&$citation) {
		$returner = $this->update(
			'UPDATE	citations
			SET	assoc_type = ?,
				assoc_id = ?,
				citation_state = ?,
				raw_citation = ?,
				edited_citation = ?,
				parse_score = ?,
				lookup_score = ?
			WHERE	citation_id = ?',
			array(
				(integer)$citation->getAssocType(),
				(integer)$citation->getAssocId(),
				(integer)$citation->getCitationState(),
				$citation->getRawCitation(),
				$citation->getEditedCitation(),
				$citation->getParseScore(),
				$citation->getLookupScore(),
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
		$citation->setId($row['citation_id']);
		$citation->setAssocType($row['assoc_type']);
		$citation->setAssocId($row['assoc_id']);
		$citation->setCitationState($row['citation_state']);
		$citation->setRawCitation($row['raw_citation']);
		$citation->setEditedCitation($row['edited_citation']);
		$citation->setParseScore($row['parse_score']);
		$citation->setLookupScore($row['lookup_score']);

		$this->getDataObjectSettings('citation_settings', 'citation_id', $row['citation_id'], $citation);

		// Add citation source descriptions
		$sourceDescriptions = $this->_getCitationSourceDescriptions($citation->getId());
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
}

?>

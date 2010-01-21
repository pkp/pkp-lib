<?php

/**
 * @file CitationDAO.inc.php
 *
 * Copyright (c) 2000-2009 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class CitationDAO
 * @ingroup citation
 * @see Citation
 *
 * @brief Operations for retrieving and modifying Citation objects
 */

//$Id$

import('citation.Citation');

class CitationDAO extends DAO {
	/**
	 * Insert a new Citation.
	 * @param $citation Citation
	 * @return integer the new citation id
	 */
	function insertCitation(&$citation) {
		$this->update(
			sprintf('INSERT INTO citations
				(citation_state, raw_citation, edited_citation, parse_score, lookup_score)
				VALUES
				(?, ?, ?, ?, ?)'),
			array(
				(integer)$citation->getCitationState(),
				$citation->getRawCitation(),
				$citation->getEditedCitation(),
				$citation->getParseScore(),
				$citation->getLookupScore()
			)
		);
		$citation->setId($this->getInsertId());
		$this->_updateCitationMetadata($citation, false);
		return $citation->getId();
	}

	/**
	 * Retrieve a citation by id.
	 * @param $citationId integer
	 * @return Citation
	 */
	function &getCitation($citationId) {
		$result =& $this->retrieve(
			'SELECT * FROM citations WHERE citation_id = ?', $citationId
		);

		$citation = null;
		if ($result->RecordCount() != 0) {
			$citation =& $this->_fromRow($result->GetRowAssoc(false));
			$this->getDataObjectSettings('citation_settings', 'citation_id', $citation->getId(), $citation);
		}

		$result->Close();
		unset($result);

		return $citation;
	}

	/**
	 * Update an existing Citation.
	 * @param $citation Citation
	 */
	function updateCitation(&$citation) {
		$returner = $this->update(
			'UPDATE	citations
			SET	citation_state = ?,
				raw_citation = ?,
				edited_citation = ?,
				parse_score = ?
				lookup_score = ?
			WHERE	citation_id = ?',
			array(
				(integer)$citation->getCitationState(),
				$citation->getRawCitation(),
				$citation->getEditedCitation(),
				$citation->getParseScore(),
				$citation->getLookupScore()
			)
		);
		$this->_updateCitationMetadata($citation);
	}

	/**
	 * Delete a Citation.
	 * @param $citation Citation
	 * @return boolean
	 */
	function deleteCitation(&$citation) {
		return $this->deleteObjectById($citation->getId());
	}

	/**
	 * Delete a Citation by ID.
	 * @param $citationId int
	 * @return boolean
	 */
	function deleteCitationById($citationId) {
		$params = array((int)$citationId);
		$this->update('DELETE FROM citation_settings WHERE citation_id = ?', $params);
		return $this->update('DELETE FROM citations WHERE citation_id = ?', $params);
	}


	//
	// Protected helper methods
	//
	/**
	 * Get the ID of the last inserted Citation.
	 * @return int
	 */
	function getInsertId() {
		return parent::getInsertId('citations', 'citation_id');
	}


	//
	// Private helper methods
	//
	/**
	 * Construct a new Citation object.
	 * @return Citation
	 */
	function &_newDataObject($metadataSchemaName, $assocType) {
		$citation = new Citation();
		return $citation;
	}

	/**
	 * Internal function to return a Citation object from a
	 * row.
	 * @param $row array
	 * @return Citation
	 */
	function &_fromRow(&$row) {
		$citation =& $this->newDataObject();
		$citation->setId($row['citation_id']);
		$citation->setCitationState($row['citation_state']);
		$citation->setRawCitation($row['raw_citation']);
		$citation->setEditedCitation($row['edited_citation']);
		$citation->setParseScore($row['parse_score']);
		$citation->setLookupScore($row['lookup_score']);

		$this->getDataObjectSettings('citation_settings', 'citation_id', $row['citation_id'], $citation);

		return $citation;
	}

	/**
	 * Update the citation meta-data
	 * @param $citation Citation
	 */
	function _updateCitationMetadata(&$citation) {
		// Persist citation meta-data
		$this->updateDataObjectSettings('citation_settings', $citation,
				array('citation_id' => $citation->getId()));
	}
}

?>

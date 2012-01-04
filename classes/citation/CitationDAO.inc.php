<?php

/**
 * @file CitationDAO.inc.php
 *
 * Copyright (c) 2000-2012 John Willinsky
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
	 * Retrieve an array of citations matching a particular association id.
	 * @param $assocType int
	 * @param $assocId int
	 * @param $dbResultRange DBResultRange the desired range
	 * @return DAOResultFactory containing matching Citations
	 */
	function &getCitationsByAssocId($assocType, $assocId, $rangeInfo = null) {
		$result =& $this->retrieveRange(
			'SELECT *
			FROM citations
			WHERE assoc_type = ? AND assoc_id = ?
			ORDER BY citation_id DESC',
			array($assocType, $assocId),
			$rangeInfo
		);

		$returner = new DAOResultFactory($result, $this, '_fromRow', array('id'));
		return $returner;
	}

	/**
	 * Update an existing Citation.
	 * @param $citation Citation
	 */
	function updateCitation(&$citation) {
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
		$this->_updateCitationMetadata($citation);
	}

	/**
	 * Delete a Citation.
	 * @param $citation Citation
	 * @return boolean
	 */
	function deleteCitation(&$citation) {
		return $this->deleteCitationById($citation->getId());
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

	/**
	 * Delete all citations matching a particular association id.
	 * @param $assocType int
	 * @param $assocId int
	 * @return boolean
	 */
	function deleteCitationsByAssocId($assocType, $assocId) {
		$citations =& $this->getCitationsByAssocId($assocType, $assocId);
		while (($citation =& $citations->next())) {
			$this->deleteCitationById($citation->getId());
			unset($citation);
		}
		return true;
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
	function &_newDataObject() {
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

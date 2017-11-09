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

}

?>

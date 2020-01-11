<?php

/**
 * @file classes/citation/CitationDAO.inc.php
 *
 * Copyright (c) 2014-2019 Simon Fraser University
 * Copyright (c) 2000-2019 John Willinsky
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
	function insertObject($citation) {
		$seq = $citation->getSequence();
		if (!(is_numeric($seq) && $seq > 0)) {
			// Find the latest sequence number
			$result = $this->retrieve(
				'SELECT MAX(seq) AS lastseq FROM citations
				 WHERE publication_id = ?',
				array(
					(integer)$citation->getData('publicationId'),
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
				(publication_id, raw_citation, seq)
				VALUES
				(?, ?, ?)'),
			array(
				(integer)$citation->getData('publicationId'),
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
	function getById($citationId) {
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
	 * Import citations from a raw citation list of the particular publication.
	 * @param $publicationId int
	 * @param $rawCitationList string
	 */
	function importCitations($publicationId, $rawCitationList) {
		assert(is_numeric($publicationId));
		$publicationId = (int) $publicationId;

		// Remove existing citations.
		$this->deleteByPublicationId($publicationId);

		// Tokenize raw citations
		import('lib.pkp.classes.citation.CitationListTokenizerFilter');
		$citationTokenizer = new CitationListTokenizerFilter();
		$citationStrings = $citationTokenizer->execute($rawCitationList);

		// Instantiate and persist citations
		if (is_array($citationStrings)) foreach($citationStrings as $seq => $citationString) {
			$citation = new Citation($citationString);
			// Set the publication
			$citation->setData('publicationId', $publicationId);
			// Set the counter
			$citation->setSequence($seq+1);
			$this->insertObject($citation);
		}
	}

	/**
	 * Retrieve an array of citations matching a particular publication id.
	 * @param $publicationId int
	 * @param $dbResultRange DBResultRange the desired range
	 * @return DAOResultFactory containing matching Citations
	 */
	function getByPublicationId($publicationId, $rangeInfo = null) {
		$result = $this->retrieveRange(
			'SELECT *
			FROM citations
			WHERE publication_id = ?
			ORDER BY seq, citation_id',
			array((int)$publicationId),
			$rangeInfo
		);
		return new DAOResultFactory($result, $this, '_fromRow', array('id'));
	}

	/**
	 * Get a list of additional fields that do not have
	 * dedicated accessors.
	 * @return array
	 */
	function getAdditionalFieldNames() {
		$additionalFields = parent::getAdditionalFieldNames();
		return $additionalFields;
	}

	/**
	 * Update an existing citation.
	 * @param $citation Citation
	 */
	function updateObject($citation) {
		$returner = $this->update(
			'UPDATE	citations
			SET	publication_id = ?,
				raw_citation = ?,
				seq = ?
			WHERE	citation_id = ?',
			array(
				(integer)$citation->getData('publicationId'),
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
	function deleteObject($citation) {
		return $this->deleteById($citation->getId());
	}

	/**
	 * Delete a citation by id.
	 * @param $citationId int
	 * @return boolean
	 */
	function deleteById($citationId) {
		assert(!empty($citationId));

		// Delete citation
		$params = array((int)$citationId);
		$this->update('DELETE FROM citation_settings WHERE citation_id = ?', $params);
		return $this->update('DELETE FROM citations WHERE citation_id = ?', $params);
	}

	/**
	 * Delete all citations matching a particular publication id.
	 * @param $publicationId int
	 * @return boolean
	 */
	function deleteByPublicationId($publicationId) {
		$citations = $this->getByPublicationId($publicationId);
		while ($citation = $citations->next()) {
			$this->deleteById($citation->getId());
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
		$citation->setData('publicationId', (integer)$row['publication_id']);
		$citation->setRawCitation($row['raw_citation']);
		$citation->setSequence((integer)$row['seq']);

		$this->getDataObjectSettings('citation_settings', 'citation_id', $row['citation_id'], $citation);

		return $citation;
	}

	/**
	 * Update the citation meta-data
	 * @param $citation Citation
	 */
	function _updateObjectMetadata($citation) {
		// Persist citation meta-data
		$this->updateDataObjectSettings('citation_settings', $citation,
				array('citation_id' => $citation->getId()));
	}

}



<?php

/**
 * @file classes/citation/CitationDAO.inc.php
 *
 * Copyright (c) 2014-2018 Simon Fraser University
 * Copyright (c) 2000-2018 John Willinsky
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
				 WHERE submission_id = ?',
				array(
					(integer)$citation->getSubmissionId(),
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
				(submission_id, raw_citation, seq)
				VALUES
				(?, ?, ?)'),
			array(
				(integer)$citation->getSubmissionId(),
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
	 * Import citations from a raw citation list of the particular submission.
	 * @param $submissionId int
	 * @param $rawCitationList string
	 */
	function importCitations($submissionId, $rawCitationList) {
		assert(is_numeric($submissionId));
		$submissionId = (int) $submissionId;

		// Remove existing citations.
		$this->deleteBySubmissionId($submissionId);

		// Tokenize raw citations
		import('lib.pkp.classes.citation.CitationListTokenizerFilter');
		$citationTokenizer = new CitationListTokenizerFilter();
		$citationStrings = $citationTokenizer->execute($rawCitationList);

		// Instantiate and persist citations
		if (is_array($citationStrings)) foreach($citationStrings as $seq => $citationString) {
			$citation = new Citation($citationString);
			// Set the submission
			$citation->setSubmissionId($submissionId);
			// Set the counter
			$citation->setSequence($seq+1);
			$this->insertObject($citation);
		}
	}

	/**
	 * Retrieve an array of citations matching a particular submission id.
	 * @param $submissionId int
	 * @param $dbResultRange DBResultRange the desired range
	 * @return DAOResultFactory containing matching Citations
	 */
	function getBySubmissionId($submissionId, $rangeInfo = null) {
		$result = $this->retrieveRange(
			'SELECT *
			FROM citations
			WHERE submission_id = ?
			ORDER BY seq, citation_id',
			array((int)$submissionId),
			$rangeInfo
		);
		return new DAOResultFactory($result, $this, '_fromRow', array('id'));
	}

	/**
	 * Update an existing citation.
	 * @param $citation Citation
	 */
	function updateObject($citation) {
		$returner = $this->update(
			'UPDATE	citations
			SET	submission_id = ?,
				raw_citation = ?,
				seq = ?,
			WHERE	citation_id = ?',
			array(
				(integer)$citation->getSubmissionId(),
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
	 * Delete all citations matching a particular submission id.
	 * @param $submissionId int
	 * @return boolean
	 */
	function deleteBySubmissionId($submissionId) {
		$citations = $this->getBySubmissionId($submissionId);
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
		$citation->setSubmissionId((integer)$row['submission_id']);
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

?>

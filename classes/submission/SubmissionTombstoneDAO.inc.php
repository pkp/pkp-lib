<?php

/**
 * @file classes/submission/SubmissionTombstoneDAO.inc.php
 *
 * Copyright (c) 2003-2012 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class SubmissionTombstoneDAO
 * @ingroup submission
 * @see SubmissionTombstoneDAO
 *
 * @brief Base class for retrieving and modifying SubmissionTombstone objects.
 */

class SubmissionTombstoneDAO extends DAO {
	/**
	 * Constructor.
	 */
	function SubmissionTombstoneDAO() {
		parent::DAO();
	}

	/**
	 * Retrieve SubmissionTombstone by id.
	 * @param $tombstoneId int
	 * @param $contextId int
	 * @param $contextColumnName string The name of the column that will
	 * be used to search the passed context id.
	 * @return SubmissionTombstone object
	 */
	function &getById($tombstoneId, $contextId = null, $contextColumnName = null) {
		$params = array((int) $tombstoneId);
		if ($contextId !== null) $params[] = (int) $contextId;
		$result =& $this->retrieve(
			'SELECT * FROM submission_tombstones WHERE tombstone_id = ?'
			. ($contextId !== null ? ' AND ' . $contextColumnName . ' = ?' : ''),
			$params
		);

		$submissionTombstone =& $this->_fromRow($result->GetRowAssoc(false));

		$result->Close();
		unset($result);

		return $submissionTombstone;
	}

	/**
	 * Retrieve SubmissionTombstone by submission id.
	 * @param $submissionId int
	 * @return SubmissionTombstone object
	 */
	function &getBySubmissionId($submissionId) {
		$result =& $this->retrieve(
			'SELECT * FROM submission_tombstones WHERE submission_id = ?', (int) $submissionId
		);

		$submissionTombstone =& $this->_fromRow($result->GetRowAssoc(false));

		$result->Close();
		unset($result);

		return $submissionTombstone;
	}

	/**
	 * Creates and returns a submission tombstone object from a row.
	 * @param $row array
	 * @return SubmissionTombstone object
	 */
	function &_fromRow($row) {
		$submissionTombstone = $this->newDataObject();
		$submissionTombstone->setId($row['tombstone_id']);
		$submissionTombstone->setSubmissionId($row['submission_id']);
		$submissionTombstone->setDateDeleted($this->datetimeFromDB($row['date_deleted']));
		$submissionTombstone->setSetSpec($row['set_spec']);
		$submissionTombstone->setSetName($row['set_name']);
		$submissionTombstone->setOAIIdentifier($row['oai_identifier']);

		return $submissionTombstone;
	}

	/**
	 * Delete SubmissionTombstone by tombstone id.
	 * @param $tombstoneId int
	 * @param $contextId int
	 * @param $contextColumnName string The name of the column that will
	 * be used to search the passed context id.
	 * @return boolean
	 */
	function deleteById($tombstoneId, $contextId = null, $contextColumnName = null) {
		$params = array((int) $tombstoneId);
		if (isset($contextId)) $params[] = (int) $contextId;

		$this->update('DELETE FROM submission_tombstones WHERE tombstone_id = ?' .
			(isset($contextId) ? ' AND ' . $contextColumnName . ' = ?' : ''),
			$params
		);
		if ($this->getAffectedRows()) {
			$submissionTombstoneSettingsDao =& DAORegistry::getDAO('SubmissionTombstoneSettingsDAO');
			return $submissionTombstoneSettingsDao->deleteSettings($tombstoneId);
		}
		return false;
	}

	/**
	 * Delete SubmissionTombstone by submission id.
	 * @param $submissionId int
	 * @return boolean
	 */
	function deleteBySubmissionId($submissionId) {
		$submissionTombstone =& $this->getBySubmissionId($submissionId);
		return $this->deleteById($submissionTombstone->getId());
	}

	/**
	 * Get the ID of the last inserted article tombstone.
	 * @return int
	 */
	function getInsertTombstoneId() {
		return $this->getInsertId('submission_tombstones', 'tombstone_id');
	}

	/**
	 * Retrieve all sets for submission tombstones of a context.
	 * @param $contextId int
	 * @param $contextColumnName string The name of the column that will
	 * be used to search the passed context id.
	 * @return array('setSpec' => setName)
	 */
	function &getSets($contextId, $contextColumnName) {
		$returner = array();

		$result =& $this->retrieve(
			'SELECT DISTINCT set_spec, set_name FROM submission_tombstones WHERE ' . $contextColumnName . ' = ?',
			(int) $contextId
		);

		while (!$result->EOF) {
			$returner[$result->fields[0]] = $result->fields[1];
			$result->moveNext();
		}

		$result->Close();
		unset($result);

		return $returner;
	}
}

?>
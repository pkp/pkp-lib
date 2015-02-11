<?php

/**
 * @file classes/submission/SubmissionFileQueryDAO.inc.php
 *
 * Copyright (c) 2014 Simon Fraser University Library
 * Copyright (c) 2000-2014 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class SubmissionFileQueryDAO
 * @ingroup submission
 * @see SubmissionFileQuery
 *
 * @brief Operations for retrieving and modifying SubmissionFileQuery objects.
 */


import('lib.pkp.classes.submission.SubmissionFileQuery');

class SubmissionFileQueryDAO extends DAO {
	/**
	 * Constructor
	 */
	function SubmissionFileQueryDAO() {
		parent::DAO();
	}

	/**
	 * Retrieve a submission file query by ID.
	 * @param $queryId int SubmissionFileQuery ID
	 * @param $submissionId int optional
	 * @return SubmissionFileQuery
	 */
	function getById($queryId, $submissionId = null) {
		$params = array((int) $queryId);
		if ($submissionId !== null) $params[] = (int) $submissionId;
		$result = $this->retrieve(
			'SELECT sfq.*
			FROM	submission_file_queries sfq
			WHERE	sfq.query_id = ?'
				. ($submissionId !== null?' AND sfq.submission_id = ?':''),
			$params
		);

		$returner = null;
		if ($result->RecordCount() != 0) {
			$returner = $this->_fromRow($result->GetRowAssoc(false));
		}

		$result->Close();
		return $returner;
	}

	/**
	 * Retrieve all queries for a submission.
	 * @param $submissionId int
	 * @param $stageId int
	 * @param $onlyParents boolean
	 * @return array SubmissionFileQuery
	 */
	function getBySubmissionId($submissionId, $stageId = null, $onlyParents = false) {
		$queries = array();
		$params = array((int) $submissionId);
		if ($stageId) $params[] = $stageId;

		$result = $this->retrieve(
			'SELECT	sfq.*
			FROM	submission_file_queries sfq
			WHERE	sfq.submission_id = ? '
			. ($stageId !== null?' AND sfq.stage_id = ?':'')
			. ($onlyParents?' AND sfq.parent_query_id = 0':''),
			$params
		);

		while (!$result->EOF) {
			$row = $result->getRowAssoc(false);
			$queries[$row['query_id']] = $this->_fromRow($row);
			$result->MoveNext();
		}

		$result->Close();
		return $queries;
	}

	/**
	 * Retrieves the replies to a query, ordered by increasing post date.
	 * @param $queryId int
	 * @param $submissionId int optional
	 * @return array SubmissionFileQuery
	 */
	function getRepliesToQuery($queryId, $submissionId = null) {
		$queries = array();
		$params = array((int) $queryId);
		if ($submissionId) $params[] = $submissionId;

		$result = $this->retrieve(
			'SELECT	sfq.*
			FROM	submission_file_queries sfq
			WHERE	sfq.parent_query_id = ? '
			. ($submissionId !== null?' AND sfq.submission_id = ?':'')
			. ' ORDER BY date_posted ASC',
			$params
		);

		while (!$result->EOF) {
			$row = $result->getRowAssoc(false);
			$queries[] = $this->_fromRow($row);
			$result->MoveNext();
		}

		$result->Close();
		return $queries;
	}

	/**
	 * Update the localized data for this object
	 * @param $query object
	 */
	function updateLocaleFields($query) {
		$this->updateDataObjectSettings(
			'submission_file_query_settings',
			$query,
			array(
				'query_id' => $query->getId()
			)
		);
	}

	/**
	 * Internal function to return a submission file query object from a row.
	 * @param $row array
	 * @return SubmissionFileQuery
	 */
	function _fromRow($row) {
		$query = $this->newDataObject();
		$query->setId($row['query_id']);
		$query->setSubmissionId($row['submission_id']);
		$query->setStageId($row['stage_id']);
		$query->setParentQueryId($row['parent_query_id']);
		$query->setRevision($row['revision']);
		$query->setUserId($row['user_id']);
		$query->setDatePosted($row['date_posted']);
		$query->setDateModified($row['date_modified']);
		$query->setThreadClosed($row['thread_closed']);

		$this->getDataObjectSettings('submission_file_query_settings', 'query_id', $row['query_id'], $query);

		HookRegistry::call('SubmissionFileQueryDAO::_fromRow', array(&$query, &$row));
		return $query;
	}

	/**
	 * Get a new data object
	 * @return DataObject
	 */
	function newDataObject() {
		return new SubmissionFileQuery();
	}

	/**
	 * Get field names for which data is localized.
	 * @return array
	 */
	function getLocaleFieldNames() {
		return array('comment', 'subject');
	}

	/**
	 * Insert a new SubmissionFileQuery.
	 * @param $query SubmissionFileQuery
	 */
	function insertObject($query) {
		$this->update(
				'INSERT INTO submission_file_queries
				(submission_id, stage_id, parent_query_id, revision, user_id, date_posted, date_modified, thread_closed)
				VALUES
				(?, ?, ?, ?, ?, ?, ?, ?)',
				array(
					(int) $query->getSubmissionId(),
					$query->getStageId(),
					$query->getParentQueryId(),
					$query->getRevision() . '',
					$query->getUserId(),
					$query->getDatePosted(),
					$query->getDateModified(),
					(int) $query->getThreadClosed(),
				)
		);

		$query->setId($this->getInsertId());
		$this->updateLocaleFields($query);

		return $query->getId();
	}

	/**
	 * Adds a participant to a query.
	 * @param int $queryId
	 * @param int $userId
	 */
	function insertParticipant($queryId, $userId) {
		$this->update(
				'INSERT INTO submission_file_query_participants
				(query_id, user_id)
				VALUES
				(?, ?)',
				array(
					(int) $queryId,
					(int) $userId,
				)
		);

		return true;
	}

	/**
	 * Removes a participant from a query.
	 * @param int $queryId
	 * @param int $userId
	 */
	function removeParticipant($queryId, $userId) {
		$params = array((int) $queryId, (int) $userid);
		$returner = $this->update(
			'DELETE FROM submission_file_query_participants WHERE query_id = ? AND user_id = ?',
			$params
		);

		return $returner;
	}

	/**
	 * Removes all participants from a query.
	 * @param int $queryId
	 */
	function removeAllParticipants($queryId) {
		$params = array((int) $queryId);
		$returner = $this->update(
			'DELETE FROM submission_file_query_participants WHERE query_id',
			$params
		);

		return $returner;
	}

	/**
	 * Update an existing SubmissionFileQuery.
	 * @param $query SubmissionFileQuery
	 */
	function updateObject($query) {

		$returner = $this->update(
				'UPDATE	submission_file_queries
				SET	stage_id = ?,
					parent_query_id = ?,
					revision = ?,
					user_id = ?,
					date_posted = ?,
					date_modified = ?,
					thread_closed = ?
				WHERE	query_id = ?',
				array(
						$query->getStageId(),
						$query->getParentQueryId(),
						$query->getRevision() . '',
						(int) $query->getUserId(),
						$query->getDatePosted(),
						$query->getDateModified(),
						(int) $query->getThreadClosed(),
						(int) $query->getId()
				)
		);
		$this->updateLocaleFields($query);
		return $returner;
	}

	/**
	 * Delete a submission file query.
	 * @param $query SubmissionFileQuery
	 */
	function deleteObject($query) {
		return $this->deleteById($query->getId());
	}

	/**
	 * Delete a submission file query by ID.
	 * @param $queryId int
	 * @param $submissionId int optional
	 */
	function deleteById($queryId, $submissionId = null) {
		$params = array((int) $queryId);
		if ($submissionId) $params[] = (int) $submissionId;
		$returner = $this->update(
			'DELETE FROM submission_file_queries WHERE query_id = ?' .
			($submissionId?' AND submission_id = ?':''),
			$params
		);
		if ($returner) $this->update('DELETE FROM submission_file_query_settings WHERE query_id = ?', array((int) $queryId));

		return $returner;
	}

	/**
	 * Get the ID of the last inserted submission file query.
	 * @return int
	 */
	function getInsertId() {
		return $this->_getInsertId('submission_file_queries', 'query_id');
	}

	/**
	 * Delete queries by submission.
	 * @param $submissionId int
	 */
	function deleteBySubmissionId($submissionId) {
		$queries = $this->getBySubmissionId($submissionId);
		foreach ($queries as $query) {
			$this->deleteObject($query);
		}
	}
}

?>

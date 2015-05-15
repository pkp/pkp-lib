<?php

/**
 * @file classes/query/QueryDAO.inc.php
 *
 * Copyright (c) 2015 Simon Fraser University Library
 * Copyright (c) 2000-2015 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class QueryDAO
 * @ingroup query
 * @see Query
 *
 * @brief Operations for retrieving and modifying Query objects.
 */


import('lib.pkp.classes.query.Query');

class QueryDAO extends DAO {
	/**
	 * Constructor
	 */
	function QueryDAO() {
		parent::DAO();
	}

	/**
	 * Retrieve a submission file query by ID.
	 * @param $queryId int Query ID
	 * @param $submissionId int optional
	 * @return Query
	 */
	function getById($queryId, $submissionId = null) {
		$params = array((int) $queryId);
		if ($submissionId) $params[] = (int) $submissionId;
		$result = $this->retrieve(
			'SELECT sfq.*
			FROM	queries sfq
			WHERE	sfq.query_id = ?'
				. ($submissionId?' AND sfq.submission_id = ?':''),
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
	 * @return array Query
	 */
	function getBySubmissionId($submissionId, $stageId = null, $onlyParents = false) {
		$params = array((int) $submissionId);
		if ($stageId) $params[] = $stageId;
		return new DAOResultFactory(
			$this->retrieve(
				'SELECT	sfq.*
				FROM	queries sfq
				WHERE	sfq.submission_id = ? '
				. ($stageId?' AND sfq.stage_id = ?':'')
				. ($onlyParents?' AND sfq.parent_query_id = 0':''),
				$params
			),
			$this, '_fromRow'
		);
	}

	/**
	 * Retrieves the replies to a query, ordered by increasing post date.
	 * @param $queryId int
	 * @param $submissionId int optional
	 * @return array Query
	 */
	function getRepliesToQuery($queryId, $submissionId = null) {
		$params = array((int) $queryId);
		if ($submissionId) $params[] = (int) $submissionId;
		return new DAOResultFactory(
			$this->retrieve(
				'SELECT	sfq.*
				FROM	queries sfq
				WHERE	sfq.parent_query_id = ? '
				. ($submissionId?' AND sfq.submission_id = ?':'')
				. ' ORDER BY date_posted ASC',
				$params
			),
			$this, '_fromRow'
		);
	}

	/**
	 * Update the localized data for this object
	 * @param $query object
	 */
	function updateLocaleFields($query) {
		$this->updateDataObjectSettings(
			'query_settings',
			$query,
			array('query_id' => $query->getId())
		);
	}

	/**
	 * Internal function to return a submission file query object from a row.
	 * @param $row array
	 * @return Query
	 */
	function _fromRow($row) {
		$query = $this->newDataObject();
		$query->setId($row['query_id']);
		$query->setSubmissionId($row['submission_id']);
		$query->setStageId($row['stage_id']);
		$query->setParentQueryId($row['parent_query_id']);
		$query->setUserId($row['user_id']);
		$query->setDatePosted($row['date_posted']);
		$query->setDateModified($row['date_modified']);
		$query->setThreadClosed($row['thread_closed']);

		$this->getDataObjectSettings('query_settings', 'query_id', $row['query_id'], $query);

		HookRegistry::call('QueryDAO::_fromRow', array(&$query, &$row));
		return $query;
	}

	/**
	 * Get a new data object
	 * @return DataObject
	 */
	function newDataObject() {
		return new Query();
	}

	/**
	 * Get field names for which data is localized.
	 * @return array
	 */
	function getLocaleFieldNames() {
		return array('comment', 'subject');
	}

	/**
	 * Insert a new Query.
	 * @param $query Query
	 */
	function insertObject($query) {
		$this->update(
			sprintf('INSERT INTO queries
				(submission_id, stage_id, parent_query_id, user_id, date_posted, date_modified, thread_closed)
				VALUES
				(?, ?, ?, ?, %s, %s, ?)',
				$this->datetimeToDB($query->getDatePosted()),
				$this->datetimeToDB($query->getDateModified())
			), array(
				(int) $query->getSubmissionId(),
				(int) $query->getStageId(),
				(int) $query->getParentQueryId(),
				(int) $query->getUserId(),
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
			'INSERT INTO query_participants
			(query_id, user_id)
			VALUES
			(?, ?)',
			array(
				(int) $queryId,
				(int) $userId,
			)
		);
	}

	/**
	 * Removes a participant from a query.
	 * @param int $queryId
	 * @param int $userId
	 */
	function removeParticipant($queryId, $userId) {
		$this->update(
			'DELETE FROM query_participants WHERE query_id = ? AND user_id = ?',
			array((int) $queryId, (int) $userId)
		);
	}

	/**
	 * Removes all participants from a query.
	 * @param int $queryId
	 */
	function removeAllParticipants($queryId) {
		$this->update(
			'DELETE FROM query_participants WHERE query_id = ?',
			(int) $queryId
		);
	}

	/**
	 * Retrieve all participant user IDs for a query.
	 * @param $queryid int
	 * @return array
	 */
	function getParticipantIds($queryId) {
		$result = $this->retrieve(
			'SELECT	user_id
			FROM	query_participants
			WHERE	query_id = ?',
			(int) $queryId
		);
		$userIds = array();
		while (!$result->EOF) {
			$row = $result->getRowAssoc(false);
			$userIds[] = $row['user_id'];
			$result->MoveNext();
		}
		return $userIds;
	}

	/**
	 * Update an existing Query.
	 * @param $query Query
	 */
	function updateObject($query) {
		$this->update(
			sprintf('UPDATE	queries
				SET	stage_id = ?,
					parent_query_id = ?,
					user_id = ?,
					date_posted = %s,
					date_modified = %s,
					thread_closed = ?
				WHERE	query_id = ?',
				$this->datetimeToDB($query->getDatePosted()),
				$this->datetimeToDB($query->getDateModified())
			), array(
				(int) $query->getStageId(),
				(int) $query->getParentQueryId(),
				(int) $query->getUserId(),
				(int) $query->getThreadClosed(),
				(int) $query->getId()
			)
		);
		$this->updateLocaleFields($query);
	}

	/**
	 * Delete a submission file query.
	 * @param $query Query
	 */
	function deleteObject($query) {
		$this->deleteById($query->getId());
	}

	/**
	 * Delete a submission file query by ID.
	 * @param $queryId int Query ID
	 * @param $submissionId int optional
	 */
	function deleteById($queryId, $submissionId = null) {
		$params = array((int) $queryId);
		if ($submissionId) $params[] = (int) $submissionId;
		$this->update(
			'DELETE FROM queries WHERE query_id = ?' .
			($submissionId?' AND submission_id = ?':''),
			$params
		);
		if ($this->getAffectedRows()) {
			$this->update('DELETE FROM query_settings WHERE query_id = ?', array((int) $queryId));
			$this->removeAllParticipants($queryId);
		}
	}

	/**
	 * Get the ID of the last inserted submission file query.
	 * @return int
	 */
	function getInsertId() {
		return $this->_getInsertId('queries', 'query_id');
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

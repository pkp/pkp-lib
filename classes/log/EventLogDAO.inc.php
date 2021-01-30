<?php

/**
 * @file classes/log/EventLogDAO.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class EventLogDAO
 * @ingroup log
 * @see EventLogEntry
 *
 * @brief Class for inserting/accessing event log entries.
 */


import ('lib.pkp.classes.log.EventLogEntry');

class EventLogDAO extends DAO {

	/**
	 * Retrieve a log entry by ID.
	 * @param $logId int
	 * @param $assocId int optional
	 * @param $assocType int optional
	 * @return EventLogEntry
	 */
	function getById($logId, $assocType = null, $assocId = null) {
		$params = [(int) $logId];
		if (isset($assocType)) {
			$params[] = (int) $assocType;
			$params[] = (int) $assocId;
		}

		$result = $this->retrieve(
			'SELECT * FROM event_log WHERE log_id = ?' .
			(isset($assocType)?' AND assoc_type = ? AND assoc_id = ?':''),
			$params
		);
		$row = $result->current();
		return $row ? $this->build((array) $row) : null;
	}

	/**
	 * Retrieve all log entries matching the specified association.
	 * @param $assocType int
	 * @param $assocId int
	 * @param $rangeInfo object optional
	 * @return DAOResultFactory containing matching EventLogEntry ordered by sequence
	 */
	function getByAssoc($assocType, $assocId, $rangeInfo = null) {
		$result = $this->retrieveRange(
			'SELECT * FROM event_log WHERE assoc_type = ? AND assoc_id = ? ORDER BY log_id DESC',
			[(int) $assocType, (int) $assocId],
			$rangeInfo
		);

		return new DAOResultFactory($result, $this, 'build');
	}

	/**
	 * Instantiate a new data object
	 * @return object
	 */
	function newDataObject() {
		return new EventLogEntry();
	}

	/**
	 * Internal function to return an EventLogEntry object from a row.
	 * @param $row array
	 * @return EventLogEntry
	 */
	function build($row) {
		$entry = $this->newDataObject();
		$entry->setId($row['log_id']);
		$entry->setUserId($row['user_id']);
		$entry->setDateLogged($this->datetimeFromDB($row['date_logged']));
		$entry->setEventType($row['event_type']);
		$entry->setAssocType($row['assoc_type']);
		$entry->setAssocId($row['assoc_id']);
		$entry->setMessage($row['message']);
		$entry->setIsTranslated($row['is_translated']);

		$result = $this->retrieve('SELECT * FROM event_log_settings WHERE log_id = ?', [(int) $entry->getId()]);
		$params = [];
		foreach ($result as $r) {
			$params[$r->setting_name] = $this->convertFromDB(
				$r->setting_value,
				$r->setting_type
			);
		}
		$entry->setParams($params);

		HookRegistry::call('EventLogDAO::build', array(&$entry, &$row));

		return $entry;
	}

	/**
	 * Insert a new log entry.
	 * @param $entry EventLogEntry
	 */
	function insertObject($entry) {
		$this->update(
			sprintf('INSERT INTO event_log
				(user_id, date_logged, event_type, assoc_type, assoc_id, message, is_translated)
				VALUES
				(?, %s, ?, ?, ?, ?, ?)',
				$this->datetimeToDB($entry->getDateLogged())),
			[
				(int) $entry->getUserId(),
				(int) $entry->getEventType(),
				(int) $entry->getAssocType(),
				(int) $entry->getAssocId(),
				$entry->getMessage(),
				(int) $entry->getIsTranslated()
			]
		);
		$entry->setId($this->getInsertId());

		// Add name => value entries into the settings table
		$params = $entry->getParams();
		if (is_array($params)) foreach ($params as $key => $value) {
			$type = null;
			$value = $this->convertToDB($value, $type);
			$this->update(
				'INSERT INTO event_log_settings (log_id, setting_name, setting_value, setting_type) VALUES (?, ?, ?, ?)',
				[(int) $entry->getId(), $key, $value, $type]
			);
		}

		return $entry->getId();
	}

	/**
	 * Delete a single log entry (and associated settings).
	 * @param $logId int
	 */
	function deleteById($logId, $assocType = null, $assocId = null) {
		$params = [(int) $logId];
		if ($assocType !== null) {
			$params[] = (int) $assocType;
			$params[] = (int) $assocId;
		}
		if ($this->update(
			'DELETE FROM event_log WHERE log_id = ?' .
			($assocType !== null?' AND assoc_type = ? AND assoc_id = ?':''),
			$params
		)) {
			$this->update('DELETE FROM event_log_settings WHERE log_id = ?', [(int) $logId]);
		}
	}

	/**
	 * Delete all log entries for an object.
	 * @param $assocType int
	 * @param $assocId int
	 */
	function deleteByAssoc($assocType, $assocId) {
		$entries = $this->getByAssoc($assocType, $assocId);
		while ($entry = $entries->next()) {
			$this->deleteById($entry->getId());
		}
	}

	/**
	 * Transfer all log entries to another user.
	 * @param $oldUserId int
	 * @param $newUserId int
	 */
	function changeUser($oldUserId, $newUserId) {
		$this->update(
			'UPDATE event_log SET user_id = ? WHERE user_id = ?',
			[(int) $newUserId, (int) $oldUserId]
		);
	}

	/**
	 * Get the ID of the last inserted log entry.
	 * @return int
	 */
	function getInsertId() {
		return $this->_getInsertId('event_log', 'log_id');
	}
}



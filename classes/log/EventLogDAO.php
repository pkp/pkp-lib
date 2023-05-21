<?php

/**
 * @file classes/log/EventLogDAO.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class EventLogDAO
 *
 * @ingroup log
 *
 * @see EventLogEntry
 *
 * @brief Class for inserting/accessing event log entries.
 */

namespace PKP\log;

use PKP\db\DAOResultFactory;
use PKP\plugins\Hook;

class EventLogDAO extends \PKP\db\DAO
{
    /**
     * Retrieve a log entry by ID.
     *
     * @param int $logId
     * @param int $assocId optional
     * @param int $assocType optional
     *
     * @return EventLogEntry
     */
    public function getById($logId, $assocType = null, $assocId = null)
    {
        $params = [(int) $logId];
        if (isset($assocType)) {
            $params[] = (int) $assocType;
            $params[] = (int) $assocId;
        }

        $result = $this->retrieve(
            'SELECT * FROM event_log WHERE log_id = ?' .
            (isset($assocType) ? ' AND assoc_type = ? AND assoc_id = ?' : ''),
            $params
        );
        $row = $result->current();
        return $row ? $this->build((array) $row) : null;
    }

    /**
     * Retrieve all log entries matching the specified association.
     *
     * @param int $assocType
     * @param int $assocId
     * @param ?\PKP\db\DBResultRange $rangeInfo optional
     *
     * @return DAOResultFactory<EventLogEntry> containing matching EventLogEntry ordered by sequence
     */
    public function getByAssoc($assocType, $assocId, $rangeInfo = null)
    {
        $result = $this->retrieveRange(
            'SELECT * FROM event_log WHERE assoc_type = ? AND assoc_id = ? ORDER BY log_id DESC',
            [(int) $assocType, (int) $assocId],
            $rangeInfo
        );

        return new DAOResultFactory($result, $this, 'build');
    }

    /**
     * Instantiate a new data object
     *
     * @return object
     */
    public function newDataObject()
    {
        return new EventLogEntry();
    }

    /**
     * Internal function to return an EventLogEntry object from a row.
     *
     * @param array $row
     *
     * @return EventLogEntry
     */
    public function build($row)
    {
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

        Hook::call('EventLogDAO::build', [&$entry, &$row]);

        return $entry;
    }

    /**
     * Insert a new log entry.
     *
     * @param EventLogEntry $entry
     */
    public function insertObject($entry)
    {
        $this->update(
            sprintf(
                'INSERT INTO event_log
				(user_id, date_logged, event_type, assoc_type, assoc_id, message, is_translated)
				VALUES
				(?, %s, ?, ?, ?, ?, ?)',
                $this->datetimeToDB($entry->getDateLogged())
            ),
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
        if (is_array($params)) {
            foreach ($params as $key => $value) {
                $type = null;
                $value = $this->convertToDB($value, $type);
                $this->update(
                    'INSERT INTO event_log_settings (log_id, setting_name, setting_value, setting_type) VALUES (?, ?, ?, ?)',
                    [(int) $entry->getId(), $key, $value, $type]
                );
            }
        }

        return $entry->getId();
    }

    /**
     * Delete a single log entry (and associated settings).
     *
     * @param int $logId
     * @param null|mixed $assocType
     * @param null|mixed $assocId
     */
    public function deleteById($logId, $assocType = null, $assocId = null)
    {
        $params = [(int) $logId];
        if ($assocType !== null) {
            $params[] = (int) $assocType;
            $params[] = (int) $assocId;
        }
        if ($this->update(
            'DELETE FROM event_log WHERE log_id = ?' .
            ($assocType !== null ? ' AND assoc_type = ? AND assoc_id = ?' : ''),
            $params
        )) {
            $this->update('DELETE FROM event_log_settings WHERE log_id = ?', [(int) $logId]);
        }
    }

    /**
     * Delete all log entries for an object.
     *
     * @param int $assocType
     * @param int $assocId
     */
    public function deleteByAssoc($assocType, $assocId)
    {
        $entries = $this->getByAssoc($assocType, $assocId);
        while ($entry = $entries->next()) {
            $this->deleteById($entry->getId());
        }
    }

    /**
     * Transfer all log entries to another user.
     *
     * @param int $oldUserId
     * @param int $newUserId
     */
    public function changeUser($oldUserId, $newUserId)
    {
        $this->update(
            'UPDATE event_log SET user_id = ? WHERE user_id = ?',
            [(int) $newUserId, (int) $oldUserId]
        );
    }
}

if (!PKP_STRICT_MODE) {
    class_alias('\PKP\log\EventLogDAO', '\EventLogDAO');
}

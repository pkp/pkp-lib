<?php

/**
 * @file classes/announcement/AnnouncementDAO.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class AnnouncementDAO
 * @ingroup announcement
 * @see Announcement
 *
 * @brief Operations for retrieving and modifying Announcement objects.
 */

import('lib.pkp.classes.announcement.Announcement');
import('lib.pkp.classes.db.SchemaDAO');

use Illuminate\Database\Capsule\Manager as Capsule;

class AnnouncementDAO extends SchemaDAO {
	/** @var string One of the SCHEMA_... constants */
	var $schemaName = SCHEMA_ANNOUNCEMENT;

	/** @var string The name of the primary table for this object */
	var $tableName = 'announcements';

	/** @var string The name of the settings table for this object */
	var $settingsTableName = 'announcement_settings';

	/** @var string The column name for the object id in primary and settings tables */
	var $primaryKeyColumn = 'announcement_id';

	/** @var array Maps schema properties for the primary table to their column names */
	var $primaryTableColumns = [
		'id' => 'announcement_id',
		'assocId' => 'assoc_id',
		'assocType' => 'assoc_type',
		'typeId' => 'type_id',
		'dateExpire' => 'date_expire',
		'datePosted' => 'date_posted',
	];

	/**
	 * Retrieve an announcement by announcement ID.
	 * @param $announcementId int
	 * @param $assocType int Optional assoc type
	 * @param $assocId int Optional assoc ID
	 * @return Announcement
	 */
	function getById($announcementId, $assocType = null, $assocId = null) {
		$query = Capsule::table($this->tableName)->where($this->primaryKeyColumn, '=', (int) $announcementId);
		if ($assocType !== null) $query->where('assoc_type', '=', (int) $assocType);
		if ($assocId !== null) $query->where('assoc_id', '=', (int) $assocType);
		if ($result = $query->first()) {
			return $this->_fromRow((array) $result);
		}
		return null;
	}

	/**
	 * Get a new data object.
	 * @return DataObject
	 */
	function newDataObject() {
		return new Announcement();
	}

	/**
	 * Delete announcements by announcement type ID.
	 * @param $typeId int Announcement type ID
	 * @return boolean
	 */
	function deleteByTypeId($typeId) {
		foreach ($this->getByTypeId($typeId) as $announcement) {
			$this->deleteObject($announcement);
		}
	}

	/**
	 * Delete announcements by Assoc ID
	 * @param $assocType int ASSOC_TYPE_...
	 * @param $assocId int
	 */
	function deleteByAssoc($assocType, $assocId) {
		$announcements = $this->getByAssocId($assocType, $assocId);
		while ($announcement = $announcements->next()) {
			$this->deleteById($announcement->getId());
		}
		return true;
	}

	/**
	 * Retrieve an array of announcements matching a particular assoc ID.
	 * @param $assocType int ASSOC_TYPE_...
	 * @param $assocId int
	 * @return Generator Matching Announcements
	 */
	function getByAssocId($assocType, $assocId) {
		$result = Capsule::table($this->tableName)
			->where('assoc_type', '=', (int) $assocType)
			->where('assoc_id', '=', (int) $assocId)
			->orderByDesc('date_posted')
			->get();
		foreach ($result as $row) {
			yield $row->announcement_id => $this->_fromRow((array) $row);
		}
	}

	/**
	 * Retrieve an array of announcements matching a particular type ID.
	 * @param $typeId int
	 * @return Generator Matching Announcements
	 */
	function getByTypeId($typeId) {
		$result = $this->retrieveRange(
			'SELECT * FROM announcements WHERE type_id = ? ORDER BY date_posted DESC',
			[(int) $typeId]
		);
		foreach ($result as $row) {
			yield $row->announcement_id => $this->_fromRow((array) $row);
		}
	}

	/**
	 * Retrieve an array of numAnnouncements announcements matching a particular Assoc ID.
	 * @param $assocType int ASSOC_TYPE_...
	 * @param $assocId int
	 * @param $numAnnouncements int Maximum number of announcements
	 * @param $rangeInfo DBResultRange (optional)
	 * @return object DAOResultFactory containing matching Announcements
	 */
	function getNumAnnouncementsByAssocId($assocType, $assocId, $numAnnouncements, $rangeInfo = null) {
		$result = $this->retrieveRange(
			'SELECT *
			FROM announcements
			WHERE assoc_type = ?
				AND assoc_id = ?
			ORDER BY date_posted DESC LIMIT ?',
			[(int) $assocType, (int) $assocId, (int) $numAnnouncements],
			$rangeInfo
		);

		return new DAOResultFactory($result, $this, '_fromRow');
	}

	/**
	 * Retrieve an array of announcements with no/valid expiry date matching a particular Assoc ID.
	 * @param $assocType int ASSOC_TYPE_...
	 * @param $assocId int
	 * @param $rangeInfo DBResultRange (optional)
	 * @return object DAOResultFactory containing matching Announcements
	 */
	function getAnnouncementsNotExpiredByAssocId($assocType, $assocId, $rangeInfo = null) {
		$result = $this->retrieveRange(
			'SELECT *
			FROM announcements
			WHERE assoc_type = ?
				AND assoc_id = ?
				AND (date_expire IS NULL OR DATE(date_expire) > DATE(NOW()))
				AND (DATE(date_posted) <= DATE(NOW()))
			ORDER BY date_posted DESC',
			[(int) $assocType, (int) $assocId],
			$rangeInfo
		);

		return new DAOResultFactory($result, $this, '_fromRow');
	}

	/**
	 * Retrieve an array of numAnnouncements announcements with no/valid expiry date matching a particular Assoc ID.
	 * @param $assocType int ASSOC_TYPE_...
	 * @param $assocId int
	 * @param $numAnnouncements Maximum number of announcements to include
	 * @param $rangeInfo DBResultRange (optional)
	 * @return object DAOResultFactory containing matching Announcements
	 */
	function getNumAnnouncementsNotExpiredByAssocId($assocType, $assocId, $numAnnouncements, $rangeInfo = null) {
		$result = $this->retrieveRange(
			'SELECT *
			FROM announcements
			WHERE assoc_type = ?
				AND assoc_id = ?
				AND (date_expire IS NULL OR DATE(date_expire) > DATE(NOW()))
				AND (DATE(date_posted) <= DATE(NOW()))
			ORDER BY date_posted DESC LIMIT ?',
			[(int) $assocType, (int) $assocId, (int) $numAnnouncements],
			$rangeInfo
		);

		return new DAOResultFactory($result, $this, '_fromRow');
	}

	/**
	 * Retrieve most recent announcement by Assoc ID.
	 * @param $assocType int ASSOC_TYPE_...
	 * @param $assocId int
	 * @return Announcement
	 */
	function getMostRecentAnnouncementByAssocId($assocType, $assocId) {
		$result = $this->retrieve(
			'SELECT *
			FROM announcements
			WHERE assoc_type = ?
				AND assoc_id = ?
			ORDER BY date_posted DESC LIMIT 1',
			[(int) $assocType, (int) $assocId]
		);
		$row = $result->current();
		return $row ? $this->_fromRow((array) $row) : null;
	}

	/**
	 * Get the ID of the last inserted announcement.
	 * @return int
	 */
	function getInsertId() {
		return $this->_getInsertId('announcements', 'announcement_id');
	}
}



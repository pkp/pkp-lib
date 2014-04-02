<?php

/**
 * @file classes/announcement/PKPAnnouncementDAO.inc.php
 *
 * Copyright (c) 2013-2014 Simon Fraser University Library
 * Copyright (c) 2000-2014 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class PKPAnnouncementDAO
 * @ingroup announcement
 * @see Announcement, PKPAnnouncement
 *
 * @brief Operations for retrieving and modifying Announcement objects.
 */

import('lib.pkp.classes.announcement.PKPAnnouncement');

class PKPAnnouncementDAO extends DAO {
	/**
	 * Constructor
	 */
	function PKPAnnouncementDAO() {
		parent::DAO();
	}

	/**
	 * Retrieve an announcement by announcement ID.
	 * @param $announcementId int
	 * @return Announcement
	 */
	function &getById($announcementId) {
		$result =& $this->retrieve(
			'SELECT * FROM announcements WHERE announcement_id = ?',
			(int) $announcementId
		);

		$returner = null;
		if ($result->RecordCount() != 0) {
			$returner =& $this->_returnAnnouncementFromRow($result->GetRowAssoc(false));
		}
		$result->Close();
		return $returner;
	}

	/**
	 * @see getById
	 */
	function &getAnnouncement($announcementId) {
		if (Config::getVar('debug', 'deprecation_warnings')) trigger_error('Deprecated function.');
		return $this->getById($announcementId);
	}

	/**
	 * Retrieve announcement Assoc ID by announcement ID.
	 * @param $announcementId int
	 * @return int
	 */
	function getAnnouncementAssocId($announcementId) {
		$result =& $this->retrieve(
			'SELECT assoc_id FROM announcements WHERE announcement_id = ?',
			(int) $announcementId
		);

		return isset($result->fields[0]) ? $result->fields[0] : 0;
	}

	/**
	 * Retrieve announcement Assoc ID by announcement ID.
	 * @param $announcementId int
	 * @return int
	 */
	function getAnnouncementAssocType($announcementId) {
		$result =& $this->retrieve(
			'SELECT assoc_type FROM announcements WHERE announcement_id = ?',
			(int) $announcementId
		);

		return isset($result->fields[0]) ? $result->fields[0] : 0;
	}

	/**
	 * Get the list of localized field names for this table
	 * @return array
	 */
	function getLocaleFieldNames() {
		return array('title', 'descriptionShort', 'description');
	}

	/**
	 * Get a new data object.
	 * @return DataObject
	 */
	function newDataObject() {
		assert(false); // Should be implemented by subclasses
	}

	/**
	 * Internal function to return an Announcement object from a row.
	 * @param $row array
	 * @return Announcement
	 */
	function &_returnAnnouncementFromRow(&$row) {
		$announcement = $this->newDataObject();
		$announcement->setId($row['announcement_id']);
		$announcement->setAssocType($row['assoc_type']);
		$announcement->setAssocId($row['assoc_id']);
		$announcement->setTypeId($row['type_id']);
		$announcement->setDateExpire($this->datetimeFromDB($row['date_expire']));
		$announcement->setDatePosted($this->datetimeFromDB($row['date_posted']));

		$this->getDataObjectSettings('announcement_settings', 'announcement_id', $row['announcement_id'], $announcement);

		return $announcement;
	}

	/**
	 * Update the settings for this object
	 * @param $announcement object
	 */
	function updateLocaleFields(&$announcement) {
		$this->updateDataObjectSettings('announcement_settings', $announcement, array(
			'announcement_id' => $announcement->getId()
		));
	}

	/**
	 * Insert a new Announcement.
	 * @param $announcement Announcement
	 * @return int
	 */
	function insertAnnouncement(&$announcement) {
		$this->update(
			sprintf('INSERT INTO announcements
				(assoc_type, assoc_id, type_id, date_expire, date_posted)
				VALUES
				(?, ?, ?, %s, %s)',
				$this->datetimeToDB($announcement->getDateExpire()),
				$this->datetimeToDB($announcement->getDatetimePosted())
			), array(
				(int) $announcement->getAssocType(),
				(int) $announcement->getAssocId(),
				(int) $announcement->getTypeId()
			)
		);
		$announcement->setId($this->getInsertAnnouncementId());
		$this->updateLocaleFields($announcement);
		return $announcement->getId();
	}

	/**
	 * Update an existing announcement.
	 * @param $announcement Announcement
	 * @return boolean
	 */
	function updateObject(&$announcement) {
		$returner = $this->update(
			sprintf('UPDATE announcements
				SET
					assoc_type = ?,
					assoc_id = ?,
					type_id = ?,
					date_expire = %s,
					date_posted = %s
				WHERE announcement_id = ?',
				$this->datetimeToDB($announcement->getDateExpire()),
				$this->datetimeToDB($announcement->getDatetimePosted())),
			array(
				(int) $announcement->getAssocType(),
				(int) $announcement->getAssocId(),
				(int) $announcement->getTypeId(),
				(int) $announcement->getId()
			)
		);
		$this->updateLocaleFields($announcement);
		return $returner;
	}

	/**
	 * @see updateObject
	 */
	function updateAnnouncement(&$announcement) {
		if (Config::getVar('debug', 'deprecation_warnings')) trigger_error('Deprecated function.');
		return $this->updateObject($announcement);
	}

	/**
	 * Delete an announcement.
	 * @param $announcement Announcement
	 * @return boolean
	 */
	function deleteObject($announcement) {
		return $this->deleteById($announcement->getId());
	}

	/**
	 * @see deleteObject
	 */
	function deleteAnnouncement($announcement) {
		if (Config::getVar('debug', 'deprecation_warnings')) trigger_error('Deprecated function.');
		return $this->deleteObject($announcement);
	}

	/**
	 * Delete an announcement by announcement ID.
	 * @param $announcementId int
	 * @return boolean
	 */
	function deleteById($announcementId) {
		$this->update('DELETE FROM announcement_settings WHERE announcement_id = ?', (int) $announcementId);
		return $this->update('DELETE FROM announcements WHERE announcement_id = ?', (int) $announcementId);
	}

	/**
	 * @see deleteById
	 */
	function deleteAnnouncementById($announcementId) {
		if (Config::getVar('debug', 'deprecation_warnings')) trigger_error('Deprecated function.');
		return $this->deleteById($announcementId);
	}

	/**
	 * Delete announcements by announcement type ID.
	 * @param $typeId int
	 * @return boolean
	 */
	function deleteByTypeId($typeId) {
		$announcements =& $this->getByTypeId($typeId);
		while (($announcement =& $announcements->next())) {
			$this->deleteObject($announcement);
			unset($announcement);
		}
	}

	/**
	 * Delete announcements by Assoc ID
	 * @param $assocType int
	 * @param $assocId int
	 */
	function deleteByAssoc($assocType, $assocId) {
		$announcements =& $this->getByAssocId($assocType, $assocId);
		while (($announcement =& $announcements->next())) {
			$this->deleteById($announcement->getId());
			unset($announcement);
		}
		return true;
	}

	/**
	 * @see deleteByAssocId
	 */
	function deleteAnnouncementsByAssocId($assocType, $assocId) {
		if (Config::getVar('debug', 'deprecation_warnings')) trigger_error('Deprecated function.');
		return $this->deleteByAssocId($assocType, $assocId);
	}

	/**
	 * Retrieve an array of announcements matching a particular assoc ID.
	 * @param $assocType int
	 * @return object DAOResultFactory containing matching Announcements
	 */
	function &getByAssocId($assocType, $assocId, $rangeInfo = null) {
		$result =& $this->retrieveRange(
			'SELECT *
			FROM announcements
			WHERE assoc_type = ? AND assoc_id = ?
			ORDER BY announcement_id DESC',
			array((int) $assocType, (int) $assocId),
			$rangeInfo
		);

		$returner = new DAOResultFactory($result, $this, '_returnAnnouncementFromRow');
		return $returner;
	}

	/**
	 * @see getByAssocId
	 */
	function &getAnnouncementsByAssocId($assocType, $assocId, $rangeInfo = null) {
		if (Config::getVar('debug', 'deprecation_warnings')) trigger_error('Deprecated function.');
		return $this->getByAssocId($assocType, $assocId, $rangeInfo);
	}

	/**
	 * Retrieve an array of announcements matching a particular type ID.
	 * @param $typeId int
	 * @return object DAOResultFactory containing matching Announcements
	 */
	function &getByTypeId($typeId, $rangeInfo = null) {
		$result =& $this->retrieveRange(
			'SELECT * FROM announcements WHERE type_id = ? ORDER BY announcement_id DESC',
			(int) $typeId,
			$rangeInfo
		);

		$returner = new DAOResultFactory($result, $this, '_returnAnnouncementFromRow');
		return $returner;
	}

	/**
	 * @see getByTypeId
	 */
	function &getAnnouncementsByTypeId($typeId, $rangeInfo = null) {
		if (Config::getVar('debug', 'deprecation_warnings')) trigger_error('Deprecated function.');
		return $this->getByTypeId($typeId, $rangeInfo);
	}

	/**
	 * Retrieve an array of numAnnouncements announcements matching a particular Assoc ID.
	 * @param $assocType int
	 * @return object DAOResultFactory containing matching Announcements
	 */
	function &getNumAnnouncementsByAssocId($assocType, $assocId, $numAnnouncements, $rangeInfo = null) {
		$result =& $this->retrieveRange(
			'SELECT *
			FROM announcements
			WHERE assoc_type = ?
				AND assoc_id = ?
			ORDER BY announcement_id DESC LIMIT ?',
			array((int) $assocType, (int) $assocId, (int) $numAnnouncements),
			$rangeInfo
		);

		$returner = new DAOResultFactory($result, $this, '_returnAnnouncementFromRow');
		return $returner;
	}

	/**
	 * Retrieve an array of announcements with no/valid expiry date matching a particular Assoc ID.
	 * @param $assocType int
	 * @param $assocId int
	 * @param $rangeInfo DBResultRange
	 * @return object DAOResultFactory containing matching Announcements
	 */
	function &getAnnouncementsNotExpiredByAssocId($assocType, $assocId, $rangeInfo = null) {
		$result =& $this->retrieveRange(
			'SELECT *
			FROM announcements
			WHERE assoc_type = ?
				AND assoc_id = ?
				AND (date_expire IS NULL OR date_expire > CURRENT_DATE)
			ORDER BY announcement_id DESC',
			array((int) $assocType, (int) $assocId),
			$rangeInfo
		);

		$returner = new DAOResultFactory($result, $this, '_returnAnnouncementFromRow');
		return $returner;
	}

	/**
	 * Retrieve an array of numAnnouncements announcements with no/valid expiry date matching a particular Assoc ID.
	 * @param $assocType int
	 * @return object DAOResultFactory containing matching Announcements
	 */
	function &getNumAnnouncementsNotExpiredByAssocId($assocType, $assocId, $numAnnouncements, $rangeInfo = null) {
		$result =& $this->retrieveRange(
			'SELECT *
			FROM announcements
			WHERE assoc_type = ?
				AND assoc_id = ?
				AND (date_expire IS NULL OR date_expire > CURRENT_DATE)
			ORDER BY announcement_id DESC LIMIT ?',
			array((int) $assocType, (int) $assocId, (int) $numAnnouncements),
			$rangeInfo
		);

		$returner = new DAOResultFactory($result, $this, '_returnAnnouncementFromRow');
		return $returner;
	}

	/**
	 * Retrieve most recent announcement by Assoc ID.
	 * @param $assocType int
	 * @return Announcement
	 */
	function &getMostRecentAnnouncementByAssocId($assocType, $assocId) {
		$result =& $this->retrieve(
			'SELECT *
			FROM announcements
			WHERE assoc_type = ?
				AND assoc_id = ?
			ORDER BY announcement_id DESC LIMIT 1',
			array((int) $assocType, (int) $assocId)
		);

		$returner = null;
		if ($result->RecordCount() != 0) {
			$returner =& $this->_returnAnnouncementFromRow($result->GetRowAssoc(false));
		}
		$result->Close();
		return $returner;
	}

	/**
	 * Get the ID of the last inserted announcement.
	 * @return int
	 */
	function getInsertAnnouncementId() {
		return $this->getInsertId('announcements', 'announcement_id');
	}
}

?>

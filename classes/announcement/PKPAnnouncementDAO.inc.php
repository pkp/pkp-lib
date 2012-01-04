<?php

/**
 * @file PKPAnnouncementDAO.inc.php
 *
 * Copyright (c) 2000-2012 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class PKPAnnouncementDAO
 * @ingroup announcement
 * @see Announcement, PKPAnnouncement
 *
 * @brief Operations for retrieving and modifying Announcement objects.
 */

import('announcement.PKPAnnouncement');

class PKPAnnouncementDAO extends DAO {
	/**
	 * Retrieve an announcement by announcement ID.
	 * @param $announcementId int
	 * @return Announcement
	 */
	function &getAnnouncement($announcementId) {
		$result =& $this->retrieve(
			'SELECT * FROM announcements WHERE announcement_id = ?', $announcementId
		);

		$returner = null;
		if ($result->RecordCount() != 0) {
			$returner =& $this->_returnAnnouncementFromRow($result->GetRowAssoc(false));
		}
		$result->Close();
		return $returner;
	}

	/**
	 * Retrieve announcement Assoc ID by announcement ID.
	 * @param $announcementId int
	 * @return int
	 */
	function getAnnouncementAssocId($announcementId) {
		$result =& $this->retrieve(
			'SELECT assoc_id FROM announcements WHERE announcement_id = ?', $announcementId
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
			'SELECT assoc_type FROM announcements WHERE announcement_id = ?', $announcementId
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
	 * Internal function to return an Announcement object from a row.
	 * @param $row array
	 * @return Announcement
	 */
	function &_returnAnnouncementFromRow(&$row) {
		$announcement = new Announcement();
		$announcement->setId($row['announcement_id']);
		$announcement->setAssocType($row['assoc_type']);
		$announcement->setAssocId($row['assoc_id']);
		$announcement->setTypeId($row['type_id']);
		$announcement->setDateExpire($this->dateFromDB($row['date_expire']));
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
				$this->datetimeToDB($announcement->getDateExpire()), $this->datetimeToDB($announcement->getDatetimePosted())),
			array(
				$announcement->getAssocType(),
				$announcement->getAssocId(),
				$announcement->getTypeId()
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
					date_expire = %s
				WHERE announcement_id = ?',
				$this->datetimeToDB($announcement->getDateExpire())),
			array(
				$announcement->getAssocType(),
				$announcement->getAssocId(),
				$announcement->getTypeId(),
				$announcement->getId()
			)
		);
		$this->updateLocaleFields($announcement);
		return $returner;
	}

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
		return $this->deleteAnnouncementById($announcement->getId());
	}

	function deleteAnnouncement($announcement) {
		if (Config::getVar('debug', 'deprecation_warnings')) trigger_error('Deprecated function.');
		return $this->deleteObject($announcement);
	}

	/**
	 * Delete an announcement by announcement ID.
	 * @param $announcementId int
	 * @return boolean
	 */
	function deleteAnnouncementById($announcementId) {
		$this->update('DELETE FROM announcement_settings WHERE announcement_id = ?', $announcementId);
		return $this->update('DELETE FROM announcements WHERE announcement_id = ?', $announcementId);
	}

	/**
	 * Delete announcements by announcement type ID.
	 * @param $typeId int
	 * @return boolean
	 */
	function deleteAnnouncementByTypeId($typeId) {
		$announcements =& $this->getAnnouncementsByTypeId($typeId);
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
	 function deleteAnnouncementsByAssocId($assocType, $assocId) {
		$announcements =& $this->getAnnouncementsByAssocId($assocType, $assocId);
		while (($announcement =& $announcements->next())) {
			$this->deleteAnnouncementById($announcement->getId());
			unset($announcement);
		}
		return true;
	 }

	/**
	 * Retrieve an array of announcements matching a particular assoc ID.
	 * @param $assocType int
	 * @return object DAOResultFactory containing matching Announcements
	 */
	function &getAnnouncementsByAssocId($assocType, $assocId, $rangeInfo = null) {
		$result =& $this->retrieveRange(
			'SELECT *
			FROM announcements
			WHERE assoc_type = ? AND assoc_id = ?
			ORDER BY announcement_id DESC',
			array($assocType, $assocId),
			$rangeInfo
		);

		$returner = new DAOResultFactory($result, $this, '_returnAnnouncementFromRow');
		return $returner;
	}

	/**
	 * Retrieve an array of announcements matching a particular type ID.
	 * @param $typeId int
	 * @return object DAOResultFactory containing matching Announcements
	 */
	function &getAnnouncementsByTypeId($typeId, $rangeInfo = null) {
		$result =& $this->retrieveRange(
			'SELECT * FROM announcements WHERE type_id = ? ORDER BY announcement_id DESC', $typeId, $rangeInfo
		);

		$returner = new DAOResultFactory($result, $this, '_returnAnnouncementFromRow');
		return $returner;
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
			array($assocType, $assocId, $numAnnouncements),
			$rangeInfo
		);

		$returner = new DAOResultFactory($result, $this, '_returnAnnouncementFromRow');
		return $returner;
	}

	/**
	 * Retrieve an array of announcements with no/valid expiry date matching a particular Assoc ID.
	 * @param $assocType int
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
			array($assocType, $assocId),
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
			array($assocType, $assocId, $numAnnouncements), $rangeInfo
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
			array($assocType, $assocId)
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

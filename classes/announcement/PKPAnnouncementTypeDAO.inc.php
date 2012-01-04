<?php

/**
 * @file PKPAnnouncementTypeDAO.inc.php
 *
 * Copyright (c) 2000-2012 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class PKPAnnouncementTypeDAO
 * @ingroup announcement
 * @see AnnouncementType, PKPAnnouncementType
 *
 * @brief Operations for retrieving and modifying AnnouncementType objects.
 */

//$Id$

import('announcement.PKPAnnouncementType');

class PKPAnnouncementTypeDAO extends DAO {
	/**
	 * Retrieve an announcement type by announcement type ID.
	 * @param $typeId int
	 * @return AnnouncementType
	 */
	function &getAnnouncementType($typeId) {
		$result =& $this->retrieve(
			'SELECT * FROM announcement_types WHERE type_id = ?', $typeId
		);

		$returner = null;
		if ($result->RecordCount() != 0) {
			$returner =& $this->_returnAnnouncementTypeFromRow($result->GetRowAssoc(false));
		}
		$result->Close();
		return $returner;
	}

	/**
	 * Retrieve announcement type Assoc ID by announcement type ID.
	 * @param $typeId int
	 * @return int
	 */
	function getAnnouncementTypeAssocId($typeId) {
		$result =& $this->retrieve(
			'SELECT assoc_id FROM announcement_types WHERE type_id = ?', $typeId
		);

		return isset($result->fields[0]) ? $result->fields[0] : 0;
	}

	/**
	 * Retrieve announcement type name by ID.
	 * @param $typeId int
	 * @return string
	 */
	function getAnnouncementTypeName($typeId) {
		$result =& $this->retrieve(
			'SELECT COALESCE(l.setting_value, p.setting_value) FROM announcement_type_settings l LEFT JOIN announcement_type_settings p ON (p.type_id = ? AND p.setting_name = ? AND p.locale = ?) WHERE l.type_id = ? AND l.setting_name = ? AND l.locale = ?',
			array(
				$typeId, 'name', AppLocale::getLocale(),
				$typeId, 'name', AppLocale::getPrimaryLocale()
			)
		);

		$returner = isset($result->fields[0]) ? $result->fields[0] : false;

		$result->Close();
		unset($result);

		return $returner;
	}


	/**
	 * Check if a announcement type exists with the given type id for a assoc type/id pair.
	 * @param $typeId int
	 * @param $assocType int
	 * @return boolean
	 */
	function announcementTypeExistsByTypeId($typeId, $assocType, $assocId) {
		$result =& $this->retrieve(
			'SELECT COUNT(*)
			FROM	announcement_types
			WHERE	type_id = ? AND
				assoc_type = ? AND
				assoc_id = ?',
			array(
				$typeId,
				$assocType,
				$assocId
			)
		);
		$returner = isset($result->fields[0]) && $result->fields[0] != 0 ? true : false;

		$result->Close();
		unset($result);

		return $returner;
	}

	function getLocaleFieldNames() {
		return array('name');
	}

	/**
	 * Return announcement type ID based on a type name for an assoc type/id pair.
	 * @param $typeName string
	 * @param $assocType int
	 * @return int
	 */
	function getAnnouncementTypeByTypeName($typeName, $assocType) {
		$result =& $this->retrieve(
			'SELECT type_id
				FROM announcement_types
				WHERE type_name = ?
				AND assoc_type = ?
				AND assoc_id = ?',
			array(
				$typeName,
				$assocType
			)
		);
		$returner = isset($result->fields[0]) ? $result->fields[0] : 0;

		$result->Close();
		unset($result);

		return $returner;
	}

	/**
	 * Internal function to return an AnnouncementType object from a row.
	 * @param $row array
	 * @return AnnouncementType
	 */
	function &_returnAnnouncementTypeFromRow(&$row) {
		$announcementType = new AnnouncementType();
		$announcementType->setId($row['type_id']);
		$announcementType->setAssocType($row['assoc_type']);
		$announcementType->setAssocId($row['assoc_id']);
		$this->getDataObjectSettings('announcement_type_settings', 'type_id', $row['type_id'], $announcementType);

		return $announcementType;
	}

	/**
	 * Update the localized settings for this object
	 * @param $announcementType object
	 */
	function updateLocaleFields(&$announcementType) {
		$this->updateDataObjectSettings('announcement_type_settings', $announcementType, array(
			'type_id' => $announcementType->getId()
		));
	}

	/**
	 * Insert a new AnnouncementType.
	 * @param $announcementType AnnouncementType
	 * @return int
	 */
	function insertAnnouncementType(&$announcementType) {
		$this->update(
			sprintf('INSERT INTO announcement_types
				(assoc_type, assoc_id)
				VALUES
				(?, ?)'),
			array(
				$announcementType->getAssocType(),
				$announcementType->getAssocId()
			)
		);
		$announcementType->setId($this->getInsertTypeId());
		$this->updateLocaleFields($announcementType);
		return $announcementType->getId();
	}

	/**
	 * Update an existing announcement type.
	 * @param $announcement AnnouncementType
	 * @return boolean
	 */
	function updateObject(&$announcementType) {
		$returner = $this->update(
			'UPDATE	announcement_types
			SET	assoc_type = ?,
				assoc_id = ?
			WHERE	type_id = ?',
			array(
				$announcementType->getAssocType(),
				$announcementType->getAssocId(),
				$announcementType->getId()
			)
		);

		$this->updateLocaleFields($announcementType);
		return $returner;
	}

	function updateAnnouncementType(&$announcementType) {
		if (Config::getVar('debug', 'deprecation_warnings')) trigger_error('Deprecated function.');
		return $this->updateObject($announcementType);
	}

	/**
	 * Delete an announcement type. Note that all announcements with this type are also
	 * deleted.
	 * @param $announcementType AnnouncementType
	 * @return boolean
	 */
	function deleteObject($announcementType) {
		return $this->deleteAnnouncementTypeById($announcementType->getId());
	}

	function deleteAnnouncementType($announcementType) {
		if (Config::getVar('debug', 'deprecation_warnings')) trigger_error('Deprecated function.');
		return $this->deleteObject($announcementType);
	}

	/**
	 * Delete an announcement type by announcement type ID. Note that all announcements with
	 * this type ID are also deleted.
	 * @param $typeId int
	 * @return boolean
	 */
	function deleteAnnouncementTypeById($typeId) {
		$this->update('DELETE FROM announcement_type_settings WHERE type_id = ?', $typeId);
		$ret = $this->update('DELETE FROM announcement_types WHERE type_id = ?', $typeId);

		// Delete all announcements with this announcement type
		if ($ret) {
			$announcementDao =& DAORegistry::getDAO('AnnouncementDAO');
			return $announcementDao->deleteAnnouncementByTypeId($typeId);
		} else {
			return $ret;
		}
	}

	/**
	 * Delete announcement types by Assoc ID.
	 * @param $assocType int
	 */
	function deleteAnnouncementTypesByAssocId($assocType, $assocId) {
		$types =& $this->getAnnouncementTypesByAssocId($assocType, $assocId);
		while (($type =& $types->next())) {
			$this->deleteObject($type);
			unset($type);
		}
	}

	/**
	 * Retrieve an array of announcement types matching a particular Assoc ID.
	 * @param $assocType int
	 * @return object DAOResultFactory containing matching AnnouncementTypes
	 */
	function &getAnnouncementTypesByAssocId($assocType, $assocId, $rangeInfo = null) {
		$result =& $this->retrieveRange(
			'SELECT * FROM announcement_types WHERE assoc_type = ? AND assoc_id = ? ORDER BY type_id', array($assocType, $assocId), $rangeInfo
		);

		$returner = new DAOResultFactory($result, $this, '_returnAnnouncementTypeFromRow');
		return $returner;
	}

	/**
	 * Get the ID of the last inserted announcement type.
	 * @return int
	 */
	function getInsertTypeId() {
		return $this->getInsertId('announcement_types', 'type_id');
	}
}

?>

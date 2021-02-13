<?php

/**
 * @file classes/announcement/AnnouncementTypeDAO.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class AnnouncementTypeDAO
 * @ingroup announcement
 * @see AnnouncementType
 *
 * @brief Operations for retrieving and modifying AnnouncementType objects.
 */


import('lib.pkp.classes.announcement.AnnouncementType');

class AnnouncementTypeDAO extends DAO {

	/**
	 * Generate a new data object.
	 * @return DataObject
	 */
	function newDataObject() {
		return new AnnouncementType();
	}

	/**
	 * Retrieve an announcement type by announcement type ID.
	 * @param $typeId int Announcement type ID
	 * @param $assocType int Optional assoc type
	 * @param $assocId int Optional assoc ID
	 * @return AnnouncementType
	 */
	function getById($typeId, $assocType = null, $assocId = null) {
		$params = [(int) $typeId];
		if ($assocType !== null) $params[] = (int) $assocType;
		if ($assocId !== null) $params[] = (int) $assocId;
		$result = $this->retrieve(
			'SELECT * FROM announcement_types WHERE type_id = ?' .
			($assocType !== null?' AND assoc_type = ?':'') .
			($assocId !== null?' AND assoc_id = ?':''),
			$params
		);
		$row = $result->current();
		return $row ? $this->_fromRow((array) $row) : null;
	}

	/**
	 * Get the locale field names.
	 * @return array
	 */
	function getLocaleFieldNames() {
		return ['name'];
	}

	/**
	 * Internal function to return an AnnouncementType object from a row.
	 * @param $row array
	 * @return AnnouncementType
	 */
	function _fromRow($row) {
		$announcementType = $this->newDataObject();
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
	function updateLocaleFields($announcementType) {
		$this->updateDataObjectSettings('announcement_type_settings', $announcementType,
			['type_id' => (int) $announcementType->getId()]
		);
	}

	/**
	 * Insert a new AnnouncementType.
	 * @param $announcementType AnnouncementType
	 * @return int
	 */
	function insertObject($announcementType) {
		$this->update(
			sprintf('INSERT INTO announcement_types
				(assoc_type, assoc_id)
				VALUES
				(?, ?)'),
			[(int) $announcementType->getAssocType(), (int) $announcementType->getAssocId()]
		);
		$announcementType->setId($this->getInsertId());
		$this->updateLocaleFields($announcementType);
		return $announcementType->getId();
	}

	/**
	 * Update an existing announcement type.
	 * @param $announcementType AnnouncementType
	 * @return boolean
	 */
	function updateObject($announcementType) {
		$returner = $this->update(
			'UPDATE	announcement_types
			SET	assoc_type = ?,
				assoc_id = ?
			WHERE	type_id = ?',
			[
				(int) $announcementType->getAssocType(),
				(int) $announcementType->getAssocId(),
				(int) $announcementType->getId()
			]
		);

		$this->updateLocaleFields($announcementType);
		return $returner;
	}

	/**
	 * Delete an announcement type. Note that all announcements with this type are also
	 * deleted.
	 * @param $announcementType AnnouncementType
	 * @return boolean
	 */
	function deleteObject($announcementType) {
		return $this->deleteById($announcementType->getId());
	}

	/**
	 * Delete an announcement type by announcement type ID. Note that all announcements with
	 * this type ID are also deleted.
	 * @param $typeId int
	 */
	function deleteById($typeId) {
		$this->update('DELETE FROM announcement_type_settings WHERE type_id = ?', [(int) $typeId]);
		$this->update('DELETE FROM announcement_types WHERE type_id = ?', [(int) $typeId]);

		$announcementDao = DAORegistry::getDAO('AnnouncementDAO'); /* @var $announcementDao AnnouncementDAO */
		$announcementDao->deleteByTypeId($typeId);
	}

	/**
	 * Delete announcement types by association.
	 * @param $assocType int ASSOC_TYPE_...
	 * @param $assocId int
	 */
	function deleteByAssoc($assocType, $assocId) {
		foreach ($this->getByAssoc($assocType, $assocId) as $type) {
			$this->deleteObject($type);
		}
	}

	/**
	 * Retrieve an array of announcement types matching a particular Assoc ID.
	 * @param $assocType int ASSOC_TYPE_...
	 * @param $assocId int
	 * @return Generator Matching AnnouncementTypes
	 */
	function getByAssoc($assocType, $assocId) {
		$result = $this->retrieve(
			'SELECT * FROM announcement_types WHERE assoc_type = ? AND assoc_id = ? ORDER BY type_id',
			[(int) $assocType, (int) $assocId]
		);
		foreach ($result as $row) {
			yield $row->type_id => $this->_fromRow((array) $row);
		}
	}

	/**
	 * Get the ID of the last inserted announcement type.
	 * @return int
	 */
	function getInsertId() {
		return $this->_getInsertId('announcement_types', 'type_id');
	}
}



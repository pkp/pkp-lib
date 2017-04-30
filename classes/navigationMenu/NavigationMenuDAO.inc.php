<?php

/**
 * @file classes/announcement/AnnouncementTypeDAO.inc.php
 *
 * Copyright (c) 2014-2017 Simon Fraser University
 * Copyright (c) 2000-2017 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class AnnouncementTypeDAO
 * @ingroup announcement
 * @see AnnouncementType
 *
 * @brief Operations for retrieving and modifying AnnouncementType objects.
 */


import('lib.pkp.classes.navigationMenu.NavigationMenu');

class NavigationMenuDAO extends DAO {
	/**
	 * Constructor
	 */
	function __construct() {
		parent::__construct();
	}

	/**
	 * Generate a new data object.
	 * @return DataObject
	 */
	function newDataObject() {
		return new NavigationMenu();
	}

	/**
	 * Retrieve a navigation menu by navigation menu ID.
	 * @param $navigationMenuId int navigation menu ID
	 * @param $contextId int Context Id
	 * @param $assocId int Optional assoc ID - may associate with template area :: TODO:DEFSTAT is area Entity or String?
	 * @return NavigationMenu
	 */
	function getById($navigationMenuId, $contextId, $assocId = null) {
		$params = array((int) $navigationMenuId);
		if ($contextId !== null) $params[] = (int) $contextId;
		if ($assocId !== null) $params[] = (int) $assocId;
		$result = $this->retrieve(
			'SELECT * FROM navigation_menus WHERE navigation_menu_id = ?' .
			($contextId !== null?' AND context_id = ?':'') .
			($assocId !== null?' AND assoc_id = ?':''),
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
	 * Retrieve a navigation menu by context Id.
	 * @param $contextId int Context Id
	 * @param $assocId int Optional assoc ID - may associate with template area :: TODO:DEFSTAT is area Entity or String?
	 * @return NavigationMenu
	 */
	function getByContextId($contextId, $assocId = null) {
		$params = array((int) $contextId);
		if ($assocId !== null) $params[] = (int) $assocId;
		$result = $this->retrieve(
			'SELECT * FROM navigation_menus WHERE context_id = ?' .
			($assocId !== null?' AND assoc_id = ?':''),
			$params
		);

		//$returner = isset($result->fields[0]) ? $result->fields[0] : 0;

		//$returner = null;
		//if ($result->RecordCount() != 0) {
		//    $returner = $this->_fromRow($result->GetRowAssoc(false));
		//}
		//$result->Close();
		//return $returner;
		return new DAOResultFactory($result, $this, '_fromRow');
	}

	///**
	// * Retrieve announcement type Assoc ID by announcement type ID.
	// * @param $typeId int
	// * @return int
	// */
	//function getAnnouncementTypeAssocId($typeId) {
	//    $result = $this->retrieve(
	//        'SELECT assoc_id FROM announcement_types WHERE type_id = ?',
	//        (int) $typeId
	//    );

	//    return isset($result->fields[0]) ? $result->fields[0] : 0;
	//}

	///**
	// * Retrieve announcement type name by ID.
	// * @param $typeId int
	// * @return string
	// */
	//function getAnnouncementTypeName($typeId) {
	//    $result = $this->retrieve(
	//        'SELECT COALESCE(l.setting_value, p.setting_value) FROM announcement_type_settings p LEFT JOIN announcement_type_settings l ON (l.type_id = ? AND l.setting_name = ? AND l.locale = ?) WHERE p.type_id = ? AND p.setting_name = ? AND p.locale = ?',
	//        array(
	//            (int) $typeId, 'name', AppLocale::getLocale(),
	//            (int) $typeId, 'name', AppLocale::getPrimaryLocale()
	//        )
	//    );

	//    $returner = isset($result->fields[0]) ? $result->fields[0] : false;

	//    $result->Close();
	//    return $returner;
	//}


	/**
	 * Check if a navigationMenu exists with the given title.
	 * @param $title int
	 * @param $contextId int
	 * @return boolean
	 */
	function navigationMenuExistsByTitle($title, $contextId) {
		$result = $this->retrieve(
			'SELECT COUNT(*)
			FROM	navigation_menus
			WHERE	title = ? AND
				context_id = ?',
			array(
				$title,
				(int) $contextId
			)
		);
		$returner = isset($result->fields[0]) && $result->fields[0] != 0 ? true : false;

		$result->Close();
		return $returner;
	}

	///**
	// * Get the locale field names.
	// * @return array
	// */
	//function getLocaleFieldNames() {
	//    return array('name');
	//}

	///**
	// * Return announcement type ID based on a type name for an assoc type/id pair.
	// * @param $typeName string
	// * @param $assocType int ASSOC_TYPE_...
	// * @param $assocId int
	// * @return int
	// */
	//function getByTypeName($typeName, $assocType, $assocId) {
	//    $result = $this->retrieve(
	//        'SELECT ats.type_id
	//            FROM announcement_type_settings AS ats
	//            LEFT JOIN announcement_types at ON ats.type_id = at.type_id
	//            WHERE ats.setting_name = "name"
	//            AND ats.setting_value = ?
	//            AND at.assoc_type = ?
	//            AND at.assoc_id = ?',
	//        array(
	//            $typeName,
	//            (int) $assocType,
	//            (int) $assocId
	//        )
	//    );
	//    $returner = isset($result->fields[0]) ? $result->fields[0] : 0;

	//    $result->Close();
	//    return $returner;
	//}

	/**
	 * Internal function to return an NavigationMenu object from a row.
	 * @param $row array
	 * @return AnnouncementType
	 */
	function _fromRow($row) {
		$navigationMenu = $this->newDataObject();
		$navigationMenu->setId($row['navigation_menu_id']);
		$navigationMenu->setTitle($row['title']);
		$navigationMenu->setContextId($row['context_id']);
		$navigationMenu->setSeq($row['seq']);
		$navigationMenu->setAssocId($row['assoc_id']);
		$navigationMenu->setDefault($row['default']);
		$navigationMenu->setEnabled($row['enabled']);

		//$this->getDataObjectSettings('announcement_type_settings', 'type_id', $row['type_id'], $announcementType);

		return $navigationMenu;
	}

	///**
	// * Update the localized settings for this object
	// * @param $announcementType object
	// */
	//function updateLocaleFields($announcementType) {
	//    $this->updateDataObjectSettings('announcement_type_settings', $announcementType, array(
	//        'type_id' => (int) $announcementType->getId()
	//    ));
	//}

	/**
	 * Insert a new AnnouncementType.
	 * @param $navigationMenu NavigationMenu
	 * @return int
	 */
	function insertObject($navigationMenu) {
		$this->update(
			sprintf('INSERT INTO navigation_menus
				(title, context_id)
				VALUES
				(?, ?)'),
			array(
				$navigationMenu->getTitle(),
				(int) $navigationMenu->getContextId()
			)
		);
		$navigationMenu->setId($this->getInsertId());
		//$this->updateLocaleFields($announcementType);
		return $navigationMenu->getId();
	}

	/**
	 * Update an existing announcement type.
	 * @param $announcementType AnnouncementType
	 * @return boolean
	 */
	function updateObject($navigationMenu) {
		$returner = $this->update(
			'UPDATE	navigation_menus
			SET	title = ?,
				context_id = ?,
				seq = ?,
				assoc_id = ?,
				default = ?,
				enabled = ?
			WHERE	navigation_menu_id = ?',
			array(
				$navigationMenu->getTitle(),
				(int) $navigationMenu->getContextId(),
				(int) $navigationMenu->getSeq(),
				(int) $navigationMenu->getAssocId(),
				(int) $navigationMenu->getDefault(),
				(int) $navigationMenu->getEnabled(),
				(int) $navigationMenu->getId()
			)
		);

		//$this->updateLocaleFields($announcementType);
		return $returner;
	}

	/**
	 * Delete an announcement type. Note that all announcements with this type are also
	 * deleted.
	 * @param $announcementType AnnouncementType
	 * @return boolean
	 */
	function deleteObject($navigationMenu) {
		return $this->deleteById($navigationMenu->getId());
	}

	/**
	 * Delete an announcement type by announcement type ID. Note that all announcements with
	 * this type ID are also deleted.
	 * @param $typeId int
	 */
	function deleteById($navigationMenuId) {
		return $this->update('DELETE FROM navigation_menus WHERE navigation_menu_id = ?', (int) $navigationMenuId);
	}

	///**
	// * Delete announcement types by association.
	// * @param $assocType int ASSOC_TYPE_...
	// * @param $assocId int
	// */
	//function deleteByAssoc($assocType, $assocId) {
	//    $types = $this->getByAssoc($assocType, $assocId);
	//    while ($type = $types->next()) {
	//        $this->deleteObject($type);
	//    }
	//}

	///**
	// * Retrieve an array of announcement types matching a particular Assoc ID.
	// * @param $assocType int ASSOC_TYPE_...
	// * @param $assocId int
	// * @param $rangeInfo DBResultRange (optional)
	// * @return object DAOResultFactory containing matching AnnouncementTypes
	// */
	//function getByAssoc($assocType, $assocId, $rangeInfo = null) {
	//    $result = $this->retrieveRange(
	//        'SELECT * FROM announcement_types WHERE assoc_type = ? AND assoc_id = ? ORDER BY type_id',
	//        array((int) $assocType, (int) $assocId),
	//        $rangeInfo
	//    );

	//    return new DAOResultFactory($result, $this, '_fromRow');
	//}

	/**
	 * Get the ID of the last inserted announcement type.
	 * @return int
	 */
	function getInsertId() {
		return $this->_getInsertId('navigation_menus', 'navigation_menu_id');
	}
}

?>

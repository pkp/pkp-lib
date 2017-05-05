<?php

/**
 * @file classes/announcement/AnnouncementDAO.inc.php
 *
 * Copyright (c) 2014-2017 Simon Fraser University
 * Copyright (c) 2000-2017 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class AnnouncementDAO
 * @ingroup announcement
 * @see Announcement
 *
 * @brief Operations for retrieving and modifying Announcement objects.
 */

import('lib.pkp.classes.navigationMenu.NavigationMenu');
import('lib.pkp.classes.navigationMenu.NavigationMenuItem');

class NavigationMenuItemDAO extends DAO {
	/**
	 * Constructor
	 */
	function __construct() {
		parent::__construct();
	}

	/**
	 * Retrieve a navigation menu item by ID.
	 * @param $navigationMenuItemId int
	 * @return NavigationMenuItem
	 */
	function getById($navigationMenuItemId) {
		$params = array((int) $navigationMenuItemId);
		$result = $this->retrieve(
			'SELECT	* FROM navigation_menu_items WHERE navigation_menu_item_id = ?',
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
	 * Retrieve navigation menu items by navigation menu ID.
	 * @param $navigationMenuId int
	 * @return int
	 */
	function getByNavigationMenuId($navigationMenuId) {
		$params = array((int) $navigationMenuId);
		$result = $this->retrieve(
			'SELECT	* FROM navigation_menu_items WHERE navigation_menu_id = ?',
			$params
		);

		return new DAOResultFactory($result, $this, '_fromRow');
	}

	/**
	 * Get the list of localized field names for this table
	 * @return array
	 */
	function getLocaleFieldNames() {
		return array('title');
	}

	/**
	 * Get a new data object.
	 * @return DataObject
	 */
	function newDataObject() {
		return new NavigationMenuItem();
	}

	/**
	 * Internal function to return an Announcement object from a row.
	 * @param $row array
	 * @return Announcement
	 */
	function _fromRow($row) {
		$navigationMenuItem = $this->newDataObject();
		$navigationMenuItem->setId($row['announcement_id']);
		$navigationMenuItem->setAssocType($row['assoc_type']);
		$navigationMenuItem->setAssocId($row['assoc_id']);
		$navigationMenuItem->setTypeId($row['type_id']);
		$navigationMenuItem->setDateExpire($this->datetimeFromDB($row['date_expire']));
		$navigationMenuItem->setDatePosted($this->datetimeFromDB($row['date_posted']));

		$this->getDataObjectSettings('navigation_menu_item_settings', 'navigation_menu_item_id', $row['navigation_menu_item_id'], $navigationMenuItem);

		return $navigationMenuItem;
	}

	/**
	 * Update the settings for this object
	 * @param $announcement object
	 */
	function updateLocaleFields($navigationMenuItem) {
		$this->updateDataObjectSettings('announcement_settings', $navigationMenuItem, array(
			'navigation_menu_item_id' => $navigationMenuItem->getId()
		));
	}

	/**
	 * Insert a new Announcement.
	 * @param $navigationMenuItem NavigationMenuItem
	 * @return int
	 */
	function insertObject($navigationMenuItem) {
		$this->update(
				'INSERT INTO navigation_menu_items
				(navigation_menu_id, seq, assoc_id, path)
				VALUES
				(?, ?, ?, ?)',
			array(
				(int) $navigationMenuItem->getNavigationMenuId(),
				(int) $navigationMenuItem->getSeq(),
				(int) $navigationMenuItem->getAssocId(),
				$navigationMenuItem->getPath()
			)
		);
		$navigationMenuItem->setId($this->getInsertId());
		$this->updateLocaleFields($navigationMenuItem);
		return $navigationMenuItem->getId();
	}

	/**
	 * Update an existing announcement.
	 * @param $navigationMenuItem NavigationMenuItem
	 * @return boolean
	 */
	function updateObject($announcement) {
		$returner = $this->update(
				'UPDATE announcements
				SET
					navigation_menu_id = ?,
					seq = ?,
					assoc_id = ?,
					path = ?
				WHERE announcement_id = ?',
			array(
				(int) $navigationMenuItem->getNavigationMenuId(),
				(int) $navigationMenuItem->getSeq(),
				(int) $navigationMenuItem->getAssocId(),
				$navigationMenuItem->getPath(),
				(int) $navigationMenuItem->getId()
			)
		);
		$this->updateLocaleFields($navigationMenuItem);
		return $returner;
	}

	/**
	 * Delete an announcement.
	 * @param $navigationMenuItem NavigationMenuItem
	 * @return boolean
	 */
	function deleteObject($navigationMenuItem) {
		return $this->deleteById($navigationMenuItem->getId());
	}

	/**
	 * Delete an announcement by announcement ID.
	 * @param $announcementId int
	 * @return boolean
	 */
	function deleteById($navigationMenuItemId) {
		$this->update('DELETE FROM navigation_menu_item_settings WHERE navigation_menu_item_id = ?', (int) $navigationMenuItemId);
		return $this->update('DELETE FROM navigation_menu_items WHERE navigation_menu_item_id = ?', (int) $navigationMenuItemId);
	}

	/**
	 * Delete menu items by menu item ID.
	 * @param $navigationMenuId int Navigation Menu ID
	 * @return boolean
	 */
	function deleteByNavigationMenuId($navigationMenuId) {
		$navigationMenuItems = $this->getByNavigationMenuId($navigationMenuId);
		while ($navigationMenuItem = $navigationMenuItems->next()) {
			$this->deleteObject($navigationMenuItem);
		}
	}

	///**
	// * Delete announcements by Assoc ID
	// * @param $assocType int ASSOC_TYPE_...
	// * @param $assocId int
	// */
	//function deleteByAssoc($assocType, $assocId) {
	//    $announcements = $this->getByAssocId($assocType, $assocId);
	//    while ($announcement = $announcements->next()) {
	//        $this->deleteById($announcement->getId());
	//    }
	//    return true;
	//}

	///**
	// * Retrieve an array of announcements matching a particular assoc ID.
	// * @param $assocType int ASSOC_TYPE_...
	// * @param $assocId int
	// * @param $rangeInfo DBResultRange (optional)
	// * @return object DAOResultFactory containing matching Announcements
	// */
	//function getByAssocId($assocType, $assocId, $rangeInfo = null) {
	//    $result = $this->retrieveRange(
	//        'SELECT *
	//        FROM announcements
	//        WHERE assoc_type = ? AND assoc_id = ?
	//        ORDER BY date_posted DESC',
	//        array((int) $assocType, (int) $assocId),
	//        $rangeInfo
	//    );

	//    return new DAOResultFactory($result, $this, '_fromRow');
	//}

	///**
	// * Retrieve an array of announcements matching a particular type ID.
	// * @param $typeId int
	// * @param $rangeInfo DBResultRange (optional)
	// * @return object DAOResultFactory containing matching Announcements
	// */
	//function getByTypeId($typeId, $rangeInfo = null) {
	//    $result = $this->retrieveRange(
	//        'SELECT * FROM announcements WHERE type_id = ? ORDER BY date_posted DESC',
	//        (int) $typeId,
	//        $rangeInfo
	//    );

	//    return new DAOResultFactory($result, $this, '_fromRow');
	//}

	///**
	// * Retrieve an array of numAnnouncements announcements matching a particular Assoc ID.
	// * @param $assocType int ASSOC_TYPE_...
	// * @param $assocId int
	// * @param $numAnnouncements int Maximum number of announcements
	// * @param $rangeInfo DBResultRange (optional)
	// * @return object DAOResultFactory containing matching Announcements
	// */
	//function getNumAnnouncementsByAssocId($assocType, $assocId, $numAnnouncements, $rangeInfo = null) {
	//    $result = $this->retrieveRange(
	//        'SELECT *
	//        FROM announcements
	//        WHERE assoc_type = ?
	//            AND assoc_id = ?
	//        ORDER BY date_posted DESC LIMIT ?',
	//        array((int) $assocType, (int) $assocId, (int) $numAnnouncements),
	//        $rangeInfo
	//    );

	//    return new DAOResultFactory($result, $this, '_fromRow');
	//}

	/**
	 * Get the ID of the last inserted announcement.
	 * @return int
	 */
	function getInsertId() {
		return $this->_getInsertId('navigation_menu_items', 'navigation_menu_item_id');
	}
}

?>

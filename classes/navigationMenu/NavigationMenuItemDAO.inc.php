<?php

/**
 * @file classes/navigationMenu/NavigationMenuItemDAO.inc.php
 *
 * Copyright (c) 2014-2017 Simon Fraser University
 * Copyright (c) 2000-2017 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class NavigationMenuItemDAO
 * @ingroup navigationMenuItem
 * @see NavigationMenuItem
 *
 * @brief Operations for retrieving and modifying NavigationMenuItem objects. NMI = NavigationMenuItem
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
	 * Retrieve a navigation menu items by context Id.
	 * @param $contextId int Context Id
	 * @return NavigationMenu
	 */
	function getByContextId($contextId) {
		$params = array((int) $contextId);
		$result = $this->retrieve(
			'SELECT * FROM navigation_menu_items WHERE context_id = ?',
			$params
		);

		return new DAOResultFactory($result, $this, '_fromRow');
	}

	/**
	 * Retrieve a navigation menu items without parent by context Id.
	 * @param $contextId int Context Id
	 * @return NavigationMenu
	 */
	function getWithoutParentByContextId($contextId) {
		$params = array((int) $contextId);
		$result = $this->retrieve(
			'SELECT * FROM navigation_menu_items WHERE context_id = ? and navigation_menu_id=0',
			$params
		);

		return new DAOResultFactory($result, $this, '_fromRow');
	}

	/**
	 * Retrieve navigation menu items by navigation menu ID.
	 * @param $navigationMenuId int
	 * @return int
	 */
	function getByNavigationMenuId($navigationMenuId) {
		$params = array((int) $navigationMenuId);
		$result = $this->retrieve(
			'SELECT	* FROM navigation_menu_items WHERE navigation_menu_id = ? order by enabled desc, seq',
			$params
		);

		return new DAOResultFactory($result, $this, '_fromRow');
	}

	/**
	 * Retrieve possible parents of a navigation menu item (other navigation menu Items) by navigation menu ID.
	 * @param $navigationMenuId int
	 * @return int
	 */
	function getPossibleParrentNMIByNavigationMenuId($navigationMenuId, $navigationMenuItemId) {
		$params = array((int) $navigationMenuId, (int) $navigationMenuItemId);
		$result = $this->retrieve(
			'SELECT	* FROM navigation_menu_items WHERE navigation_menu_id = ? and navigation_menu_item_id <> ? and enabled = 1 order by seq',
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
	 * Internal function to return a NavigationMenuItem object from a row.
	 * @param $row array
	 * @return NavigationMenuItem
	 */
	function _fromRow($row) {
		$navigationMenuItem = $this->newDataObject();
		$navigationMenuItem->setId($row['navigation_menu_item_id']);
		$navigationMenuItem->setNavigationMenuId($row['navigation_menu_id']);
		$navigationMenuItem->setAssocId($row['assoc_id']);
		$navigationMenuItem->setSequence($row['seq']);
		$navigationMenuItem->setPath($row['path']);
		$navigationMenuItem->setContextId($row['context_id']);
		$navigationMenuItem->setEnabled($row['enabled']);

		$this->getDataObjectSettings('navigation_menu_item_settings', 'navigation_menu_item_id', $row['navigation_menu_item_id'], $navigationMenuItem);

		return $navigationMenuItem;
	}

	/**
	 * Update the settings for this object
	 * @param $navigationMenuItem object
	 */
	function updateLocaleFields($navigationMenuItem) {
		$this->updateDataObjectSettings('navigation_menu_item_settings', $navigationMenuItem, array(
			'navigation_menu_item_id' => $navigationMenuItem->getId()
		));
	}

	/**
	 * Insert a new NavigationMenuItem.
	 * @param $navigationMenuItem NavigationMenuItem
	 * @return int
	 */
	function insertObject($navigationMenuItem) {
		$this->update(
				'INSERT INTO navigation_menu_items
				(navigation_menu_id, seq, assoc_id, path, defaultmenu, enabled, context_id)
				VALUES
				(?, ?, ?, ?, ?, ?, ?)',
			array(
				(int) $navigationMenuItem->getNavigationMenuId(),
				(int) $navigationMenuItem->getSequence(),
				(int) $navigationMenuItem->getAssocId(),
				$navigationMenuItem->getPath(),
				(int) $navigationMenuItem->getDefaultMenu(),
				(int) $navigationMenuItem->getEnabled(),
				(int) $navigationMenuItem->getContextId()
			)
		);
		$navigationMenuItem->setId($this->getInsertId());
		$this->updateLocaleFields($navigationMenuItem);
		return $navigationMenuItem->getId();
	}

	/**
	 * Update an existing NavigationMenuItem.
	 * @param $navigationMenuItem NavigationMenuItem
	 * @return boolean
	 */
	function updateObject($navigationMenuItem) {
		$returner = $this->update(
				'UPDATE navigation_menu_items
				SET
					navigation_menu_id = ?,
					seq = ?,
					assoc_id = ?,
					path = ?,
					defaultmenu = ?,
					enabled = ?,
					context_id = ?
				WHERE navigation_menu_item_id = ?',
			array(
				(int) $navigationMenuItem->getNavigationMenuId(),
				(int) $navigationMenuItem->getSequence(),
				(int) $navigationMenuItem->getAssocId(),
				$navigationMenuItem->getPath(),
				(int) $navigationMenuItem->getDefaultMenu(),
				(int) $navigationMenuItem->getEnabled(),
				(int) $navigationMenuItem->getContextId(),
				(int) $navigationMenuItem->getId()
			)
		);
		$this->updateLocaleFields($navigationMenuItem);
		return $returner;
	}

	/**
	 * Delete a NavigationMenuItem.
	 * @param $navigationMenuItem NavigationMenuItem
	 * @return boolean
	 */
	function deleteObject($navigationMenuItem) {
		return $this->deleteById($navigationMenuItem->getId());
	}

	/**
	 * Delete a NavigationMenuItem by navigationMenuItem ID.
	 * @param $navigationMenuItemId int
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

	/**
	 * Get the ID of the last inserted navigation menu item.
	 * @return int
	 */
	function getInsertId() {
		return $this->_getInsertId('navigation_menu_items', 'navigation_menu_item_id');
	}
}

?>

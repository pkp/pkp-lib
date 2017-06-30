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
	 * Retrieve a navigation menu item by path.
	 * @param $contextId int Context Id
	 * @param $path string
	 * @return NavigationMenuItem
	 */
	function getByPath($contextId, $path) {
		$params = array($path, (int) $contextId);
		$result = $this->retrieve(
			'SELECT	* FROM navigation_menu_items WHERE path = ? and context_id = ?',
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
	 * Retrieve items by menu id
	 */
	public function getByMenuId($menuId) {
		$params = array((int) $menuId);
		$result = $this->retrieve(
			'SELECT nmi.*
				FROM navigation_menu_item_assignments as nmh
				LEFT JOIN navigation_menu_items as nmi ON (nmh.navigation_menu_item_id = nmi.navigation_menu_item_id)
				WHERE nmh.navigation_menu_id = ?
				ORDER BY nmh.seq',
			$params
		);

		return new DAOResultFactory($result, $this, '_fromRow');
	}

	/**
	 * Get the list of localized field names for this table
	 * @return array
	 */
	function getLocaleFieldNames() {
		return array('title', 'content');
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
	function _fromRow($row, $dataObject = false) {
		$navigationMenuItem = $this->newDataObject();
		$navigationMenuItem->setId($row['navigation_menu_item_id']);
		$navigationMenuItem->setPath($row['path']);
		$navigationMenuItem->setContextId($row['context_id']);
		$navigationMenuItem->setPage($row['page']);

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
				(path, page, defaultmenu, context_id)
				VALUES
				(?, ?, ?, ?)',
			array(
				$navigationMenuItem->getPath(),
				$navigationMenuItem->getPage(),
				(int) $navigationMenuItem->getDefaultMenu(),
				(int) $navigationMenuItem->getContextId(),
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
					path = ?,
					page = ?,
					defaultmenu = ?,
					context_id = ?
				WHERE navigation_menu_item_id = ?',
			array(
				$navigationMenuItem->getPath(),
				$navigationMenuItem->getPage(),
				(int) $navigationMenuItem->getDefaultMenu(),
				(int) $navigationMenuItem->getContextId(),
				(int) $navigationMenuItem->getId(),
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
		return false;
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

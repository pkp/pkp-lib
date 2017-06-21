<?php

/**
 * @file classes/navigationMenu/NavigationMenuHierarchyDAO.inc.php
 *
 * Copyright (c) 2014-2017 Simon Fraser University
 * Copyright (c) 2000-2017 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class NavigationMenuHierarchyDAO
 * @ingroup navigationMenu
 * @see NavigationMenuHierarchy
 *
 * @brief Operations for retrieving and modifying NavigationMenuHierarchy objects.
 */


import('lib.pkp.classes.navigationMenu.NavigationMenuHierarchy');

class NavigationMenuHierarchyDAO extends DAO {
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
		return new NavigationMenuHierarchy();
	}

	/**
	 * Retrieve a navigation menu by navigation menu ID.
	 * @param $navigationMenuHierarchyId int
	 * @return NavigationMenuHierarchy
	 */
	function getById($navigationMenuHierarchyId) {
		$params = array((int) $navigationMenuHierarchyId);
		$result = $this->retrieve(
			'SELECT * FROM navigation_menus_hierarchy WHERE navigation_menu_hierarchy_id = ?',
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
	 * Retrieve navigation menu hierarchy rules by navigation menu ID.
	 * @param $navigationMenuId int
	 * @return DAOResultFactory
	 */
	function getByNavigationMenuId($navigationMenuId) {
		$params = array((int) $navigationMenuId);
		$result = $this->retrieve(
			'SELECT	* FROM navigation_menus_hierarchy WHERE navigation_menu_id = ? order by child_navigation_menu_item_id',
			$params
		);

		return new DAOResultFactory($result, $this, '_fromRow');
	}

	/**
	 * Get the locale field names.
	 * @return array
	 */
	function getLocaleFieldNames() {
	    return array();
	}

	/**
	 * Internal function to return a NavigationMenuHierarchy object from a row.
	 * @param $row array
	 * @return NavigationMenuHierarchy
	 */
	function _fromRow($row) {
		$navigationMenuHierarchy = $this->newDataObject();
		$navigationMenuHierarchy->setId($row['navigation_menu_hierarchy_id']);
		$navigationMenuHierarchy->setNavigationMenuId($row['navigation_menu_id']);
		$navigationMenuHierarchy->setNavigationMenuItemId($row['navigation_menu_item_id']);
		$navigationMenuHierarchy->setChildNavigationMenuItemId($row['child_navigation_menu_item_id']);
		$navigationMenuHierarchy->setSequence($row['seq']);

		return $navigationMenuHierarchy;
	}

	/**
	 * Get a new data object.
	 * @return DataObject
	 */
	function newNMIHierarchyDataObject() {
		return new NavigationMenuHierarchy();
	}

	/**
	 * Delete a NavigationMenuHierarchy.
	 * @param $navigationMenuHierarchy NavigationMenuHierarchy
	 * @return boolean
	 */
	function deleteObject($navigationMenuHierarchy) {
		return $this->deleteById($navigationMenuHierarchy->getId());
	}

	/**
	 * Delete a NavigationMenuHierarchy by navigationMenuHierarchy ID.
	 * @param $navigationMenuHierarchyId int
	 * @return boolean
	 */
	function deleteById($navigationMenuHierarchyId) {
		return $this->update('DELETE FROM navigation_menus_hierarchy WHERE navigation_menu_hierarchy_id = ?', (int) $navigationMenuHierarchyId);
	}

	/**
	 * Insert a new NavigationHierarchy.
	 * @param $navigationMenuHierarchy NavigationMenuHierarchy
	 * @return int
	 */
	function insertObject($navigationMenuHierarchy) {
		$this->update(
				'INSERT INTO navigation_menus_hierarchy
				(navigation_menu_id, navigation_menu_item_id, child_navigation_menu_item_id, seq)
				VALUES
				(?, ?, ?, ?)',
			array(
				(int) $navigationMenuHierarchy->getNavigationMenuId(),
				(int) $navigationMenuHierarchy->getNavigationMenuItemId(),
				(int) $navigationMenuHierarchy->getChildNavigationMenuItemId(),
				(int) $navigationMenuHierarchy->getSequence(),
			)
		);

		$navigationMenuHierarchy->setId($this->getInsertId());

		return $navigationMenuHierarchy->getId();
	}


	/**
	 * Get the ID of the last inserted navigation menu item.
	 * @return int
	 */
	function getInsertId() {
		return $this->_getInsertId('navigation_menus_hierarchy', 'navigation_menu_hierarchy_id');
	}

	/**
	 * Update an existing NavigationMenuHierarchy
	 * @param NavigationMenuHierarchy $navigationMenuHierarchy
	 * @return boolean
	 */
	function updateObject($navigationMenuHierarchy) {
		$returner = $this->update(
			'UPDATE	navigation_menus_hierarchy
			SET	navigation_menu_id = ?,
				navigation_menu_item_id = ?,
				child_navigation_menu_item_id = ?,
				seq = ?,
			WHERE	navigation_menu_hierarchy_id = ?',
			array(
				(int) $navigationMenuHierarchy->getNavigationMenuId(),
				(int) $navigationMenuHierarchy->getNavigationMenuItemId(),
				(int) $navigationMenuHierarchy->getChildNavigationMenuItemId(),
				(int) $navigationMenuHierarchy->getSequence(),
				(int) $navigationMenuHierarchy->getId(),
			)
		);

		return $returner;
	}
}

?>

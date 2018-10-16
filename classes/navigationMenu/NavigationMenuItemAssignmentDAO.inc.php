<?php

/**
 * @file classes/navigationMenu/NavigationMenuItemAssignmentDAO.inc.php
 *
 * Copyright (c) 2014-2018 Simon Fraser University
 * Copyright (c) 2000-2018 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class NavigationMenuItemAssignment
 * @ingroup navigationMenuItem
 * @see NavigationMenuItem
 *
 * @brief Operations for retrieving and modifying NavigationMenuItemAssignment
 *  objects
 */

import('lib.pkp.classes.navigationMenu.NavigationMenu');
import('lib.pkp.classes.navigationMenu.NavigationMenuItem');
import('lib.pkp.classes.navigationMenu.NavigationMenuItemAssignment');

class NavigationMenuItemAssignmentDAO extends DAO {

	/**
	 * Retrieve a navigation menu item assignment by ID.
	 * @param $navigationMenuItemAssignmentId int
	 * @return NavigationMenuItemAssignment
	 */
	function getById($navigationMenuItemAssignmentId) {
		$params = array((int) $navigationMenuItemAssignmentId);
		$result = $this->retrieve(
			'SELECT	* FROM navigation_menu_item_assignments WHERE navigation_menu_item_assignment_id = ?',
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
	 * Get a new data object.
	 * @return NavigationMenuItemAssignment
	 */
	public function newDataObject() {
		return new NavigationMenuItemAssignment();
	}

	/**
	 * Retrieve items by menu id
	 * @param $menuId int
	 */
	public function getByMenuId($menuId) {
		$params = array((int) $menuId);
		$result = $this->retrieve(
			'SELECT nmi.*,nmh.navigation_menu_id,nmh.parent_id,nmh.seq, nmh.navigation_menu_item_assignment_id
				FROM navigation_menu_item_assignments as nmh
				LEFT JOIN navigation_menu_items as nmi ON (nmh.navigation_menu_item_id = nmi.navigation_menu_item_id)
				WHERE nmh.navigation_menu_id = ?
				ORDER BY nmh.seq',
			$params
		);

		return new DAOResultFactory($result, $this, '_fromRow');
	}

	/**
	 * Retrieve items by menu item id
	 *
	 * @param $menuItemId int
	 */
	public function getByMenuItemId($menuItemId) {
		$params = array((int) $menuItemId);
		$result = $this->retrieve(
			'SELECT nmi.*, nmh.navigation_menu_id, nmh.parent_id, nmh.seq, nmh.navigation_menu_item_assignment_id
				FROM navigation_menu_item_assignments as nmh
				LEFT JOIN navigation_menu_items as nmi ON (nmh.navigation_menu_item_id = nmi.navigation_menu_item_id)
				WHERE nmh.navigation_menu_item_id = ?
				ORDER BY nmh.seq',
			$params
		);

		return new DAOResultFactory($result, $this, '_fromRow');
	}

	/**
	 * Retrieve items by navigationMenuItemId menu item id and ParentId
	 * @param $navigationMenuItemId int
	 * @param $menuId int
	 * @param $parentId int
	 */
	public function getByNMIIdAndMenuIdAndParentId($navigationMenuItemId, $menuId, $parentId = null) {
		$params = array(
			(int) $menuId,
			(int) $navigationMenuItemId
		);
		if ($parentId) $params[] = (int) $parentId;

		$result = $this->retrieve(
			'SELECT nmh.*
				FROM navigation_menu_item_assignments as nmh
				WHERE nmh.navigation_menu_id = ?
				AND nmh.navigation_menu_item_id = ?' .
				($parentId?' AND nmh.parent_id = ?':''),
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
	 * Retrieve items by navigationMenu id and ParentId
	 * @param $menuId int
	 * @param $parentId int 0 if we want to return NMIAssignments with no parents
	 */
	public function getByMenuIdAndParentId($menuId, $parentId) {
		$params = array(
			(int) $menuId,
			(int) $parentId
		);

		$result = $this->retrieve(
			'SELECT nmh.*
				FROM navigation_menu_item_assignments as nmh
				WHERE nmh.navigation_menu_id = ?
				AND nmh.parent_id = ?',
			$params
		);

		return new DAOResultFactory($result, $this, '_fromRow');
	}

	/**
	 * Internal function to return a NavigationMenuItemAssignment object from a
	 * row.
	 * @param $row array
	 * @return NavigationMenuItemAssignment
	 */
	public function _fromRow($row) {
		$assignment = $this->newDataObject();
		$assignment->setId($row['navigation_menu_item_assignment_id']);
		$assignment->setMenuId($row['navigation_menu_id']);
		$assignment->setMenuItemId($row['navigation_menu_item_id']);
		$assignment->setParentId($row['parent_id']);
		$assignment->setSequence($row['seq']);

		$this->getDataObjectSettings('navigation_menu_item_assignment_settings', 'navigation_menu_item_assignment_id', $row['navigation_menu_item_assignment_id'], $assignment);

		return $assignment;
	}

	/**
	 * Update an existing NavigationMenuItemAssignment.
	 * @param $navigationMenuItemAssignment NavigationMenuItemAssignment
	 * @return boolean
	 */
	function updateObject($navigationMenuItemAssignment) {
		$returner = $this->update(
				'UPDATE navigation_menu_item_assignments
				SET
					navigation_menu_id = ?,
					navigation_menu_item_id = ?,
					parent_id = ?,
					seq = ?,
				WHERE navigation_menu_item_assignment_id = ?',
			array(
				(int) $navigationMenuItemAssignment->getMenuId(),
				(int) $navigationMenuItemAssignment->getMenuItemId(),
				(int) $navigationMenuItemAssignment->getParentId(),
				(int) $navigationMenuItemAssignment->getSequence(),
				(int) $navigationMenuItemAssignment->getId(),
			)
		);
		$this->updateLocaleFields($navigationMenuItemAssignment);

		$this->unCacheRelatedNavigationMenus($navigationMenuItemAssignment->getId());

		return $returner;
	}

	/**
	 * Insert a new NavigationMenuItemAssignment.
	 * @param $assignment NavigationMenuItemAssignment
	 * @return int
	 */
	public function insertObject($assignment) {
		$this->update(
				'INSERT INTO navigation_menu_item_assignments
				(navigation_menu_id, navigation_menu_item_id, parent_id, seq)
				VALUES
				(?, ?, ?, ?)',
			array(
				(int) $assignment->getMenuId(),
				(int) $assignment->getMenuItemId(),
				(int) $assignment->getParentId(),
				(int) $assignment->getSequence(),
			)
		);

		// Add default title (of the navigationMenuItem)
		$navigationMenuItemDao = DAORegistry::getDAO('NavigationMenuItemDAO');
		$navigationMenuItem = $navigationMenuItemDao->getById($assignment->getMenuItemId());

		$assignment->setTitle($navigationMenuItem->getTitle(null), null);

		$assignment->setId($this->getInsertId());
		$this->updateLocaleFields($assignment);

		$this->unCacheRelatedNavigationMenus($assignment->getId());

		return $assignment->getId();
	}

	/**
	 * Delete all assignments by NavigationMenu ID
	 * @param $menuId NavigationMenu id
	 * @return boolean
	 */
	function deleteByMenuId($menuId) {
		$navigationMenuItemAssignments = $this->getByMenuId($menuId);
		while ($navigationMenuItemAssignment = $navigationMenuItemAssignments->next()) {
			$this->deleteObject($navigationMenuItemAssignment);
		}

		return true;
	}

	/**
	 * Delete all assignments by NavigationMenuItem ID
	 * @param $menuItemId NavigationMenuItem id
	 * @return boolean
	 */
	function deleteByMenuItemId($menuItemId) {
		$navigationMenuItemAssignments = $this->getByMenuItemId($menuItemId);
		while ($navigationMenuItemAssignment = $navigationMenuItemAssignments->next()) {
			$this->deleteObject($navigationMenuItemAssignment);
		}

		return true;
	}

	/**
	 * Delete a NavigationMenuItemAssignment.
	 * @param $navigationMenuItemAssignment NavigationMenuItemAssignment
	 * @return boolean
	 */
	function deleteObject($navigationMenuItemAssignment) {
		return $this->deleteById($navigationMenuItemAssignment->getId());
	}

	/**
	 * Delete a NavigationMenuItemAssignment by NavigationMenuItemAssignment ID.
	 * @param $navigationMenuItemAssignmentId int
	 * @return boolean
	 */
	function deleteById($navigationMenuItemAssignmentId) {
		$this->unCacheRelatedNavigationMenus($navigationMenuItemAssignmentId);

		$this->update('DELETE FROM navigation_menu_item_assignment_settings WHERE navigation_menu_item_assignment_id = ?', (int) $navigationMenuItemAssignmentId);
		$this->update('DELETE FROM navigation_menu_item_assignments WHERE navigation_menu_item_assignment_id = ?', (int) $navigationMenuItemAssignmentId);
	}

	/**
	 * Get the list of localized field names for this table
	 * @return array
	 */
	function getLocaleFieldNames() {
		return array('title');
	}

	/**
	 * Get the ID of the last inserted navigation menu item assignment.
	 * @return int
	 */
	function getInsertId() {
		return $this->_getInsertId('navigation_menu_item_assignments', 'navigation_menu_item_assignment_id');
	}

	/**
	 * Update the settings for this object
	 * @param $navigationMenuItemAssignment object
	 */
	function updateLocaleFields($navigationMenuItemAssignment) {
		$this->updateDataObjectSettings('navigation_menu_item_assignment_settings', $navigationMenuItemAssignment, array(
			'navigation_menu_item_assignment_id' => $navigationMenuItemAssignment->getId()
		));
	}

	/**
	 * Uncache the related NMs to the NMIA with $id
	 * @param mixed $id
	 */
	function unCacheRelatedNavigationMenus($id) {
		$navigationMenuItemAssignment = $this->getById($id);
		if ($navigationMenuItemAssignment) {
			$navigationMenuDao = \DAORegistry::getDAO('NavigationMenuDAO');
			$cache = $navigationMenuDao->getCache($navigationMenuItemAssignment->getMenuId());
			if ($cache) $cache->flush();
		}
	}
}



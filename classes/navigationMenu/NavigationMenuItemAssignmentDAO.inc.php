<?php

/**
 * @file classes/navigationMenu/NavigationMenuItemAssignmentDAO.inc.php
 *
 * Copyright (c) 2014-2017 Simon Fraser University
 * Copyright (c) 2000-2017 John Willinsky
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
	 * Get a new data object.
	 * @return NavigationMenuItemAssignment
	 */
	public function newDataObject() {
		return new NavigationMenuItemAssignment();
	}

	/**
	 * Retrieve items by menu id
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

		$assignment->setId($this->getInsertId());
		$this->updateLocaleFields($assignment);
		return $assignment->getId();
	}

	/**
	 * Delete all assignments by NavigationMenu ID
	 * @param $menuId NavigationMenu id
	 * @return boolean
	 */
	function deleteByMenuId($menuId) {
		return $this->update(
			'DELETE FROM navigation_menu_item_assignments
				WHERE navigation_menu_id = ?',
			(int) $menuId
		);
	}

	/**
	 * Delete all assignments by NavigationMenuItem ID
	 * @param $menuItemId NavigationMenuItem id
	 * @return boolean
	 */
	function deleteByMenuItemId($menuItemId) {
		return $this->update(
			'DELETE FROM navigation_menu_item_assignments
				WHERE navigation_menu_item_id = ? or parent_id = ?',
			array(
				(int) $menuItemId,
				(int) $menuItemId
			)
		);
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
		$this->update('DELETE FROM navigation_menu_item_assignement_settings WHERE navigation_menu_item_assignment_id = ?', (int) $navigationMenuItemAssignmentId);
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
}

?>

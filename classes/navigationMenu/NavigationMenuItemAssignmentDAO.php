<?php

/**
 * @file classes/navigationMenu/NavigationMenuItemAssignmentDAO.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class NavigationMenuItemAssignment
 * @ingroup navigationMenuItem
 *
 * @see NavigationMenuItem
 *
 * @brief Operations for retrieving and modifying NavigationMenuItemAssignment
 *  objects
 */

namespace PKP\navigationMenu;

use PKP\db\DAORegistry;
use PKP\db\DAOResultFactory;

class NavigationMenuItemAssignmentDAO extends \PKP\db\DAO
{
    /**
     * Retrieve a navigation menu item assignment by ID.
     *
     * @param int $navigationMenuItemAssignmentId
     *
     * @return ?NavigationMenuItemAssignment
     */
    public function getById($navigationMenuItemAssignmentId)
    {
        $result = $this->retrieve(
            'SELECT	* FROM navigation_menu_item_assignments WHERE navigation_menu_item_assignment_id = ?',
            [(int) $navigationMenuItemAssignmentId]
        );
        $row = (array) $result->current();
        return $row ? $this->_fromRow($row) : null;
    }

    /**
     * Get a new data object.
     *
     * @return NavigationMenuItemAssignment
     */
    public function newDataObject()
    {
        return new NavigationMenuItemAssignment();
    }

    /**
     * Retrieve items by menu id
     *
     * @param int $menuId
     *
     * @return DAOResultFactory
     */
    public function getByMenuId($menuId)
    {
        $result = $this->retrieve(
            'SELECT nmi.*,nmh.navigation_menu_id,nmh.parent_id,nmh.seq, nmh.navigation_menu_item_assignment_id
				FROM navigation_menu_item_assignments as nmh
				LEFT JOIN navigation_menu_items as nmi ON (nmh.navigation_menu_item_id = nmi.navigation_menu_item_id)
				WHERE nmh.navigation_menu_id = ?
				ORDER BY nmh.seq',
            [(int) $menuId]
        );
        return new DAOResultFactory($result, $this, '_fromRow');
    }

    /**
     * Retrieve items by menu item id
     *
     * @param int $menuItemId
     *
     * @return DAOResultFactory
     */
    public function getByMenuItemId($menuItemId)
    {
        $result = $this->retrieve(
            'SELECT nmi.*, nmh.navigation_menu_id, nmh.parent_id, nmh.seq, nmh.navigation_menu_item_assignment_id
				FROM navigation_menu_item_assignments as nmh
				LEFT JOIN navigation_menu_items as nmi ON (nmh.navigation_menu_item_id = nmi.navigation_menu_item_id)
				WHERE nmh.navigation_menu_item_id = ?
				ORDER BY nmh.seq',
            [(int) $menuItemId]
        );
        return new DAOResultFactory($result, $this, '_fromRow');
    }

    /**
     * Retrieve items by navigationMenuItemId menu item id and ParentId
     *
     * @param int $navigationMenuItemId
     * @param int $menuId
     * @param int $parentId
     *
     * @return NavigationMenuItemAssignment
     */
    public function getByNMIIdAndMenuIdAndParentId($navigationMenuItemId, $menuId, $parentId = null)
    {
        $params = [(int) $menuId, (int) $navigationMenuItemId];
        if ($parentId) {
            $params[] = (int) $parentId;
        }
        $result = $this->retrieve(
            'SELECT nmh.*
				FROM navigation_menu_item_assignments as nmh
				WHERE nmh.navigation_menu_id = ?
				AND nmh.navigation_menu_item_id = ?' .
                ($parentId ? ' AND nmh.parent_id = ?' : ''),
            $params
        );
        $row = (array) $result->current();
        return $row ? $this->_fromRow($row) : null;
    }

    /**
     * Retrieve items by navigationMenu id and ParentId
     *
     * @param int $menuId
     * @param int $parentId 0 if we want to return NMIAssignments with no parents
     */
    public function getByMenuIdAndParentId($menuId, $parentId)
    {
        $result = $this->retrieve(
            'SELECT nmh.*
				FROM navigation_menu_item_assignments as nmh
				WHERE nmh.navigation_menu_id = ?
				AND nmh.parent_id = ?',
            [(int) $menuId, (int) $parentId]
        );
        return new DAOResultFactory($result, $this, '_fromRow');
    }

    /**
     * Internal function to return a NavigationMenuItemAssignment object from a
     * row.
     *
     * @param array $row
     *
     * @return NavigationMenuItemAssignment
     */
    public function _fromRow($row)
    {
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
     *
     * @param NavigationMenuItemAssignment $navigationMenuItemAssignment
     *
     * @return bool
     */
    public function updateObject($navigationMenuItemAssignment)
    {
        $returner = $this->update(
            'UPDATE navigation_menu_item_assignments
			SET
				navigation_menu_id = ?,
				navigation_menu_item_id = ?,
				parent_id = ?,
				seq = ?,
			WHERE navigation_menu_item_assignment_id = ?',
            [
                (int) $navigationMenuItemAssignment->getMenuId(),
                (int) $navigationMenuItemAssignment->getMenuItemId(),
                (int) $navigationMenuItemAssignment->getParentId(),
                (int) $navigationMenuItemAssignment->getSequence(),
                (int) $navigationMenuItemAssignment->getId(),
            ]
        );
        $this->updateLocaleFields($navigationMenuItemAssignment);
        $this->unCacheRelatedNavigationMenus($navigationMenuItemAssignment->getId());
        return (bool) $returner;
    }

    /**
     * Insert a new NavigationMenuItemAssignment.
     *
     * @param NavigationMenuItemAssignment $assignment
     *
     * @return int
     */
    public function insertObject($assignment)
    {
        $this->update(
            'INSERT INTO navigation_menu_item_assignments
			(navigation_menu_id, navigation_menu_item_id, parent_id, seq)
			VALUES
			(?, ?, ?, ?)',
            [
                (int) $assignment->getMenuId(),
                (int) $assignment->getMenuItemId(),
                (int) $assignment->getParentId(),
                (int) $assignment->getSequence(),
            ]
        );

        $assignment->setId($this->getInsertId());

        // Add default title (of the navigationMenuItem)
        $navigationMenuItemDao = DAORegistry::getDAO('NavigationMenuItemDAO'); /** @var NavigationMenuItemDAO $navigationMenuItemDao */
        $navigationMenuItem = $navigationMenuItemDao->getById($assignment->getMenuItemId());
        $assignment->setTitle($navigationMenuItem->getTitle(null), null);
        $this->updateLocaleFields($assignment);

        $this->unCacheRelatedNavigationMenus($assignment->getId());

        return $assignment->getId();
    }

    /**
     * Delete all assignments by NavigationMenu ID
     *
     * @param NavigationMenu $menuId id
     *
     * @return bool
     */
    public function deleteByMenuId($menuId)
    {
        $navigationMenuItemAssignments = $this->getByMenuId($menuId);
        while ($navigationMenuItemAssignment = $navigationMenuItemAssignments->next()) {
            $this->deleteObject($navigationMenuItemAssignment);
        }

        return true;
    }

    /**
     * Delete all assignments by NavigationMenuItem ID
     *
     * @param NavigationMenuItem $menuItemId id
     *
     * @return bool
     */
    public function deleteByMenuItemId($menuItemId)
    {
        $navigationMenuItemAssignments = $this->getByMenuItemId($menuItemId);
        while ($navigationMenuItemAssignment = $navigationMenuItemAssignments->next()) {
            $this->deleteObject($navigationMenuItemAssignment);
        }

        return true;
    }

    /**
     * Delete a NavigationMenuItemAssignment.
     *
     * @param NavigationMenuItemAssignment $navigationMenuItemAssignment
     *
     * @return bool
     */
    public function deleteObject($navigationMenuItemAssignment)
    {
        return $this->deleteById($navigationMenuItemAssignment->getId());
    }

    /**
     * Delete a NavigationMenuItemAssignment by NavigationMenuItemAssignment ID.
     *
     * @param int $navigationMenuItemAssignmentId
     *
     * @return bool
     */
    public function deleteById($navigationMenuItemAssignmentId)
    {
        $this->unCacheRelatedNavigationMenus($navigationMenuItemAssignmentId);

        $this->update('DELETE FROM navigation_menu_item_assignment_settings WHERE navigation_menu_item_assignment_id = ?', [(int) $navigationMenuItemAssignmentId]);
        $this->update('DELETE FROM navigation_menu_item_assignments WHERE navigation_menu_item_assignment_id = ?', [(int) $navigationMenuItemAssignmentId]);
    }

    /**
     * Get the list of localized field names for this table
     *
     * @return array
     */
    public function getLocaleFieldNames()
    {
        return ['title'];
    }

    /**
     * Get the ID of the last inserted navigation menu item assignment.
     *
     * @return int
     */
    public function getInsertId()
    {
        return $this->_getInsertId('navigation_menu_item_assignments', 'navigation_menu_item_assignment_id');
    }

    /**
     * Update the settings for this object
     *
     * @param object $navigationMenuItemAssignment
     */
    public function updateLocaleFields($navigationMenuItemAssignment)
    {
        $this->updateDataObjectSettings('navigation_menu_item_assignment_settings', $navigationMenuItemAssignment, [
            'navigation_menu_item_assignment_id' => $navigationMenuItemAssignment->getId()
        ]);
    }

    /**
     * Uncache the related NMs to the NMIA with $id
     */
    public function unCacheRelatedNavigationMenus($id)
    {
        if ($navigationMenuItemAssignment = $this->getById($id)) {
            $navigationMenuDao = DAORegistry::getDAO('NavigationMenuDAO');
            $cache = $navigationMenuDao->getCache($navigationMenuItemAssignment->getMenuId());
            if ($cache) {
                $cache->flush();
            }
        }
    }
}

if (!PKP_STRICT_MODE) {
    class_alias('\PKP\navigationMenu\NavigationMenuItemAssignmentDAO', '\NavigationMenuItemAssignmentDAO');
}

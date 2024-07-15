<?php

/**
 * @file classes/navigationMenu/NavigationMenuItemAssignmentDAO.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class NavigationMenuItemAssignment
 *
 * @ingroup navigationMenuItem
 *
 * @see NavigationMenuItem
 *
 * @brief Operations for retrieving and modifying NavigationMenuItemAssignment
 *  objects
 */

namespace PKP\navigationMenu;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use PKP\db\DAORegistry;
use PKP\db\DAOResultFactory;

class NavigationMenuItemAssignmentDAO extends \PKP\db\DAO
{
    /**
     * Retrieve a navigation menu item assignment by ID.
     */
    public function getById(int $navigationMenuItemAssignmentId): ?NavigationMenuItemAssignment
    {
        $result = $this->retrieve(
            'SELECT * FROM navigation_menu_item_assignments WHERE navigation_menu_item_assignment_id = ?',
            [$navigationMenuItemAssignmentId]
        );
        $row = (array) $result->current();
        return $row ? $this->_fromRow($row) : null;
    }

    /**
     * Get a new data object.
     */
    public function newDataObject(): NavigationMenuItemAssignment
    {
        return new NavigationMenuItemAssignment();
    }

    /**
     * Retrieve items by menu id
     *
     * @return DAOResultFactory<NavigationMenuItemAssignment>
     */
    public function getByMenuId(int $navigationMenuId): DAOResultFactory
    {
        $result = $this->retrieve(
            'SELECT nmi.*,nmh.navigation_menu_id,nmh.parent_id,nmh.seq, nmh.navigation_menu_item_assignment_id
				FROM navigation_menu_item_assignments as nmh
				LEFT JOIN navigation_menu_items as nmi ON (nmh.navigation_menu_item_id = nmi.navigation_menu_item_id)
				WHERE nmh.navigation_menu_id = ?
				ORDER BY nmh.seq',
            [$navigationMenuId]
        );
        return new DAOResultFactory($result, $this, '_fromRow');
    }

    /**
     * Retrieve items by menu item id
     *
     * @return DAOResultFactory<NavigationMenuItemAssignment>
     */
    public function getByMenuItemId(int $menuItemId): DAOResultFactory
    {
        $result = $this->retrieve(
            'SELECT nmi.*, nmh.navigation_menu_id, nmh.parent_id, nmh.seq, nmh.navigation_menu_item_assignment_id
				FROM navigation_menu_item_assignments as nmh
				LEFT JOIN navigation_menu_items as nmi ON (nmh.navigation_menu_item_id = nmi.navigation_menu_item_id)
				WHERE nmh.navigation_menu_item_id = ?
				ORDER BY nmh.seq',
            [$menuItemId]
        );
        return new DAOResultFactory($result, $this, '_fromRow');
    }

    /**
     * Retrieve items by navigationMenuItemId menu item id and ParentId
     */
    public function getByNMIIdAndMenuIdAndParentId(int $navigationMenuItemId, int $menuId, ?int $parentId = null): ?NavigationMenuItemAssignment
    {
        $params = [$menuId, $navigationMenuItemId];
        if ($parentId !== null) {
            $params[] = (int) $parentId;
        }
        $result = $this->retrieve(
            'SELECT nmh.*
				FROM navigation_menu_item_assignments as nmh
				WHERE nmh.navigation_menu_id = ?
				AND nmh.navigation_menu_item_id = ?' .
                ($parentId !== null ? ' AND nmh.parent_id = ?' : ''),
            $params
        );
        $row = (array) $result->current();
        return $row ? $this->_fromRow($row) : null;
    }

    /**
     * Retrieve items by navigationMenu id and ParentId
     *
     * @return DAOResultFactory<NavigationMenuItemAssignment>
     */
    public function getByMenuIdAndParentId(int $menuId, ?int $parentId): DAOResultFactory
    {
        $result = $this->retrieve(
            'SELECT nmh.*
            FROM navigation_menu_item_assignments as nmh
            WHERE nmh.navigation_menu_id = ?
            AND COALESCE(nmh.parent_id, 0) = ?
            ORDER BY nmh.seq',
            [$menuId, (int) $parentId]
        );
        return new DAOResultFactory($result, $this, '_fromRow');
    }

    /**
     * Internal function to return a NavigationMenuItemAssignment object from a
     * row.
     */
    public function _fromRow(array $row): NavigationMenuItemAssignment
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
     */
    public function updateObject(NavigationMenuItemAssignment $navigationMenuItemAssignment): bool
    {
        $returner = $this->update(
            'UPDATE navigation_menu_item_assignments
			SET
				navigation_menu_id = ?,
				navigation_menu_item_id = ?,
				parent_id = ?,
				seq = ?
			WHERE navigation_menu_item_assignment_id = ?',
            [
                $navigationMenuItemAssignment->getMenuId(),
                $navigationMenuItemAssignment->getMenuItemId(),
                $navigationMenuItemAssignment->getParentId(),
                $navigationMenuItemAssignment->getSequence(),
                $navigationMenuItemAssignment->getId(),
            ]
        );
        $this->updateLocaleFields($navigationMenuItemAssignment);
        $this->unCacheRelatedNavigationMenus($navigationMenuItemAssignment->getId());
        return (bool) $returner;
    }

    /**
     * Insert a new NavigationMenuItemAssignment.
     */
    public function insertObject(NavigationMenuItemAssignment $assignment): int
    {
        $this->update(
            'INSERT INTO navigation_menu_item_assignments
			(navigation_menu_id, navigation_menu_item_id, parent_id, seq)
			VALUES
			(?, ?, ?, ?)',
            [
                $assignment->getMenuId(),
                $assignment->getMenuItemId(),
                $assignment->getParentId(),
                $assignment->getSequence(),
            ]
        );
        $assignment->setId($this->getInsertId());

        // Add default title (of the navigationMenuItem)
        $navigationMenuItemDao = DAORegistry::getDAO('NavigationMenuItemDAO'); /** @var NavigationMenuItemDAO $navigationMenuItemDao */
        $navigationMenuItem = $navigationMenuItemDao->getById($assignment->getMenuItemId());

        $assignment->setTitle($navigationMenuItem->getTitle(null) ?? [], null);

        $this->updateLocaleFields($assignment);

        $this->unCacheRelatedNavigationMenus($assignment->getId());

        return $assignment->getId();
    }

    /**
     * Delete all assignments by NavigationMenu ID
     */
    public function deleteByMenuId(int $navigationMenuId): bool
    {
        $navigationMenuItemAssignments = $this->getByMenuId($navigationMenuId);
        while ($navigationMenuItemAssignment = $navigationMenuItemAssignments->next()) {
            $this->deleteObject($navigationMenuItemAssignment);
        }

        return true;
    }

    /**
     * Delete all assignments by NavigationMenuItem ID
     */
    public function deleteByMenuItemId(int $navigationMenuItemId): void
    {
        $navigationMenuItemAssignments = $this->getByMenuItemId($navigationMenuItemId);
        while ($navigationMenuItemAssignment = $navigationMenuItemAssignments->next()) {
            $this->deleteObject($navigationMenuItemAssignment);
        }
    }

    /**
     * Delete a NavigationMenuItemAssignment.
     */
    public function deleteObject(NavigationMenuItemAssignment $navigationMenuItemAssignment): void
    {
        $this->deleteById($navigationMenuItemAssignment->getId());
    }

    /**
     * Delete a NavigationMenuItemAssignment by NavigationMenuItemAssignment ID.
     */
    public function deleteById(int $navigationMenuItemAssignmentId): int
    {
        $this->unCacheRelatedNavigationMenus($navigationMenuItemAssignmentId);

        return DB::table('navigation_menu_item_assignments')
            ->where('navigation_menu_item_assignment_id', '=', $navigationMenuItemAssignmentId)
            ->delete();
    }

    /**
     * Get the list of localized field names for this table
     */
    public function getLocaleFieldNames(): array
    {
        return ['title'];
    }

    /**
     * Update the settings for this object
     */
    public function updateLocaleFields(NavigationMenuItemAssignment $navigationMenuItemAssignment): void
    {
        $this->updateDataObjectSettings('navigation_menu_item_assignment_settings', $navigationMenuItemAssignment, [
            'navigation_menu_item_assignment_id' => $navigationMenuItemAssignment->getId()
        ]);
    }

    /**
     * Uncache the related NMs to the NMIA with $id
     */
    public function unCacheRelatedNavigationMenus(int $navigationMenuItemAssignmentId): void
    {
        if ($navigationMenuItemAssignment = $this->getById($navigationMenuItemAssignmentId)) {
            Cache::forget("navigationMenu-{$navigationMenuItemAssignment->getMenuId()}");
        }
    }
}

if (!PKP_STRICT_MODE) {
    class_alias('\PKP\navigationMenu\NavigationMenuItemAssignmentDAO', '\NavigationMenuItemAssignmentDAO');
}

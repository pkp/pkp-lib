<?php

/**
 * @file classes/migration/upgrade/v3_5_0/I9860_EditorialMastheadNavMenuItem.php
 *
 * Copyright (c) 2024 Simon Fraser University
 * Copyright (c) 2024 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class I9860_EditorialMastheadNavMenuItem
 *
 * @brief Add editorial masthead navigation menu item under about menu item in the primary navigation menu.
 */

namespace PKP\migration\upgrade\v3_5_0;

use PKP\db\DAORegistry;
use PKP\install\DowngradeNotSupportedException;
use PKP\migration\Migration;
use PKP\navigationMenu\NavigationMenuItem;
use PKP\navigationMenu\NavigationMenuItemAssignmentDAO;
use PKP\navigationMenu\NavigationMenuItemDAO;

class I9860_EditorialMastheadNavMenuItem extends Migration
{
    /**
     * Run the migration.
     */
    public function up(): void
    {
        $navigationMenuItemDao = DAORegistry::getDAO('NavigationMenuItemDAO'); /** @var NavigationMenuItemDAO $navigationMenuItemDao */
        $navigationMenuItemAssignmentDao = DAORegistry::getDAO('NavigationMenuItemAssignmentDAO'); /** @var NavigationMenuItemAssignmentDAO $navigationMenuItemAssignmentDao */

        $contextIds = app()->get('context')->getIds();
        foreach ($contextIds as $contextId) {
            // Create and insert new Editorial Masthead navigation menu item
            $navigationMenuItem = $navigationMenuItemDao->newDataObject();
            $navigationMenuItem->setTitleLocaleKey('common.editorialMasthead');
            $navigationMenuItem->setContextId($contextId);
            $navigationMenuItem->setType(NavigationMenuItem::NMI_TYPE_MASTHEAD);
            $editorialMastheadNavMenuItemId = $navigationMenuItemDao->insertObject($navigationMenuItem);

            $mainAboutNavMenuItemId = $mainAboutNavMenuId = null;
            // Try to find the About navigation menu item
            $aboutNavMenuItems = $navigationMenuItemDao->getByType(NavigationMenuItem::NMI_TYPE_ABOUT, $contextId);
            while ($aboutNavMenuItem = $aboutNavMenuItems->next()) {
                // Get all assignments for the nav menu item
                $aboutNavMenuItemAssignments = $navigationMenuItemAssignmentDao->getByMenuItemId($aboutNavMenuItem->getId());
                while ($aboutNavMenuItemAssignment = $aboutNavMenuItemAssignments->next()) {
                    // Find the assignment with no parent
                    if (!$aboutNavMenuItemAssignment->getParentId()) {
                        $mainAboutNavMenuItemId = $aboutNavMenuItemAssignment->getMenuItemId();
                        $mainAboutNavMenuId = $aboutNavMenuItemAssignment->getMenuId();
                        break 2;
                    }
                }
            }
            if ($mainAboutNavMenuItemId && $mainAboutNavMenuId) {
                // Create new Editorial Masthead nav menu item assignment. The parent is the main About nav menu item with no parent.
                $editorialMastheadMenuItemAssignment = $navigationMenuItemAssignmentDao->newDataObject();
                $editorialMastheadMenuItemAssignment->setMenuId($mainAboutNavMenuId);
                $editorialMastheadMenuItemAssignment->setMenuItemId($editorialMastheadNavMenuItemId);
                $editorialMastheadMenuItemAssignment->setParentId($mainAboutNavMenuItemId);

                // Resequence all main About nav menu children, so that the new Editorial Masthead is on the third place (considering the default About nav menu and its order).
                $seq = 0;
                $allAboutNavMenuChildren = $navigationMenuItemAssignmentDao->getByMenuIdAndParentId($mainAboutNavMenuId, $mainAboutNavMenuItemId);
                while ($aboutNavMenuChild = $allAboutNavMenuChildren->next()) {
                    if ($seq == 2) {
                        $editorialMastheadMenuItemAssignment->setSequence($seq);
                    } else {
                        $aboutNavMenuChild->setSequence($seq);
                        $navigationMenuItemAssignmentDao->updateObject($aboutNavMenuChild);
                    }
                    $seq++;
                }
                $navigationMenuItemAssignmentDao->insertObject($editorialMastheadMenuItemAssignment);
            }
        }

    }

    /**
     * Reverse the migration
     */
    public function down(): void
    {
        throw new DowngradeNotSupportedException();
    }
}

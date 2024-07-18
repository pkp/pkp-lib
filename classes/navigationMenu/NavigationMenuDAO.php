<?php

/**
 * @file classes/navigationMenu/NavigationMenuDAO.php
 *
 * Copyright (c) 2014-2024 Simon Fraser University
 * Copyright (c) 2000-2024 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class NavigationMenuDAO
 *
 * @see NavigationMenu
 *
 * @brief Operations for retrieving and modifying NavigationMenu objects.
 */

namespace PKP\navigationMenu;

use APP\core\Application;
use Illuminate\Support\Facades\Cache;
use PKP\db\DAORegistry;
use PKP\db\DAOResultFactory;
use PKP\site\SiteDAO;
use PKP\xml\PKPXMLParser;

class NavigationMenuDAO extends \PKP\db\DAO
{
    /**
     * Generate a new data object.
     */
    public function newDataObject(): NavigationMenu
    {
        return new NavigationMenu();
    }

    /**
     * Retrieve a navigation menu by navigation menu ID.
     */
    public function getById(int $navigationMenuId, ?int $contextId = Application::SITE_CONTEXT_ID_ALL)
    {
        $params = [(int) $navigationMenuId];
        if ($contextId !== Application::SITE_CONTEXT_ID_ALL) {
            $params[] = (int) $contextId;
        }
        $result = $this->retrieve(
            'SELECT * FROM navigation_menus WHERE navigation_menu_id = ?' .
            ($contextId !== Application::SITE_CONTEXT_ID_ALL ? ' AND COALESCE(context_id, 0) = ?' : ''),
            $params
        );

        $row = (array) $result->current();
        return $row ? $this->_fromRow($row) : null;
    }

    /**
     * Retrieve a navigation menu by context Id.
     *
     * @return DAOResultFactory<NavigationMenu>
     */
    public function getByContextId(?int $contextId): DAOResultFactory
    {
        $result = $this->retrieve('SELECT * FROM navigation_menus WHERE COALESCE(context_id, 0) = ?', [(int) $contextId]);
        return new DAOResultFactory($result, $this, '_fromRow');
    }

    /**
     * Retrieve a navigation menu by navigation menu area.
     *
     * @return DAOResultFactory<NavigationMenu>
     */
    public function getByArea(?int $contextId, string $areaName): DAOResultFactory
    {
        $result = $this->retrieve('SELECT * FROM navigation_menus WHERE area_name = ? AND COALESCE(context_id, 0) = ?', [$areaName, (int) $contextId]);
        return new DAOResultFactory($result, $this, '_fromRow');
    }

    /**
     * Retrieve a navigation menu by title
     */
    public function getByTitle(?int $contextId, string $title): ?NavigationMenu
    {
        $result = $this->retrieve('SELECT * FROM navigation_menus WHERE COALESCE(context_id, 0) = ? AND title = ?', [(int) $contextId, $title]);
        $row = (array) $result->current();
        return $row ? $this->_fromRow($row) : null;
    }

    /**
     * Check if a navigationMenu exists with the given title.
     */
    public function navigationMenuExistsByTitle(?int $contextId, string $title): bool
    {
        $result = $this->retrieve('SELECT COUNT(*) AS row_count FROM navigation_menus WHERE title = ? AND COALESCE(context_id, 0) = ?', [$title, (int) $contextId]);
        $row = (array) $result->current();
        return $row && $row['row_count'] != 0;
    }

    /**
     * Get the locale field names.
     */
    public function getLocaleFieldNames(): array
    {
        return [];
    }

    /**
     * Internal function to return an NavigationMenu object from a row.
     */
    public function _fromRow(array $row): NavigationMenu
    {
        $navigationMenu = $this->newDataObject();
        $navigationMenu->setId($row['navigation_menu_id']);
        $navigationMenu->setTitle($row['title']);
        $navigationMenu->setAreaName($row['area_name']);
        $navigationMenu->setContextId($row['context_id']);

        return $navigationMenu;
    }

    /**
     * Insert a new NavigationMenu.
     */
    public function insertObject(NavigationMenu $navigationMenu): int
    {
        $this->update(
            'INSERT INTO navigation_menus (title, area_name, context_id) VALUES (?, ?, ?)',
            [$navigationMenu->getTitle(), $navigationMenu->getAreaName(), $navigationMenu->getContextId()]
        );
        $navigationMenu->setId($this->getInsertId());
        return $navigationMenu->getId();
    }

    /**
     * Update an existing NavigationMenu
     */
    public function updateObject(NavigationMenu $navigationMenu): bool
    {
        Cache::forget("navigationMenu-{$navigationMenu->getId()}");
        return (bool) $this->update(
            'UPDATE	navigation_menus
			SET	title = ?,
				area_name = ?,
				context_id = ?
			WHERE	navigation_menu_id = ?',
            [
                $navigationMenu->getTitle(),
                $navigationMenu->getAreaName(),
                $navigationMenu->getContextId(),
                (int) $navigationMenu->getId(),
            ]
        );
    }

    /**
     * Delete a NavigationMenu.
     */
    public function deleteObject($navigationMenu)
    {
        return $this->deleteById($navigationMenu->getId());
    }

    /**
     * Delete a NavigationMenu.
     */
    public function deleteById(int $navigationMenuId): int
    {
        Cache::forget("navigationMenu-{$navigationMenuId}");
        return DB::table('navigation_menus')
            ->where('navigation_menu_id', '=', $navigationMenuId)
            ->delete();
    }

    /**
     * Delete NavigationMenus by contextId.
     */
    public function deleteByContextId(?int $contextId)
    {
        $navigationMenus = $this->getByContextId($contextId);
        while ($navigationMenu = $navigationMenus->next()) {
            $this->deleteObject($navigationMenu);
        }
    }

    /**
     * Load the XML file and move the settings to the DB
     */
    public function installSettings(?int $contextId, string $filename): bool
    {
        $xmlParser = new PKPXMLParser();
        $tree = $xmlParser->parse($filename);

        if ($contextId == Application::SITE_CONTEXT_ID) {
            $siteDao = DAORegistry::getDAO('SiteDAO'); /** @var SiteDAO $siteDao */
            $site = $siteDao->getSite();
        }
        if (!$tree) {
            return false;
        }

        foreach ($tree->getChildren() as $navigationMenuNode) {
            $site = $navigationMenuNode->getAttribute('site');
            if ($contextId == Application::SITE_CONTEXT_ID && !$site) {
                continue;
            }

            if ($navigationMenuNode->name == 'navigationMenu') {
                $title = $navigationMenuNode->getAttribute('title');
                $area = $navigationMenuNode->getAttribute('area');

                $navigationMenu = null;

                // Check if the given area has a NM attached.
                // If it does the NM is not being processed and a warning is being thrown
                $navigationMenusWithArea = $this->getByArea($contextId, $area)->toArray();
                if (count($navigationMenusWithArea) != 0) {
                    error_log("WARNING: The NavigationMenu (ContextId: {$contextId}, Title: {$title}, Area: {$area}) will be skipped because the specified area has already a NavigationMenu attached.");
                    continue;
                }

                if ($this->navigationMenuExistsByTitle($contextId, $title)) {
                    $navigationMenu = $this->getByTitle($contextId, $title);
                    $navigationMenu->setAreaName($area);

                    // update the navigationMenu into the DB
                    $navigationMenuId = $this->updateObject($navigationMenu);
                } else {
                    $navigationMenu = $this->newDataObject();
                    $navigationMenu->setTitle($title);
                    $navigationMenu->setContextId($contextId);
                    $navigationMenu->setAreaName($area);

                    // insert the navigationMenu into the DB
                    $navigationMenuId = $this->insertObject($navigationMenu);
                    $navigationMenu->setId($navigationMenuId);
                }

                $seq = 0;
                foreach ($navigationMenuNode->getChildren() as $navigationMenuItemFirstLevelNode) {
                    $navigationMenuItemDao = DAORegistry::getDAO('NavigationMenuItemDAO'); /** @var NavigationMenuItemDAO $navigationMenuItemDao */
                    $navigationMenuItemDao->installNodeSettings($contextId, $navigationMenuItemFirstLevelNode, $navigationMenu->getId(), null, $seq, true);

                    $seq++;
                }
            } elseif ($navigationMenuNode->name == 'navigationMenuItem') {
                $navigationMenuItemDao = DAORegistry::getDAO('NavigationMenuItemDAO'); /** @var NavigationMenuItemDAO $navigationMenuItemDao */
                $navigationMenuItemDao->installNodeSettings($contextId, $navigationMenuNode, null, null, 0, true);
            }
        }

        return true;
    }
}

if (!PKP_STRICT_MODE) {
    class_alias('\PKP\navigationMenu\NavigationMenuDAO', '\NavigationMenuDAO');
}

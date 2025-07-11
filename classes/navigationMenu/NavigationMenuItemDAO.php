<?php

/**
 * @file classes/navigationMenu/NavigationMenuItemDAO.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class NavigationMenuItemDAO
 *
 * @ingroup navigationMenuItem
 *
 * @see NavigationMenuItem
 *
 * @brief Operations for retrieving and modifying NavigationMenuItem objects. NMI = NavigationMenuItem
 */

namespace PKP\navigationMenu;

use APP\core\Application;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use PKP\db\DAORegistry;
use PKP\db\DAOResultFactory;
use PKP\xml\PKPXMLParser;
use PKP\xml\XMLNode;

class NavigationMenuItemDAO extends \PKP\db\DAO
{
    /**
     * Retrieve a navigation menu item by ID.
     */
    public function getById(int $navigationMenuItemId): ?NavigationMenuItem
    {
        $result = $this->retrieve(
            'SELECT	* FROM navigation_menu_items WHERE navigation_menu_item_id = ?',
            [(int) $navigationMenuItemId]
        );

        $row = (array) $result->current();
        return $row ? $this->_fromRow($row) : null;
    }

    /**
     * Retrieve a navigation menu item by path.
     */
    public function getByPath(?int $contextId, string $path): ?NavigationMenuItem
    {
        $result = $this->retrieve(
            'SELECT * FROM navigation_menu_items WHERE path = ? AND COALESCE(context_id, 0) = ? AND type = ?',
            [$path, (int) $contextId, 'NMI_TYPE_CUSTOM']
        );

        $row = (array) $result->current();
        return $row ? $this->_fromRow($row) : null;
    }

    /**
     * Retrieve a navigation menu items by context Id.
     *
     * @return DAOResultFactory<NavigationMenuItem>
     */
    public function getByContextId(?int $contextId): DAOResultFactory
    {
        $result = $this->retrieve(
            'SELECT * FROM navigation_menu_items WHERE COALESCE(context_id, 0) = ?',
            [(int) $contextId]
        );

        return new DAOResultFactory($result, $this, '_fromRow');
    }

    /**
     * Retrieve items by menu id
     *
     * @return DAOResultFactory<NavigationMenuItem>
     */
    public function getByMenuId(int $navigationMenuId): DAOResultFactory
    {
        $result = $this->retrieve(
            'SELECT nmi.*
				FROM navigation_menu_item_assignments as nmh
				LEFT JOIN navigation_menu_items as nmi ON (nmh.navigation_menu_item_id = nmi.navigation_menu_item_id)
				WHERE nmh.navigation_menu_id = ?
				ORDER BY nmh.seq',
            [$navigationMenuId]
        );
        return new DAOResultFactory($result, $this, '_fromRow');
    }

    /**
     * Retrieve items by menuItemType and setting_name = titleLocaleKey
     */
    public function getByTypeAndTitleLocaleKey(?int $contextId, string $menuItemType, string $menuItemTitleLocaleKey): ?NavigationMenuItem
    {
        $result = $this->retrieve(
            'SELECT *
            FROM navigation_menu_items
            LEFT JOIN navigation_menu_item_settings ON (navigation_menu_items.navigation_menu_item_id = navigation_menu_item_settings.navigation_menu_item_id)
            WHERE navigation_menu_items.type = ?
            AND (navigation_menu_item_settings.setting_name = \'titleLocaleKey\' and navigation_menu_item_settings.setting_value = ?)
            AND COALESCE(navigation_menu_items.context_id, 0) = ?',
            [$menuItemType, $menuItemTitleLocaleKey, (int) $contextId]
        );
        $row = (array) $result->current();
        return $row ? $this->_fromRow($row) : null;
    }

    /**
     * Retrieve the menu items with the specified type (NMI_TYPE_...).
     *
     * @return DAOResultFactory<NavigationMenuItem>
     */
    public function getByType(string $type, ?int $contextId = Application::SITE_CONTEXT_ID_ALL): DAOResultFactory
    {
        $params = [$type];
        if ($contextId !== Application::SITE_CONTEXT_ID_ALL) {
            $params[] = (int) $contextId;
        }
        $result = $this->retrieve(
            'SELECT * FROM navigation_menu_items WHERE type = ?' .
            ($contextId !== Application::SITE_CONTEXT_ID_ALL ? ' AND COALESCE(context_id, 0) = ?' : ''),
            $params
        );
        return new DAOResultFactory($result, $this, '_fromRow');
    }

    /**
     * Get the list of localized field names for this table
     */
    public function getLocaleFieldNames(): array
    {
        return ['title', 'content', 'remoteUrl'];
    }

    /**
     * @copydoc DAO::getAdditionalFieldNames()
     */
    public function getAdditionalFieldNames(): array
    {
        return ['titleLocaleKey'];
    }

    /**
     * Get a new data object.
     */
    public function newDataObject(): NavigationMenuItem
    {
        return new NavigationMenuItem();
    }

    /**
     * Internal function to return a NavigationMenuItem object from a row.
     */
    public function _fromRow(array $row): NavigationMenuItem
    {
        $navigationMenuItem = $this->newDataObject();
        $navigationMenuItem->setId($row['navigation_menu_item_id']);
        $navigationMenuItem->setContextId($row['context_id']);
        $navigationMenuItem->setType($row['type']);
        $navigationMenuItem->setPath($row['path']);

        $this->getDataObjectSettings('navigation_menu_item_settings', 'navigation_menu_item_id', $row['navigation_menu_item_id'], $navigationMenuItem);

        return $navigationMenuItem;
    }

    /**
     * Update the settings for this object
     */
    public function updateLocaleFields(NavigationMenuItem $navigationMenuItem): void
    {
        $this->updateDataObjectSettings('navigation_menu_item_settings', $navigationMenuItem, [
            'navigation_menu_item_id' => $navigationMenuItem->getId()
        ]);
    }

    /**
     * Insert a new NavigationMenuItem.
     */
    public function insertObject(NavigationMenuItem $navigationMenuItem): int
    {
        $this->update(
            'INSERT INTO navigation_menu_items
				(path, context_id, type)
				VALUES
				(?, ?, ?)',
            [
                $navigationMenuItem->getPath(),
                $navigationMenuItem->getContextId(),
                $navigationMenuItem->getType(),
            ]
        );
        $navigationMenuItem->setId($this->getInsertId());
        $this->updateLocaleFields($navigationMenuItem);

        $this->unCacheRelatedNavigationMenus($navigationMenuItem->getId());

        return $navigationMenuItem->getId();
    }

    /**
     * Update an existing NavigationMenuItem.
     */
    public function updateObject(NavigationMenuItem $navigationMenuItem): bool
    {
        $returner = $this->update(
            'UPDATE navigation_menu_items
				SET
					path = ?,
					context_id = ?,
					type = ?
				WHERE navigation_menu_item_id = ?',
            [
                $navigationMenuItem->getPath(),
                $navigationMenuItem->getContextId(),
                $navigationMenuItem->getType(),
                (int) $navigationMenuItem->getId(),
            ]
        );
        $this->updateLocaleFields($navigationMenuItem);

        $this->unCacheRelatedNavigationMenus($navigationMenuItem->getId());

        return (bool) $returner;
    }

    /**
     * Delete a NavigationMenuItem.
     */
    public function deleteObject(NavigationMenuItem $navigationMenuItem): void
    {
        $this->deleteById($navigationMenuItem->getId());
    }

    /**
     * Delete a NavigationMenuItem by navigationMenuItem ID.
     */
    public function deleteById(int $navigationMenuItemId): int
    {
        $this->unCacheRelatedNavigationMenus($navigationMenuItemId);

        return DB::table('navigation_menu_items')
            ->where('navigation_menu_item_id', '=', $navigationMenuItemId)
            ->delete();
    }

    /**
     * Delete NavigationMenuItems by contextId.
     */
    public function deleteByContextId(?int $contextId): void
    {
        $navigationMenuItems = $this->getByContextId($contextId);

        while ($navigationMenuItem = $navigationMenuItems->next()) {
            $this->deleteObject($navigationMenuItem);
        }
    }

    /**
     * Load the XML file and move the settings to the DB
     */
    public function installSettings(?int $contextId, string $filename): bool
    {
        $xmlParser = new PKPXMLParser();
        $tree = $xmlParser->parse($filename);

        if ($contextId === Application::SITE_CONTEXT_ID) {
            $siteDao = DAORegistry::getDAO('SiteDAO'); /** @var \PKP\site\SiteDAO $siteDao */
            $site = $siteDao->getSite();
        }

        if (!$tree) {
            return false;
        }

        foreach ($tree->getChildren() as $setting) {
            $site = $setting->getAttribute('site');
            if ($contextId === Application::SITE_CONTEXT_ID && !$site) {
                continue;
            }
            $this->installNodeSettings($contextId, $setting, null, null, 0, true);
        }

        return true;
    }

    /**
     * Load a XML node to DB
     */
    public function installNodeSettings(?int $contextId, XMLNode $node, ?int $navigationMenuId = null, ?int $navigationMenuItemParentId = null, int $seq = 0, bool $checkChildren = false): bool
    {
        $titleKey = $node->getAttribute('title');
        $path = $node->getAttribute('path');
        $type = $node->getAttribute('type');

        $navigationMenuItemExisting = $this->getByTypeAndTitleLocaleKey($contextId, $type, $titleKey);

        if (!isset($navigationMenuItemExisting)) {
            $navigationMenuItem = $this->newDataObject();
            $navigationMenuItem->setPath($path);
            $navigationMenuItem->setContextId($contextId);

            $navigationMenuItem->setType($type);

            $navigationMenuItemId = $this->insertObject($navigationMenuItem);

            // add the i18n keys to the settings table so that they
            // can be used when a new locale is added/reloaded
            $this->updateSetting($navigationMenuItemId, 'titleLocaleKey', $titleKey);
        } else {
            $navigationMenuItemId = $navigationMenuItemExisting->getId();

            $this->updateSetting($navigationMenuItemId, 'titleLocaleKey', $titleKey);
        }

        // insert into Assignments
        if ($navigationMenuId) {
            $navigationMenuItemAssignmentDao = DAORegistry::getDAO('NavigationMenuItemAssignmentDAO'); /** @var NavigationMenuItemAssignmentDAO $navigationMenuItemAssignmentDao */
            $assignmentExists = $navigationMenuItemAssignmentDao->getByNMIIdAndMenuIdAndParentId($navigationMenuItemId, $navigationMenuId, $navigationMenuItemParentId);

            if (!isset($assignmentExists)) {
                $navigationMenuItemAssignment = $navigationMenuItemAssignmentDao->newDataObject();

                $navigationMenuItemAssignment->setMenuItemId($navigationMenuItemId);
                $navigationMenuItemAssignment->setMenuId($navigationMenuId);

                if ($navigationMenuItemParentId) {
                    $navigationMenuItemAssignment->setParentId($navigationMenuItemParentId);
                }

                $navigationMenuItemAssignment->setSequence($seq);

                // Insert Assignment
                $navigationMenuItemAssignmentDao->insertObject($navigationMenuItemAssignment);
            }
        }

        if ($checkChildren) {
            $seqSec = 0;

            foreach ($node->getChildren() as $navigationMenuItemSecondLevelNode) {
                $this->installNodeSettings($contextId, $navigationMenuItemSecondLevelNode, $navigationMenuId, $navigationMenuItemId, $seqSec, false);
                $seqSec++;
            }
        }

        return true;
    }

    /**
     * Method for update navigationMenuItem setting
     */
    public function updateSetting(int $navigationMenuItemId, string $name, mixed $value, ?string $type = null, bool $isLocalized = false): void
    {
        $keyFields = ['setting_name', 'locale', 'navigation_menu_item_id'];

        if (!$isLocalized) {
            $value = $this->convertToDB($value, $type);
            DB::table('navigation_menu_item_settings')->updateOrInsert(
                ['navigation_menu_item_id' => (int) $navigationMenuItemId, 'setting_name' => $name, 'locale' => ''],
                ['setting_value' => trim($value, '##'), 'setting_type' => $type]
            );
        } else {
            if (is_array($value)) {
                foreach ($value as $locale => $localeValue) {
                    $this->update('DELETE FROM navigation_menu_item_settings WHERE navigation_menu_item_id = ? AND setting_name = ? AND locale = ?', [(int) $navigationMenuItemId, $name, $locale]);
                    if (empty($localeValue)) {
                        continue;
                    }
                    $type = null;
                    $this->update(
                        'INSERT INTO navigation_menu_item_settings
					(navigation_menu_item_id, setting_name, setting_value, setting_type, locale)
					VALUES (?, ?, ?, ?, ?)',
                        [$navigationMenuItemId, $name, trim($this->convertToDB($localeValue, $type), '##'), $type, $locale]
                    );
                }
            }
        }
    }

    /**
     * Retrieve a context setting value.
     */
    public function getSetting(int $navigationMenuItemId, string $name, ?string $locale = null): mixed
    {
        $params = [$navigationMenuItemId, $name];
        if ($locale) {
            $params[] = $locale;
        }
        $result = $this->retrieve(
            'SELECT	setting_name, setting_value, setting_type, locale
			FROM	navigation_menu_item_settings
			WHERE	navigation_menu_item_id = ? AND
				setting_name = ?' .
                ($locale ? ' AND locale = ?' : ''),
            $params
        );

        foreach ($result as $row) {
            $returner[$row->locale] = $this->convertFromDB($row->setting_value, $row->setting_type);
        }
        if (count($returner) == 1) {
            return array_shift($returner);
        }
        if (count($returner) == 0) {
            return false;
        }
        return $returner;
    }

    /**
     * Remove all settings associated with a locale
     */
    public function deleteSettingsByLocale(string $locale): int
    {
        return $this->update('DELETE FROM navigation_menu_item_settings WHERE locale = ?', [$locale]);
    }

    /**
     * Uncache the related NMs to the NMI with $id
     */
    public function unCacheRelatedNavigationMenus(int $navigationMenuItemId)
    {
        /** @var NavigationMenuDAO */
        $navigationMenuDao = DAORegistry::getDAO('NavigationMenuDAO');
        /** @var NavigationMenuItemAssignmentDAO */
        $navigationMenuItemAssignmentDao = DAORegistry::getDAO('NavigationMenuItemAssignmentDAO');
        $assignments = $navigationMenuItemAssignmentDao->getByMenuItemId($navigationMenuItemId);
        if ($assignments) {
            $assignmentsArray = $assignments->toArray();
            foreach ($assignmentsArray as $assignment) {
                Cache::forget("navigationMenu-{$assignment->getMenuId()}");
            }
        }
    }

    /**
     * Port static page as a Custom NMI
     */
    public function portStaticPage(\APP\plugins\generic\staticPages\classes\StaticPage $staticPage): ?int
    {
        $path = $staticPage->getPath();
        $contextId = $staticPage->getContextId();

        $existingNMIWithPath = $this->getByPath($contextId, $path);

        $retNavigationMenuItemId = null;

        if (!isset($existingNMIWithPath)) {
            $navigationMenuItem = $this->newDataObject();

            $navigationMenuItem->setPath($path);
            $navigationMenuItem->setContextId($contextId);
            $navigationMenuItem->setType(NavigationMenuItem::NMI_TYPE_CUSTOM);

            $navigationMenuItem->setTitle($staticPage->getTitle(null), null);
            $navigationMenuItem->setContent($staticPage->getContent(null), null);

            $retNavigationMenuItemId = $this->insertObject($navigationMenuItem);
        }

        return $retNavigationMenuItemId;
    }
}

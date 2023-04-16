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

use Illuminate\Support\Facades\DB;
use PKP\db\DAORegistry;
use PKP\db\DAOResultFactory;
use PKP\xml\PKPXMLParser;
use PKP\xml\XMLNode;

class NavigationMenuItemDAO extends \PKP\db\DAO
{
    /**
     * Retrieve a navigation menu item by ID.
     *
     * @param int $navigationMenuItemId
     *
     * @return ?NavigationMenuItem
     */
    public function getById($navigationMenuItemId)
    {
        $params = [(int) $navigationMenuItemId];
        $result = $this->retrieve(
            'SELECT	* FROM navigation_menu_items WHERE navigation_menu_item_id = ?',
            $params
        );

        $row = (array) $result->current();
        return $row ? $this->_fromRow($row) : null;
    }

    /**
     * Retrieve a navigation menu item by path.
     *
     * @param int $contextId Context Id
     * @param string $path
     *
     * @return ?NavigationMenuItem
     */
    public function getByPath($contextId, $path)
    {
        $result = $this->retrieve(
            'SELECT	* FROM navigation_menu_items WHERE path = ? and context_id = ? and type= ?',
            [$path, (int) $contextId, 'NMI_TYPE_CUSTOM']
        );

        $row = (array) $result->current();
        return $row ? $this->_fromRow($row) : null;
    }

    /**
     * Retrieve a navigation menu items by context Id.
     *
     * @param int $contextId Context Id
     *
     * @return DAOResultFactory
     */
    public function getByContextId($contextId)
    {
        $result = $this->retrieve(
            'SELECT * FROM navigation_menu_items WHERE context_id = ?',
            [(int) $contextId]
        );

        return new DAOResultFactory($result, $this, '_fromRow');
    }

    /**
     * Retrieve items by menu id
     *
     * @param int $menuId
     */
    public function getByMenuId($menuId)
    {
        $result = $this->retrieve(
            'SELECT nmi.*
				FROM navigation_menu_item_assignments as nmh
				LEFT JOIN navigation_menu_items as nmi ON (nmh.navigation_menu_item_id = nmi.navigation_menu_item_id)
				WHERE nmh.navigation_menu_id = ?
				ORDER BY nmh.seq',
            [(int) $menuId]
        );
        return new DAOResultFactory($result, $this, '_fromRow');
    }

    /**
     * Retrieve items by menuItemType and setting_name = titleLocaleKey
     *
     * @param int $contextId
     * @param string $menuItemType
     * @param string $menuItemTitleLocaleKey
     *
     * @return ?NavigationMenuItem
     */
    public function getByTypeAndTitleLocaleKey($contextId, $menuItemType, $menuItemTitleLocaleKey)
    {
        $result = $this->retrieve(
            'SELECT *
				FROM navigation_menu_items
				LEFT JOIN navigation_menu_item_settings ON (navigation_menu_items.navigation_menu_item_id = navigation_menu_item_settings.navigation_menu_item_id)
				WHERE navigation_menu_items.type = ?
				AND (navigation_menu_item_settings.setting_name = \'titleLocaleKey\' and navigation_menu_item_settings.setting_value = ?)
				AND navigation_menu_items.context_id = ?',
            [$menuItemType, $menuItemTitleLocaleKey, (int) $contextId]
        );
        $row = (array) $result->current();
        return $row ? $this->_fromRow($row) : null;
    }

    /**
     * Retrieve the menu items with the specified type.
     *
     * @param int $type NMI_TYPE_...
     * @param int $contextId
     *
     * @return DAOResultFactory containing matching NavigationMenuItems
     */
    public function getByType($type, $contextId = null)
    {
        $params = [$type];
        if ($contextId !== null) {
            $params[] = $contextId;
        }
        $result = $this->retrieve(
            'SELECT	* FROM navigation_menu_items WHERE type = ?' .
            ($contextId !== null ? ' AND context_id = ?' : ''),
            $params
        );
        return new DAOResultFactory($result, $this, '_fromRow');
    }

    /**
     * Get the list of localized field names for this table
     *
     * @return array
     */
    public function getLocaleFieldNames()
    {
        return ['title', 'content', 'remoteUrl'];
    }

    /**
     * @copydoc DAO::getAdditionalFieldNames()
     */
    public function getAdditionalFieldNames()
    {
        return ['titleLocaleKey'];
    }

    /**
     * Get a new data object.
     *
     * @return NavigationMenuItem
     */
    public function newDataObject()
    {
        return new NavigationMenuItem();
    }

    /**
     * Internal function to return a NavigationMenuItem object from a row.
     *
     * @param array $row
     *
     * @return NavigationMenuItem
     */
    public function _fromRow($row, $dataObject = false)
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
     *
     * @param object $navigationMenuItem
     */
    public function updateLocaleFields($navigationMenuItem)
    {
        $this->updateDataObjectSettings('navigation_menu_item_settings', $navigationMenuItem, [
            'navigation_menu_item_id' => $navigationMenuItem->getId()
        ]);
    }

    /**
     * Insert a new NavigationMenuItem.
     *
     * @param NavigationMenuItem $navigationMenuItem
     *
     * @return int
     */
    public function insertObject($navigationMenuItem)
    {
        $this->update(
            'INSERT INTO navigation_menu_items
				(path, context_id, type)
				VALUES
				(?, ?, ?)',
            [
                $navigationMenuItem->getPath(),
                (int) $navigationMenuItem->getContextId(),
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
     *
     * @param NavigationMenuItem $navigationMenuItem
     *
     * @return bool
     */
    public function updateObject($navigationMenuItem)
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
                (int) $navigationMenuItem->getContextId(),
                $navigationMenuItem->getType(),
                (int) $navigationMenuItem->getId(),
            ]
        );
        $this->updateLocaleFields($navigationMenuItem);

        $this->unCacheRelatedNavigationMenus($navigationMenuItem->getId());

        return $returner;
    }

    /**
     * Delete a NavigationMenuItem.
     *
     * @param NavigationMenuItem $navigationMenuItem
     *
     * @return bool
     */
    public function deleteObject($navigationMenuItem)
    {
        return $this->deleteById($navigationMenuItem->getId());
    }

    /**
     * Delete a NavigationMenuItem by navigationMenuItem ID.
     *
     * @param int $navigationMenuItemId
     *
     * @return bool
     */
    public function deleteById($navigationMenuItemId)
    {
        $this->unCacheRelatedNavigationMenus($navigationMenuItemId);

        $this->update('DELETE FROM navigation_menu_item_settings WHERE navigation_menu_item_id = ?', [(int) $navigationMenuItemId]);
        $this->update('DELETE FROM navigation_menu_items WHERE navigation_menu_item_id = ?', [(int) $navigationMenuItemId]);

        $navigationMenuItemAssignmentDao = DAORegistry::getDAO('NavigationMenuItemAssignmentDAO'); /** @var NavigationMenuItemAssignmentDAO $navigationMenuItemAssignmentDao */
        $navigationMenuItemAssignmentDao->deleteByMenuItemId($navigationMenuItemId);
    }

    /**
     * Delete NavigationMenuItems by contextId.
     *
     * @param int $contextId
     */
    public function deleteByContextId($contextId)
    {
        $navigationMenuItems = $this->getByContextId($contextId);

        while ($navigationMenuItem = $navigationMenuItems->next()) {
            $this->deleteObject($navigationMenuItem);
        }
    }

    /**
     * Load the XML file and move the settings to the DB
     *
     * @param int $contextId
     * @param string $filename
     *
     * @return bool true === success
     */
    public function installSettings($contextId, $filename)
    {
        $xmlParser = new PKPXMLParser();
        $tree = $xmlParser->parse($filename);

        if ($contextId == \PKP\core\PKPApplication::CONTEXT_ID_NONE) {
            $siteDao = DAORegistry::getDAO('SiteDAO'); /** @var SiteDAO $siteDao */
            $site = $siteDao->getSite();
        }

        if (!$tree) {
            return false;
        }

        foreach ($tree->getChildren() as $setting) {
            $site = $setting->getAttribute('site');
            if ($contextId == \PKP\core\PKPApplication::CONTEXT_ID_NONE && !$site) {
                continue;
            }
            $this->installNodeSettings($contextId, $setting, null, null, 0, true);
        }

        return true;
    }

    /**
     * Load a XML node to DB
     *
     * @param int $contextId
     * @param XMLNode $node
     * @param int $navigationMenuId
     * @param int $navigationMenuItemParentId
     * @param int $seq
     * @param bool $checkChildren Optional
     *
     * @return bool true === success
     */
    public function installNodeSettings($contextId, $node, $navigationMenuId = null, $navigationMenuItemParentId = null, $seq = 0, $checkChildren = false)
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
     *
     * @param int $navigationMenuItemId
     * @param string $name
     * @param string $type data type of the setting. If omitted, type will be guessed
     * @param bool $isLocalized
     */
    public function updateSetting($navigationMenuItemId, $name, $value, $type = null, $isLocalized = false)
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
     *
     * @param string $name
     * @param string $locale optional
     */
    public function getSetting($navigationMenuItemId, $name, $locale = null)
    {
        $params = [(int) $navigationMenuItemId, $name];
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

        $setting = [];
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
     *
     * @param string $locale
     */
    public function deleteSettingsByLocale($locale)
    {
        return $this->update('DELETE FROM navigation_menu_item_settings WHERE locale = ?', [$locale]);
    }

    /**
     * Uncache the related NMs to the NMI with $id
     */
    public function unCacheRelatedNavigationMenus($id)
    {
        /** @var NavigationMenuDAO */
        $navigationMenuDao = DAORegistry::getDAO('NavigationMenuDAO');
        /** @var NavigationMenuItemAssignmentDAO */
        $navigationMenuItemAssignmentDao = DAORegistry::getDAO('NavigationMenuItemAssignmentDAO');
        $assignments = $navigationMenuItemAssignmentDao->getByMenuItemId($id);
        if ($assignments) {
            $assignmentsArray = $assignments->toArray();
            foreach ($assignmentsArray as $assignment) {
                $cache = $navigationMenuDao->getCache($assignment->getMenuId());
                if ($cache) {
                    $cache->flush();
                }
            }
        }
    }

    /**
     * Port static page as a Custom NMI
     *
     * @param StaticPage $staticPage
     *
     * @return int The id of the inserted NMI. Null if non is inserted
     */
    public function portStaticPage($staticPage)
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

if (!PKP_STRICT_MODE) {
    class_alias('\PKP\navigationMenu\NavigationMenuItemDAO', '\NavigationMenuItemDAO');
}

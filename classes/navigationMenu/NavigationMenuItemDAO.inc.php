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
	 * Retrieve a navigation menu items by default_id.
	 * @param $contextId int Context Id
	 * @param $defaultId int default_id
	 * @return NavigationMenuItem
	 */
	function getByDefaultId($contextId, $defaultId) {
		$params = array((int) $contextId, (int) $defaultId);
		$result = $this->retrieve(
			'SELECT * FROM navigation_menu_items WHERE context_id = ? AND default_id = ?',
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
	 * @return NavigationMenuItem
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
		$navigationMenuItem->setDefault($row['is_default']);
		$navigationMenuItem->setOp($row['op']);
		$navigationMenuItem->setDefaultId($row['default_id']);

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
				(path, page, is_default, context_id, op, default_id)
				VALUES
				(?, ?, ?, ?, ?, ?)',
			array(
				$navigationMenuItem->getPath(),
				$navigationMenuItem->getPage(),
				(int) $navigationMenuItem->getDefault(),
				(int) $navigationMenuItem->getContextId(),
				$navigationMenuItem->getOp(),
				(int) $navigationMenuItem->getDefaultId(),
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
					is_default = ?,
					context_id = ?,
					op = ?,
					default_id = ?
				WHERE navigation_menu_item_id = ?',
			array(
				$navigationMenuItem->getPath(),
				$navigationMenuItem->getPage(),
				(int) $navigationMenuItem->getDefault(),
				(int) $navigationMenuItem->getContextId(),
				(int) $navigationMenuItem->getId(),
				$navigationMenuItem->getOp(),
				(int) $navigationMenuItem->getDefaultId(),
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
		$this->update('DELETE FROM navigation_menu_items WHERE navigation_menu_item_id = ?', (int) $navigationMenuItemId);

		$navigationMenuItemAssignmentDao = DAORegistry::getDAO('NavigationMenuItemAssignmentDAO');
		$navigationMenuItemAssignmentDao->deleteByMenuItemId($navigationMenuItemId);

		return true;
	}

	/**
	 * Delete NavigationMenuItems by contextId.
	 * @param $contextId int
	 */
	function deleteByContextId($contextId) {
		$navigationMenuItems = $this->getByContextId($contextId);

		while ($navigationMenuItem = $navigationMenuItems->next()) {
			$this->deleteObject($navigationMenuItem);
		}

		return true;
	}

	/**
	 * Get the ID of the last inserted navigation menu item.
	 * @return int
	 */
	function getInsertId() {
		return $this->_getInsertId('navigation_menu_items', 'navigation_menu_item_id');
	}

	/**
	 * Load the XML file and move the settings to the DB
	 * @param $contextId
	 * @param $filename
	 * @return boolean true === success
	 */
	function installSettings($contextId, $filename) {
		$xmlParser = new XMLParser();
		$tree = $xmlParser->parse($filename);

		$contextDao = Application::getContextDAO();
		$context = $contextDao->getById($contextId);
		$supportedLocales = $context->getSupportedSubmissionLocales();

		if (!$tree) {
			$xmlParser->destroy();
			return false;
		}

		foreach ($tree->getChildren() as $setting) {
			$this->installNodeSettings($contextId, $setting, null, null, 0, true);
		}

		return true;
	}

	/**
	 * Load a XML node to DB
	 * @param $contextId
	 * @param $node
	 * @param $checkChildren bool Optional
	 * @return boolean true === success
	 */
	function installNodeSettings($contextId, $node, $navigationMenuId = null, $navigationMenuItemParentId = null, $seq = 0, $checkChildren = false) {
		$contextDao = Application::getContextDAO();
		$context = $contextDao->getById($contextId);
		$supportedLocales = $context->getSupportedSubmissionLocales();

		$titleKey = $node->getAttribute('title');
		$path = $node->getAttribute('path');
		$page = $node->getAttribute('page');
		$op = $node->getAttribute('op');
		$isDefault = $node->getAttribute('default');
		$defaultId = $node->getAttribute('default_id');

		// create a role associated with this user group
		$navigationMenuItem = $this->newDataObject();
		$navigationMenuItem->setPath($path);
		$navigationMenuItem->setContextId($contextId);
		$navigationMenuItem->setPage($page);
		$navigationMenuItem->setDefault($isDefault);
		$navigationMenuItem->setOp($op);
		$navigationMenuItem->setDefaultId($defaultId);

		// insert the group into the DB
		$navigationMenuItemId = $this->insertObject($navigationMenuItem);

		// insert into Assignments
		if ($navigationMenuId) {
			$navigationMenuItemAssignmentDao = DAORegistry::getDAO('NavigationMenuItemAssignmentDAO');
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

		// add the i18n keys to the settings table so that they
		// can be used when a new locale is added/reloaded
		$this->updateSetting($navigationMenuItemId, 'titleLocaleKey', $titleKey);

		// install the settings in the current locale for this context
		foreach ($supportedLocales as $locale) {
			$this->installLocale($locale, $contextId);
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
	 * Unload the XML file and move the settings to the DB
	 * @param $contextId
	 * @param $filename
	 * @return boolean true === success
	 */
	function uninstallSettings($contextId, $filename) {
		$xmlParser = new XMLParser();
		$tree = $xmlParser->parse($filename);

		$contextDao = Application::getContextDAO();
		$context = $contextDao->getById($contextId);
		$supportedLocales = $context->getSupportedSubmissionLocales();

		if (!$tree) {
			$xmlParser->destroy();
			return false;
		}

		foreach ($tree->getChildren() as $setting) {
			$this->uninstallNodeSettings($contextId, $setting, true);
		}

		return true;
	}

	/**
	 * unload a XML node to DB
	 * @param $contextId
	 * @param $node
	 * @param $checkChildren bool Optional
	 * @return boolean true === success
	 */
	function uninstallNodeSettings($contextId, $node, $checkChildren = false) {
		$contextDao = Application::getContextDAO();
		$context = $contextDao->getById($contextId);
		$supportedLocales = $context->getSupportedSubmissionLocales();

		$titleKey = $node->getAttribute('title');
		$path = $node->getAttribute('path');
		$page = $node->getAttribute('page');
		$op = $node->getAttribute('op');
		$isDefault = $node->getAttribute('default');
		$defaultId = $node->getAttribute('default_id');

		$navigationMenuItem = $this->getByDefaultId($contextId, $defaultId);

		if ($navigationMenuItem) {
			// delete the navigationMenuItem from DB
			$this->deleteObject($navigationMenuItem);
		}

		if ($checkChildren) {
			foreach ($node->getChildren() as $navigationMenuItemSecondLevelNode) {
				$this->uninstallNodeSettings($contextId, $navigationMenuItemSecondLevelNode, false);
			}
		}

		return true;
	}


	/**
	 * use the locale keys stored in the settings table to install the locale settings
	 * @param $locale
	 * @param $contextId
	 */
	function installLocale($locale, $contextId = null) {
		$navigationMenuItems = $this->getByContextId($contextId);
		while ($navigationMenuItem = $navigationMenuItems->next()) {
			$titleKey = $this->getSetting($navigationMenuItem->getId(), 'titleLocaleKey');
			$this->updateSetting($navigationMenuItem->getId(),
				'title',
				array($locale => __($titleKey, null, $locale)),
				'string',
				$locale,
				true
			);
		}
	}

	/**
	 * Method for update navigationMenuItem setting
	 * @param $navigationMenuItemId int
	 * @param $name string
	 * @param $value mixed
	 * @param $type string data type of the setting. If omitted, type will be guessed
	 * @param $isLocalized boolean
	 */
	function updateSetting($navigationMenuItemId, $name, $value, $type = null, $isLocalized = false) {
		$keyFields = array('setting_name', 'locale', 'navigation_menu_item_id');

		if (!$isLocalized) {
			$value = $this->convertToDB($value, $type);
			$this->replace('navigation_menu_item_settings',
				array(
					'navigation_menu_item_id' => (int) $navigationMenuItemId,
					'setting_name' => $name,
					'setting_value' => $value,
					'setting_type' => $type,
					'locale' => ''
				),
				$keyFields
			);
		} else {
			if (is_array($value)) foreach ($value as $locale => $localeValue) {
				$this->update('DELETE FROM navigation_menu_item_settings WHERE navigation_menu_item_id = ? AND setting_name = ? AND locale = ?', array((int) $navigationMenuItemId, $name, $locale));
				if (empty($localeValue)) continue;
				$type = null;
				$this->update('INSERT INTO navigation_menu_item_settings
					(navigation_menu_item_id, setting_name, setting_value, setting_type, locale)
					VALUES (?, ?, ?, ?, ?)',
					array(
						$navigationMenuItemId, $name, $this->convertToDB($localeValue, $type), $type, $locale
					)
				);
			}
		}
	}

	/**
	 * Retrieve a context setting value.
	 * @param $userGroupId int
	 * @param $name string
	 * @param $locale string optional
	 * @return mixed
	 */
	function getSetting($navigationMenuItemId, $name, $locale = null) {
		$params = array((int) $navigationMenuItemId, $name);
		if ($locale) $params[] = $locale;
		$result = $this->retrieve(
			'SELECT	setting_name, setting_value, setting_type, locale
			FROM	navigation_menu_item_settings
			WHERE	navigation_menu_item_id = ? AND
				setting_name = ?' .
				($locale?' AND locale = ?':''),
			$params
		);

		$recordCount = $result->RecordCount();
		$returner = false;
		if ($recordCount == 1) {
			$row = $result->getRowAssoc(false);
			$returner = $this->convertFromDB($row['setting_value'], $row['setting_type']);
		} elseif ($recordCount > 1) {
			$returner = array();
			while (!$result->EOF) {
				$returner[$row['locale']] = $this->convertFromDB($row['setting_value'], $row['setting_type']);
				$result->MoveNext();
			}

			$result->Close();
		}

		return $returner;
	}

	/**
	 * Remove all settings associated with a locale
	 * @param $locale
	 */
	function deleteSettingsByLocale($locale) {
		return $this->update('DELETE FROM navigation_menu_item_settings WHERE locale = ?', $locale);
	}
}

?>

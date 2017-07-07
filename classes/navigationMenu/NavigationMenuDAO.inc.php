<?php

/**
 * @file classes/navigationMenu/NavigationMenuDAO.inc.php
 *
 * Copyright (c) 2014-2017 Simon Fraser University
 * Copyright (c) 2000-2017 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class NavigationMenuDAO
 * @ingroup navigationMenu
 * @see NavigationMenu
 *
 * @brief Operations for retrieving and modifying NavigationMenu objects.
 */


import('lib.pkp.classes.navigationMenu.NavigationMenu');

class NavigationMenuDAO extends DAO {
	/**
	 * Constructor
	 */
	function __construct() {
		parent::__construct();
	}

	/**
	 * Generate a new data object.
	 * @return NavigationMenu
	 */
	function newDataObject() {
		return new NavigationMenu();
	}

	/**
	 * Retrieve a navigation menu by navigation menu ID.
	 * @param $navigationMenuId int navigation menu ID
	 * @param $contextId int Context Id
	 * @param $assocId int Optional assoc ID - may associate with template area :: TODO:DEFSTAT is area Entity or String?
	 * @return NavigationMenu
	 */
	function getById($navigationMenuId, $contextId, $assocId = null) {
		$params = array((int) $navigationMenuId);
		if ($contextId !== null) $params[] = (int) $contextId;
		if ($assocId !== null) $params[] = (int) $assocId;
		$result = $this->retrieve(
			'SELECT * FROM navigation_menus WHERE navigation_menu_id = ?' .
			($contextId !== null?' AND context_id = ?':'') .
			($assocId !== null?' AND assoc_id = ?':''),
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
	 * Retrieve a navigation menu by context Id.
	 * @param $contextId int Context Id
	 * @param $assocId int Optional assoc ID - may associate with template area :: TODO:DEFSTAT is area Entity or String?
	 * @return NavigationMenu
	 */
	function getByContextId($contextId, $assocId = null) {
		$params = array((int) $contextId);
		if ($assocId !== null) $params[] = (int) $assocId;
		$result = $this->retrieve(
			'SELECT * FROM navigation_menus WHERE context_id = ?' .
			($assocId !== null?' AND assoc_id = ?':''),
			$params
		);

		return new DAOResultFactory($result, $this, '_fromRow');
	}

	/**
	 * Retrieve a navigation menu by navigation menu ID.
	 * @param $navigationMenuId int navigation menu ID
	 * @param $contextId int Context Id
	 * @param $assocId int Optional assoc ID - may associate with template area :: TODO:DEFSTAT is area Entity or String?
	 * @return NavigationMenu
	 */
	function getByArea($contextId, $areaName) {
		$params = array($areaName);
		$params[] = (int) $contextId;
		$result = $this->retrieve(
			'SELECT * FROM navigation_menus WHERE area_name = ? and context_id = ?',
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
	 * Retrieve a navigation menu by title
	 * @param $contextId int Context Id
	 * @param $title string
	 * @return NavigationMenu
	 */
	function getByTitle($contextId, $title) {
		$params = array((int) $contextId);
		$params[] = $title;
		$result = $this->retrieve(
			'SELECT * FROM navigation_menus WHERE context_id = ? and title = ?',
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
	 * Check if a navigationMenu exists with the given title.
	 * @param $title int
	 * @param $contextId int
	 * @return boolean
	 */
	function navigationMenuExistsByTitle($title, $contextId) {
		$result = $this->retrieve(
			'SELECT COUNT(*)
			FROM	navigation_menus
			WHERE	title = ? AND
				context_id = ?',
			array(
				$title,
				(int) $contextId
			)
		);
		$returner = isset($result->fields[0]) && $result->fields[0] != 0 ? true : false;

		$result->Close();
		return $returner;
	}

	/**
	 * Get the locale field names.
	 * @return array
	 */
	function getLocaleFieldNames() {
	    return array();
	}

	/**
	 * Internal function to return an NavigationMenu object from a row.
	 * @param $row array
	 * @return NavigationMenu
	 */
	function _fromRow($row) {
		$navigationMenu = $this->newDataObject();
		$navigationMenu->setId($row['navigation_menu_id']);
		$navigationMenu->setTitle($row['title']);
		$navigationMenu->setAreaName($row['area_name']);
		$navigationMenu->setContextId($row['context_id']);
		$navigationMenu->setAssocId($row['assoc_id']);
		$navigationMenu->setDefaultMenu($row['defaultmenu']);

		return $navigationMenu;
	}

	/**
	 * Insert a new NavigationMenu.
	 * @param $navigationMenu NavigationMenu
	 * @return int
	 */
	function insertObject($navigationMenu) {
		$this->update(
				'INSERT INTO navigation_menus
				(title, area_name, context_id, assoc_id, defaultmenu)
				VALUES
				(?, ?, ?, ?, ?)',
			array(
				$navigationMenu->getTitle(),
				$navigationMenu->getAreaName(),
				(int) $navigationMenu->getContextId(),
				(int) $navigationMenu->getAssocId(),
				(int) $navigationMenu->getDefaultMenu(),
			)
		);
		$navigationMenu->setId($this->getInsertId());

		return $navigationMenu->getId();
	}

	/**
	 * Update an existing NavigationMenu
	 * @param NavigationMenu $navigationMenu
	 * @return boolean
	 */
	function updateObject($navigationMenu) {
		$returner = $this->update(
			'UPDATE	navigation_menus
			SET	title = ?,
				area_name = ?,
				context_id = ?,
				assoc_id = ?,
				defaultmenu = ?
			WHERE	navigation_menu_id = ?',
			array(
				$navigationMenu->getTitle(),
				$navigationMenu->getAreaName(),
				(int) $navigationMenu->getContextId(),
				(int) $navigationMenu->getAssocId(),
				(int) $navigationMenu->getDefaultMenu(),
				(int) $navigationMenu->getId(),
			)
		);

		return $returner;
	}

	/**
	 * Delete a NavigationMenu.
	 * TODO::defstat - What whould we do with NavigationMenuItems having the deleted NavigationMenu as parent
	 * @param $navigationMenu NavigationMenu
	 * @return boolean
	 */
	function deleteObject($navigationMenu) {
		return $this->deleteById($navigationMenu->getId());
	}

	/**
	 * Delete a NavigationMenu.
	 * TODO::defstat - What whould we do with NavigationMenuItems having the deleted NavigationMenu as parent
	 * @param $navigationMenuId int
	 */
	function deleteById($navigationMenuId) {
		return $this->update('DELETE FROM navigation_menus WHERE navigation_menu_id = ?', (int) $navigationMenuId);
	}

	/**
	 * Get the ID of the last inserted NavigationMenu
	 * @return int
	 */
	function getInsertId() {
		return $this->_getInsertId('navigation_menus', 'navigation_menu_id');
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
		$supportedLocales = $context->getSupportedLocales();

		if (!$tree) {
			$xmlParser->destroy();
			return false;
		}

		foreach ($tree->getChildren() as $navigationMenuNode) {
			$title = $navigationMenuNode->getAttribute('title');
			$area = $navigationMenuNode->getAttribute('area');

			$navigationMenu = $this->newDataObject();
			$navigationMenu->setTitle($title);
			$navigationMenu->setContextId($contextId);
			$navigationMenu->setAreaName($area);
			$navigationMenu->setDefaultMenu(true);

			// insert the group into the DB
			$navigationMenuId = $this->insertObject($navigationMenu);
		}

		return true;
	}
}

?>

<?php

/**
 * @defgroup navigationMenu.NavigationMenu
 * Implements NavigationMenu Object.
 */

/**
 * @file classes/navigationMenu/NavigationMenu.inc.php
 *
 * Copyright (c) 2014-2017 Simon Fraser University
 * Copyright (c) 2000-2017 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class NavigationMenu
 * @ingroup navigationMenu
 * @see NavigationMenuDAO
 *
 * @brief Class describing a NavigationMenu.
 */

class NavigationMenu extends DataObject {
	/** @var $menuTree array Hierarchical array of NavigationMenuItems */
	var $menuTree = null;

	//
	// Get/set methods
	//
	/**
	 * Get the NavigationMenu 'default' status (can be edited/deleted if not default)
	 * @return int
	 */
	function getDefault() {
		return $this->getData('is_default');
	}

	/**
	 * Set the NavigationMenu 'default' status (can be edited/deleted if not default)
	 * @param $default int
	 */
	function setDefault($default) {
		$this->setData('is_default', $default);
	}

	/**
	 * Get contextId of this NavigationMenu
	 * @return int
	 */
	function getContextId() {
		return $this->getData('context_id');
	}

	/**
	 * Set contextId of this NavigationMenu
	 * @param $contextId int
	 */
	function setContextId($contextId) {
		$this->setData('context_id', $contextId);
	}

	/**
	 * Get title of this NavigationMenu. Not localized.
	 * @return string
	 */
	function getTitle() {
		return $this->getData('title');
	}

	/**
	 * Set title of this NavigationMenu. Not localized.
	 * @param $title string
	 */
	function setTitle($title) {
		$this->setData('title', $title);
	}

	/**
	 * Get areaName of this NavigationMenu. Not localized.
	 * @return string
	 */
	function getAreaName() {
		return $this->getData('area_name');
	}

	/**
	 * Set navigationArea name of this NavigationMenu. Not localized.
	 * @param $areaName string
	 */
	function setAreaName($areaName) {
		$this->setData('area_name', $areaName);
	}
}

?>

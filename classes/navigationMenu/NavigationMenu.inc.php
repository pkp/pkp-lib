<?php

/**
 * @file classes/navigationMenu/NavigationMenu.inc.php
 *
 * Copyright (c) 2014-2018 Simon Fraser University
 * Copyright (c) 2000-2018 John Willinsky
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
	 * Get contextId of this NavigationMenu
	 * @return int
	 */
	function getContextId() {
		return $this->getData('contextId');
	}

	/**
	 * Set contextId of this NavigationMenu
	 * @param $contextId int
	 */
	function setContextId($contextId) {
		$this->setData('contextId', $contextId);
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
		return $this->getData('areaName');
	}

	/**
	 * Set navigationArea name of this NavigationMenu. Not localized.
	 * @param $areaName string
	 */
	function setAreaName($areaName) {
		$this->setData('areaName', $areaName);
	}
}



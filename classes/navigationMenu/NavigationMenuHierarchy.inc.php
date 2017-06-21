<?php

/**
 * @defgroup navigationMenu.NavigationMenuHierarchy
 * Implements NavigationMenuHierarchy Object.
 */

/**
 * @file classes/navigationMenu/NavigationMenuHierarchy.inc.php
 *
 * Copyright (c) 2014-2017 Simon Fraser University
 * Copyright (c) 2000-2017 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class NavigationMenuHierarchy
 * @ingroup navigationMenu
 * @see NavigationMenuHierarchyDAO
 *
 * @brief Class describing a NavigationMenuHierarchy.
 */

class NavigationMenuHierarchy extends DataObject {
	/**
	 * Constructor
	 */
	function __construct() {
		parent::__construct();
	}

	//
	// Get/set methods
	//
	/**
	 * Get navigation_menu_id for this NavigationMenuHierarchy.
	 * @return int
	 */
	function getNavigationMenuId() {
		return $this->getData('navigation_menu_id');
	}

	/**
	 * Set navigation_menu_id for this NavigationMenuHierarchy.
	 * @param $navigationMenuId int
	 */
	function setNavigationMenuId($navigationMenuId) {
		$this->setData('navigation_menu_id', $navigationMenuId);
	}

	/**
	 * Get navigation_menu_item_id for this NavigationMenuHierarchy.
	 * @return int
	 */
	function getNavigationMenuItemId() {
		return $this->getData('navigation_menu_item_id');
	}

	/**
	 * Set navigation_menu_item_id for this NavigationMenuHierarchy.
	 * @param $navigationMenuItemId int
	 */
	function setNavigationMenuItemId($navigationMenuItemId) {
		$this->setData('navigation_menu_item_id', $navigationMenuItemId);
	}

	/**
	 * Get hierarchy rule seq for this NavigationMenuHierarchy.
	 * @return int
	 */
	function getSequence() {
		return $this->getData('seq');
	}

	/**
	 * Set hierarchy rule seq for this NavigationMenuHierarchy.
	 * @param $seq int
	 */
	function setSequence($seq) {
		$this->setData('seq', $seq);
	}

	/**
	 * Get child_navigation_menu_item_id for this NavigationMenuHierarchy.
	 * @return int
	 */
	function getChildNavigationMenuItemId() {
		return $this->getData('child_navigation_menu_item_id');
	}

	/**
	 * Set child_navigation_menu_item_id for this NavigationMenuHierarchy.
	 * @param $childNavigationMenuItemId int
	 */
	function setChildNavigationMenuItemId($childNavigationMenuItemId) {
		$this->setData('child_navigation_menu_item_id', $childNavigationMenuItemId);
	}
}

?>

<?php

/**
 * @file classes/navigationMenu/NavigationMenuItem.inc.php
 *
 * Copyright (c) 2014-2017 Simon Fraser University
 * Copyright (c) 2000-2017 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class NavigationMenuItem
 * @ingroup navigationMenu
 * @see NavigationMenuItemDAO
 *
 * @brief Basic class describing a NavigationMenuItem.
 */

class NavigationMenuItem extends DataObject {
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
	 * Get assoc ID for this navigation menu item.
	 * @return int
	 */
	function getAssocId() {
		return $this->getData('assoc_id');
	}

	/**
	 * Set assoc ID for this navigation menu item.
	 * @param $assocId int
	 */
	function setAssocId($assocId) {
		$this->setData('assoc_id', $assocId);
	}

	/**
	 * Get seq for this navigation menu item.
	 * @return int
	 */
	function getSequence() {
		return $this->getData('seq');
	}

	/**
	 * Set seq for this navigation menu item.
	 * @param $seq int
	 */
	function setSequence($seq) {
		$this->setData('seq', $seq);
	}

	/**
	 * Get navigationMenuId for this navigation menu item.
	 * @return int
	 */
	function getNavigationMenuId() {
		return $this->getData('navigationMenuId');
	}

	/**
	 * Set navigationMenuId for this navigation menu item.
	 * @param $navigationMenuId int
	 */
	function setNavigationMenuId($navigationMenuId) {
		$this->setData('navigationMenuId', $navigationMenuId);
	}

	/**
	 * Set path for this navigation menu item.
	 * @param $path string
	 */
	function setPath($path) {
		$this->setData('path', $path);
	}

	/**
	 * Get navigationMenuId for this navigation menu item.
	 * @return string
	 */
	function getPath() {
		return $this->getData('path');
	}

	/**
	 * Get enabled for this navigation menu item.
	 * @return int
	 */
	function getEnabled() {
		return $this->getData('enabled');
	}

	/**
	 * Set enabled for this navigation menu item.
	 * @param $enabled int
	 */
	function setEnabled($enabled) {
		$this->setData('enabled', $enabled);
	}

	/**
	 * Get default for this navigation menu item.
	 * @return int
	 */
	function getDefaultMenu() {
		return $this->getData('defaultMenu');
	}

	/**
	 * Set defaultMenu for this navigation menu item.
	 * @param $default int
	 */
	function setDefaultMenu($default) {
		$this->setData('defaultMenu', $default);
	}

	/**
	 * Get contextId for this navigation menu item.
	 * @return int
	 */
	function getContextId() {
		return $this->getData('context_id');
	}

	/**
	 * Set context_id for this navigation menu item.
	 * @param $contextId int
	 */
	function setContextId($contextId) {
		$this->setData('context_id', $contextId);
	}

	/**
	 * Get the title of the navigation Menu.
	 * @return string
	 */
	function getLocalizedTitle() {
		return $this->getLocalizedData('title');
	}

	/**
	 * Get the title of the navigation menu item.
	 * @param $locale string
	 * @return string
	 */
	function getTitle($locale) {
		return $this->getData('title', $locale);
	}

	/**
	 * Set the title of the navigation menu item.
	 * @param $title string
	 * @param $locale string
	 */
	function setTitle($title, $locale) {
		$this->setData('title', $title, $locale);
	}
}

?>

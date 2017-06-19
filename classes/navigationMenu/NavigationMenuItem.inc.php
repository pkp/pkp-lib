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
	/** @var $navigationMenuItems array The navigationMenuItems underneath this navigationMenuItem */
	var $navigationMenuItems = array();

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
	 * Get path for this navigation menu item.
	 * @return string
	 */
	function getPath() {
		return $this->getData('path');
	}

	/**
	 * Set page handler name for this navigation menu item.
	 * @param $page string
	 */
	function setPage($page) {
		$this->setData('page', $page);
	}

	/**
	 * Get page handler name for this navigation menu item.
	 * @return string
	 */
	function getPage() {
		return $this->getData('page');
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
		return $this->getData('defaultmenu');
	}

	/**
	 * Set defaultMenu for this navigation menu item.
	 * @param $default int
	 */
	function setDefaultMenu($default) {
		$this->setData('defaultmenu', $default);
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

	/**
	 * Get the content of the navigation Menu.
	 * @return string
	 */
	function getLocalizedContent() {
		return $this->getLocalizedData('content');
	}

	/**
	 * Get the content of the navigation menu item.
	 * @param $locale string
	 * @return string
	 */
	function getContent($locale) {
		return $this->getData('content', $locale);
	}

	/**
	 * Set the content of the navigation menu item.
	 * @param $content string
	 * @param $locale string
	 */
	function setContent($content, $locale) {
		$this->setData('content', $content, $locale);
	}

	function populateNavigationMenuItems() {
		$navigationMenuItemDao = DAORegistry::getDAO('NavigationMenuItemDAO');
		$navigationMenuItems = $navigationMenuItemDao->getChildrenNMIsByNavigationMenuItemId($this->getId(), true);

		$this->navigationMenuItems = $navigationMenuItems->toAssociativeArray();
	}
}

?>

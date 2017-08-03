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

/** ID codes and paths for all default navigationMenuItems */
define('NMI_ID_CURRENT',	0x00000001);
define('NMI_ID_ARCHIVES',	0x00000002);
define('NMI_ID_ABOUT',	0x00000003);
define('NMI_ID_ABOUT_CONTEXT',	0x00000004);
define('NMI_ID_SUBMISSIONS',	0x00000005);
define('NMI_ID_EDITORIAL_TEAM',	0x00000006);
define('NMI_ID_CONTACT',	0x00000007);
define('NMI_ID_LOGOUT',	0x00000008);
define('NMI_ID_ANNOUNCEMENTS',	0x00000009);

class NavigationMenuItem extends DataObject {
	/** @var $navigationMenuItems array The navigationMenuItems underneath this navigationMenuItem */
	var $navigationMenuItems = array();

	var $_isDispayed = true;

	//
	// Get/set methods
	//

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
	 * Set page's op handler name for this navigation menu item.
	 * @param $op string
	 */
	function setOp($op) {
		$this->setData('op', $op);
	}

	/**
	 * Get page's op handler name for this navigation menu item.
	 * @return string
	 */
	function getOp() {
		return $this->getData('op');
	}

	/**
	 * Get is_default for this navigation menu item.
	 * @return int
	 */
	function getDefault() {
		return $this->getData('is_default');
	}

	/**
	 * Set is_default for this navigation menu item.
	 * @param $default int
	 */
	function setDefault($default) {
		$this->setData('is_default', $default);
	}

	/**
	 * Get defaultId of this NavigationMenuItem
	 * @return int
	 */
	function getDefaultId() {
		return $this->getData('default_id');
	}

	/**
	 * Set defaultId of this NavigationMenuItem
	 * @param $defaultId int
	 */
	function setDefaultId($defaultId) {
		$this->setData('default_id', $defaultId);
	}

	/**
	 * Get use_custom_url of this NavigationMenuItem
	 * @return int
	 */
	function getUseCustomUrl() {
		return $this->getData('use_custom_url');
	}

	/**
	 * Set use_custom_url for this navigation menu item.
	 * @param $useCustomUrl int
	 */
	function setUseCustomUrl($useCustomUrl) {
		$this->setData('use_custom_url', $useCustomUrl);
	}

	/**
	 * Set custom_url for this navigation menu item.
	 * @param $customUrl string
	 */
	function setCustomUrl($customUrl) {
		$this->setData('custom_url', $customUrl);
	}

	/**
	 * Get custom_url for this navigation menu item.
	 * @return string
	 */
	function getCustomUrl() {
		return $this->getData('custom_url');
	}

	/**
	 * Set type for this navigation menu item.
	 * @param $type string
	 */
	function setType($type) {
		$this->setData('type', $type);
	}

	/**
	 * Get type for this navigation menu item.
	 * @return string
	 */
	function getType() {
		return $this->getData('type');
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
	 * Get $isDisplayed for this navigation menu item.
	 * @return boolean
	 */
	function getIsDisplayed() {
		return $this->_isDispayed;
	}

	/**
	 * Set $isDisplayed for this navigation menu item.
	 * @param $isDisplayed boolean
	 */
	function setIsDisplayed($isDisplayed) {
		$this->_isDispayed = $isDisplayed;
	}

	function getDisplayStatus() {
		$menuItemType = $this->getType();
		switch ($menuItemType) {
		  case 'announcements': // should be made as symbolic type - globally accessible
			// check if annoucements are enabled
			$this->setIsDisplayed($isAnnouncementsEnabled);
		  case 'userProfile':
			  // check if user is logged in
			  $this->setIsDisplayed($isUserLoggedIn);
		  default:
			// Fire hook for determining display status of third-party types. Default: true
			$display = true;
			HookRegistry::call('NavigationMenus::displayType', array(&$display, $menuItemType));
			$this->setIsDisplayed($display);
		}
	}
}

?>

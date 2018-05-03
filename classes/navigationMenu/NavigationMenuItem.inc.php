<?php

/**
 * @file classes/navigationMenu/NavigationMenuItem.inc.php
 *
 * Copyright (c) 2014-2018 Simon Fraser University
 * Copyright (c) 2000-2018 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class NavigationMenuItem
 * @ingroup navigationMenu
 * @see NavigationMenuItemDAO
 *
 * @brief Basic class describing a NavigationMenuItem.
 */

/** types for all default navigationMenuItems */
define('NMI_TYPE_ABOUT',	'NMI_TYPE_ABOUT');
define('NMI_TYPE_SUBMISSIONS',	'NMI_TYPE_SUBMISSIONS');
define('NMI_TYPE_EDITORIAL_TEAM',	'NMI_TYPE_EDITORIAL_TEAM');
define('NMI_TYPE_CONTACT',	'NMI_TYPE_CONTACT');
define('NMI_TYPE_ANNOUNCEMENTS',	'NMI_TYPE_ANNOUNCEMENTS');
define('NMI_TYPE_CUSTOM',	'NMI_TYPE_CUSTOM');
define('NMI_TYPE_REMOTE_URL',	'NMI_TYPE_REMOTE_URL');

define('NMI_TYPE_USER_LOGOUT',	'NMI_TYPE_USER_LOGOUT');
define('NMI_TYPE_USER_LOGOUT_AS',	'NMI_TYPE_USER_LOGOUT_AS');
define('NMI_TYPE_USER_PROFILE',	'NMI_TYPE_USER_PROFILE');
define('NMI_TYPE_ADMINISTRATION',	'NMI_TYPE_ADMINISTRATION');
define('NMI_TYPE_USER_DASHBOARD',	'NMI_TYPE_USER_DASHBOARD');
define('NMI_TYPE_USER_REGISTER',	'NMI_TYPE_USER_REGISTER');
define('NMI_TYPE_USER_LOGIN',	'NMI_TYPE_USER_LOGIN');
define('NMI_TYPE_SEARCH',	'NMI_TYPE_SEARCH');

class NavigationMenuItem extends DataObject {
	/** @var $navigationMenuItems array The navigationMenuItems underneath this navigationMenuItem */
	var $navigationMenuItems = array();

	var $_isDisplayed = true;
	var $_isChildVisible = false;

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
	 * Set url for this navigation menu item.
	 * @param $url string
	 */
	function setUrl($url) {
		$this->setData('url', $url);
	}

	/**
	 * Get url for this navigation menu item.
	 * @return string
	 */
	function getUrl() {
		return $this->getData('url');
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
		return $this->getData('contextId');
	}

	/**
	 * Set context_id for this navigation menu item.
	 * @param $contextId int
	 */
	function setContextId($contextId) {
		$this->setData('contextId', $contextId);
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
		return $this->_isDisplayed;
	}

	/**
	 * Set $isDisplayed for this navigation menu item.
	 * @param $isDisplayed boolean
	 */
	function setIsDisplayed($isDisplayed) {
		$this->_isDisplayed = $isDisplayed;
	}

	/**
	 * Get $isChildVisible for this navigation menu item.
	 * @return boolean true if at least one NMI child is visible. It is defined at the Service functionality level
	 */
	function getIsChildVisible() {
		return $this->_isChildVisible;
	}

	/**
	 * Set $isChildVisible for this navigation menu item.
	 * @param $isChildVisible boolean true if at least one NMI child is visible. It is defined at the Service functionality level
	 */
	function setIsChildVisible($isChildVisible) {
		$this->_isChildVisible = $isChildVisible;
	}
}

?>

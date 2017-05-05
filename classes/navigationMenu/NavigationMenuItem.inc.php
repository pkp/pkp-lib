<?php

/**
 * @file classes/announcement/AnnouncementType.inc.php
 *
 * Copyright (c) 2014-2017 Simon Fraser University
 * Copyright (c) 2000-2017 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class AnnouncementType
 * @ingroup announcement
 * @see AnnouncementTypeDAO, AnnouncementTypeForm
 *
 * @brief Basic class describing an announcement type.
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
		return $this->getData('assocId');
	}

	/**
	 * Set assoc ID for this navigation menu item.
	 * @param $assocId int
	 */
	function setAssocId($assocId) {
		$this->setData('assocId', $assocId);
	}

	/**
	 * Get seq for this navigation menu item.
	 * @return int
	 */
	function getSeq() {
		return $this->getData('seq');
	}

	/**
	 * Set seq for this navigation menu item.
	 * @param $assocId int
	 */
	function setSeq($seq) {
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
	 * @param $assocType int
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
	 * @return int
	 */
	function getPath() {
		return $this->getData('path');
	}

	/**
	 * Get the title of the navigation Menu.
	 * @return string
	 */
	function getLocalizedNavigationMenuTitle() {
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

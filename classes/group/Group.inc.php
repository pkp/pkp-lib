<?php

/**
 * @defgroup group
 */

/**
 * @file classes/group/Group.inc.php
 *
 * Copyright (c) 2013-2015 Simon Fraser University Library
 * Copyright (c) 2000-2015 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class Group
 * @ingroup group
 * @see GroupDAO
 *
 * @brief Describes user groups.
 */


define('GROUP_CONTEXT_EDITORIAL_TEAM',	0x000001);
define('GROUP_CONTEXT_PEOPLE',		0x000002);

class Group extends DataObject {
	/**
	 * Constructor
	 */
	function Group() {
		parent::DataObject();
	}

	/**
	 * Get localized title of group.
	 */
	function getLocalizedTitle() {
		return $this->getLocalizedData('title');
	}

	function getGroupTitle() {
		if (Config::getVar('debug', 'deprecation_warnings')) trigger_error('Deprecated function.');
		return $this->getLocalizedTitle();
	}


	//
	// Get/set methods
	//
	/**
	 * Get title of group (primary locale)
	 * @param $locale string
	 * @return string
	 */
	function getTitle($locale) {
		return $this->getData('title', $locale);
	}

	/**
	 * Set title of group
	 * @param $title string
	 * @param $locale string
	 */
	function setTitle($title, $locale) {
		return $this->setData('title', $title, $locale);
	}

	/**
	 * Get context of group
	 * @return int
	 */
	function getContext() {
		return $this->getData('context');
	}

	/**
	 * Set context of group
	 * @param $context int
	 */
	function setContext($context) {
		return $this->setData('context',$context);
	}

	/**
	 * Get publish email flag
	 * @return int
	 */
	function getPublishEmail() {
		return $this->getData('publishEmail');
	}

	/**
	 * Set publish email flag
	 * @param $context int
	 */
	function setPublishEmail($publishEmail) {
		return $this->setData('publishEmail',$publishEmail);
	}

	/**
	 * Get flag indicating whether or not the group is displayed in "About"
	 * @return boolean
	 */
	function getAboutDisplayed() {
		return $this->getData('aboutDisplayed');
	}

	/**
	 * Set flag indicating whether or not the group is displayed in "About"
	 * @param $aboutDisplayed boolean
	 */
	function setAboutDisplayed($aboutDisplayed) {
		return $this->setData('aboutDisplayed',$aboutDisplayed);
	}

	/**
	 * Get ID of group. Deprecated in favour of getId.
	 * @return int
	 */
	function getGroupId() {
		if (Config::getVar('debug', 'deprecation_warnings')) trigger_error('Deprecated function.');
		return $this->getId();
	}

	/**
	 * Set ID of group. DEPRECATED in favour of setId.
	 * @param $groupId int
	 */
	function setGroupId($groupId) {
		if (Config::getVar('debug', 'deprecation_warnings')) trigger_error('Deprecated function.');
		return $this->setId($groupId);
	}

	/**
	 * Get assoc ID for this group.
	 * @return int
	 */
	function getAssocId() {
		return $this->getData('assocId');
	}

	/**
	 * Set assoc ID for this group.
	 * @param $assocId int
	 */
	function setAssocId($assocId) {
		return $this->setData('assocId', $assocId);
	}

	/**
	 * Get assoc type for this group.
	 * @return int
	 */
	function getAssocType() {
		return $this->getData('assocType');
	}

	/**
	 * Set assoc type for this group.
	 * @param $assocType int
	 */
	function setAssocType($assocType) {
		return $this->setData('assocType', $assocType);
	}

	/**
	 * Get sequence of group.
	 * @return float
	 */
	function getSequence() {
		return $this->getData('sequence');
	}

	/**
	 * Set sequence of group.
	 * @param $sequence float
	 */
	function setSequence($sequence) {
		return $this->setData('sequence', $sequence);
	}
}

?>

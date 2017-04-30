<?php

/**
 * @defgroup announcement Announcement
 * Implements announcements that can be presented to website visitors.
 */

/**
 * @file classes/announcement/Announcement.inc.php
 *
 * Copyright (c) 2014-2017 Simon Fraser University
 * Copyright (c) 2000-2017 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class Announcement
 * @ingroup announcement
 * @see AnnouncementDAO
 *
 * @brief Basic class describing a announcement.
 */

class NavigationMenu extends DataObject {
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
	 * Get assoc ID for this annoucement.
	 * @return int
	 */
	function getAssocId() {
		return $this->getData('assocId');
	}

	/**
	 * Set assoc ID for this annoucement.
	 * @param $assocId int
	 */
	function setAssocId($assocId) {
		$this->setData('assocId', $assocId);
	}

	/**
	 * Get the announcement type of the announcement.
	 * @return int
	 */
	function getDefault() {
	    return $this->getData('default');
	}

	/**
	 * Set the announcement type of the announcement.
	 * @param $navigationMenuId int
	 */
	function setDefault($default) {
	    $this->setData('default', $default);
	}

	/**
	 * Get the announcement type of the announcement.
	 * @return int
	 */
	function getEnabled() {
	    return $this->getData('enabled');
	}

	/**
	 * Set the announcement type of the announcement.
	 * @param $navigationMenuId int
	 */
	function setEnabled($enabled) {
	    $this->setData('enabled', $enabled);
	}

	/**
	 * Get the announcement type of the announcement.
	 * @return int
	 */
	function getSeq() {
		return $this->getData('seq');
	}

	/**
	 * Set the announcement type of the announcement.
	 * @param $typeId int
	 */
	function setSeq($seq) {
		$this->setData('seq', $seq);
	}

	/**
	 * Get assoc ID for this annoucement.
	 * @return int
	 */
	function getContextId() {
		return $this->getData('contextId');
	}

	/**
	 * Set assoc ID for this annoucement.
	 * @param $assocId int
	 */
	function setContextId($contextId) {
		$this->setData('contextId', $contextId);
	}

	/**
	 * Get title
	 * @return string
	 */
	function getTitle() {
		return $this->getData('title');
	}

	/**
	 * Set title.
	 * @param $title string
	 * @param $locale string
	 */
	function setTitle($title) {
		$this->setData('title', $title);
	}

	///**
	// * Get localized short description
	// * @return string
	// */
	//function getLocalizedDescriptionShort() {
	//    return $this->getLocalizedData('descriptionShort');
	//}

	///**
	// * Get announcement brief description.
	// * @param $locale string
	// * @return string
	// */
	//function getDescriptionShort($locale) {
	//    return $this->getData('descriptionShort', $locale);
	//}

	///**
	// * Set announcement brief description.
	// * @param $descriptionShort string
	// * @param $locale string
	// */
	//function setDescriptionShort($descriptionShort, $locale) {
	//    $this->setData('descriptionShort', $descriptionShort, $locale);
	//}
}

?>

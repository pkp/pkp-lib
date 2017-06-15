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
	 * Get assoc ID for this NavigationMenu.
	 * TODO::defstat could be a string or an id - its for the Area implementation
	 * @return int
	 */
	function getAssocId() {
		return $this->getData('assoc_id');
	}

	/**
	 * Set assoc ID for this NavigationMenu.
	 * @param $assocId int
	 */
	function setAssocId($assocId) {
		$this->setData('assoc_id', $assocId);
	}

	/**
	 * Get the NavigationMenu 'default' status (can be edited/deleted if not default)
	 * @return int
	 */
	function getDefaultMenu() {
	    return $this->getData('defaultmenu');
	}

	/**
	 * Set the NavigationMenu 'default' status (can be edited/deleted if not default)
	 * @param $default int
	 */
	function setDefaultMenu($default) {
	    $this->setData('defaultmenu', $default);
	}

	/**
	 * Get the NavigationMenu 'enabled' status.
	 * @return int
	 */
	function getEnabled() {
	    return $this->getData('enabled');
	}

	/**
	 * Set the NavigationMenu 'enabled' status.
	 * @param $enabled int
	 */
	function setEnabled($enabled) {
	    $this->setData('enabled', $enabled);
	}

	/**
	 * Get the NavigationMenu sequence
	 * TODO::defstat May not be needed - added to support sequence inside an Area
	 * @return int
	 */
	function getSequence() {
		return $this->getData('seq');
	}

	/**
	 * Set the NavigationMenu sequence
	 * @param $seq int
	 */
	function setSequence($seq) {
		$this->setData('seq', $seq);
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
	 * Get title of this NavigationMenu. Not localised.
	 * @return string
	 */
	function getTitle() {
		return $this->getData('title');
	}

	/**
	 * Set title of this NavigationMenu. Not localised.
	 * @param $title string
	 */
	function setTitle($title) {
		$this->setData('title', $title);
	}
}

?>

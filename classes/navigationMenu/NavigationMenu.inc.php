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
	/** @var $menuTree array Hierarchical array of NavigationMenuItems */
	var $menuTree = null;

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
	function getDefault() {
	    return $this->getData('is_default');
	}

	/**
	 * Set the NavigationMenu 'default' status (can be edited/deleted if not default)
	 * @param $default int
	 */
	function setDefault($default) {
	    $this->setData('is_default', $default);
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

	/**
	 * Get areaName of this NavigationMenu. Not localised.
	 * @return string
	 */
	function getAreaName() {
		return $this->getData('area_name');
	}

	/**
	 * Set navigationArea name of this NavigationMenu. Not localised.
	 * @param $areaName string
	 */
	function setAreaName($areaName) {
		$this->setData('area_name', $areaName);
	}

	/**
	 * Get a tree of NavigationMenuItems assigned to this menu
	 *
	 * @return array Hierarchical array of menu items
	 */
	public function getMenuTree() {
		$navigationMenuItemDao = DAORegistry::getDAO('NavigationMenuItemDAO');
		$items = $navigationMenuItemDao->getByMenuId($this->getId())
				->toArray();
		$navigationMenuItemAssignmentDao = DAORegistry::getDAO('NavigationMenuItemAssignmentDAO');
		$assignments = $navigationMenuItemAssignmentDao->getByMenuId($this->getId())
				->toArray();

		for ($i = 0; $i < count($assignments); $i++) {
			foreach($items as $item) {
				if ($item->getId() === $assignments[$i]->getMenuItemId()) {
					$assignments[$i]->setMenuItem($item);
					break;
				}
			}
		}

		// Create an array of parent items and array of child items sorted by
		// their parent id as the array key
		$this->menuTree = array();
		$children = array();
		foreach ($assignments as $assignment) {
			if (!$assignment->getParentId()) {
				$this->menuTree[] = $assignment;
			} else {
				if (!isset($children[$assignment->getParentId()])) {
					$children[$assignment->getParentId()] = array();
				}
				$children[$assignment->getParentId()][] = $assignment;
			}
		}

		// Assign child items to parent in array
		for ($i = 0; $i < count($this->menuTree); $i++) {
			$assignmentId = $this->menuTree[$i]->getMenuItemId();
			if (isset($children[$assignmentId])) {
				$this->menuTree[$i]->children = $children[$assignmentId];
			}
		}

		return $this->menuTree;
	}
}

?>

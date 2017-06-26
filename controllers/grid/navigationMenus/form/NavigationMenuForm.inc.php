<?php

/**
 * @file controllers/grid/navigationMenus/form/NavigationMenuForm.inc.php
 *
 * Copyright (c) 2014-2017 Simon Fraser University
 * Copyright (c) 2000-2017 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class NavigationMenuForm
 * @ingroup controllers_grid_navigationMenus_form
 * @see NavigationMenu
 *
 * @brief Form for manager to create/edit NavigationMenus.
 */


import('lib.pkp.classes.form.Form');

class NavigationMenuForm extends Form {
	/** @var int Context ID */
	var $_contextId;

	/** @var $navigationMenu NavigationMenu The menu being edited */
	var $_navigationMenuId;

	/**
	 * Constructor
	 * @param $contextId int Context ID
	 * @param $navigationMenuId int NavigationMenu Id
	 */
	function __construct($contextId, $navigationMenuId = null) {
		$this->_navigationMenuId = !empty($navigationMenuId) ? (int) $navigationMenuId : null;
		$this->_contextId = $contextId;

		parent::__construct('controllers/grid/navigationMenus/form/navigationMenuForm.tpl');

		$this->addCheck(new FormValidator($this, 'title', 'required', 'manager.announcementTypes.form.typeNameRequired'));

		$this->addCheck(new FormValidatorPost($this));
		$this->addCheck(new FormValidatorCSRF($this));
	}

	/**
	 * Get a list of localized field names for this form
	 * @return array
	 */
	function getLocaleFieldNames() {
		return null;
	}

	/**
	 * @copydoc Form::fetch()
	 */
	function fetch($request) {
		$templateMgr = TemplateManager::getManager($request);

		$themePlugins = PluginRegistry::getPlugins('themes');
		if (is_null($themePlugins)) {
			$themePlugins = PluginRegistry::loadCategory('themes', true);
		}
		$enabledThemes = array();
		$activeThemeOptions = array();
		foreach ($themePlugins as $themePlugin) {
			$enabledThemes[basename($themePlugin->getPluginPath())] = $themePlugin->getDisplayName();
			if ($themePlugin->isActive()) {
				$activeThemeOptions = $themePlugin->getOptionsConfig();
			}
		}

		$activeThemeNavigationAreas = array();
		if (!count($activeThemeOptions['navigationMenuArea']) == 0) {
			$activeThemeNavigationAreas = array('' => __('common.none'));
		}

		foreach ($activeThemeOptions['navigationMenuArea'] as $navigationMenuArea) {
			$activeThemeNavigationAreas[$navigationMenuArea] = $navigationMenuArea;
		}

		$navigationMenuItemDao = DAORegistry::getDAO('NavigationMenuItemDAO');
		$navigationMenuItems = $navigationMenuItemDao->getByContextId($request->getContext()->getId())
				->toArray();
		$assignedItems = $navigationMenuItemDao->getByMenuId($this->_navigationMenuId)
				->toArray();
		$unassignedItems = array_udiff($navigationMenuItems, $assignedItems, function($a, $b) {
			return $a->getId() - $b->getId();
		});

		$templateMgr->assign(array(
			'enabledThemes' => $enabledThemes,
			'activeThemeNavigationAreas' => $activeThemeNavigationAreas,
			'unassignedItems' => $unassignedItems,
			'navigationMenuId' => $this->_navigationMenuId,
			'title' => $this->getData('title'),
			'navigationMenuArea' => $this->getData('area_name'),
			'menuTree' => $this->getData('menuTree'),
		));

		return parent::fetch($request);
	}

	/**
	 * Initialize form data from current NavigationMenu.
	 */
	function initData() {

		if (isset($this->_navigationMenuId) && $this->_navigationMenuId != 0) {
			$navigationMenusDao = DAORegistry::getDAO('NavigationMenuDAO');
			$navigationMenu = $navigationMenusDao->getById($this->_navigationMenuId);

			if ($navigationMenu != null) {
				$this->_data = array(
					'title' => $navigationMenu->getTitle(),
					'navigationMenuId' => $navigationMenu->getId(),
					'navigationMenuArea' => $navigationMenu->getAreaName(),
					'menuTree' => $navigationMenu->getMenuTree(),
				);
			} else {
				$this->_navigationMenuId = null;
			}
		}
	}

	/**
	 * Assign form data to user-submitted data.
	 */
	function readInputData() {
		$this->readUserVars(array('title', 'navigationMenuId', 'area_name', 'menuTree'));

	}

	/**
	 * Save NavigationMenu .
	 */
	function execute() {
		$navigationMenusDao = DAORegistry::getDAO('NavigationMenuDAO');
		$navigationMenuItemAssignmentDao = DAORegistry::getDAO('NavigationMenuItemAssignmentDAO');

		if (isset($this->_navigationMenuId)) {
			$navigationMenu = $navigationMenusDao->getById($this->_navigationMenuId);
		}

		if (!isset($navigationMenu)) {
			$navigationMenu = $navigationMenusDao->newDataObject();
		}

		$navigationMenu->setContextId($this->_contextId);
		$navigationMenu->setTitle($this->getData('title'));
		$navigationMenu->setAreaName($this->getData('area_name'));

		// Update or insert NavigationMenu
		if ($navigationMenu->getId() != null) {
			$navigationMenusDao->updateObject($navigationMenu);
		} else {
			$this->_navigationMenuId = $navigationMenusDao->insertObject($navigationMenu);
		}

		// Update NavigationMenuItemAssignment
		if ($this->_navigationMenuId) {
			$navigationMenuItemAssignmentDao->deleteByMenuId($this->_navigationMenuId);
			$menuTree = $this->getData('menuTree');
			if (!empty($menuTree)) {
				foreach ($menuTree as $menuItemId => $assignmentData) {
					$assignment = new NavigationMenuItemAssignment();
					$assignment->setMenuId($this->_navigationMenuId);
					$assignment->setMenuItemId((int) $menuItemId);
					$assignment->setSequence((int) $assignmentData['seq']);
					if (isset($assignmentData['parentId'])) {
						$assignment->setParentId((int) $assignmentData['parentId']);
					}
					$navigationMenuItemAssignmentDao->insertObject($assignment);
				}
			}
		}
	}

	/**
	 * Perform additional validation checks
	 * @copydoc Form::validate
	 */
	function validate() {
		$navigationMenuDao = DAORegistry::getDAO('NavigationMenuDAO');

		$navigationMenu = $navigationMenuDao->getByTitle($this->_contextId, $this->getData('title'));
		if (isset($navigationMenu) && $navigationMenu->getId() != $this->_navigationMenuId) {
			$this->addError('path', __('manager.navigationMenus.form.duplicateTitle'));
		}

		return parent::validate();
	}
}

?>

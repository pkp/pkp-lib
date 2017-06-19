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
	var $contextId;

	/** @var $navigationMenuId int the ID of the NavigationMenu being edited */
	var $navigationMenuId;

	/**
	 * Constructor
	 * @param $contextId int Context ID
	 * @param $navigationMenuId int NavigationMenu Id
	 */
	function __construct($contextId, $navigationMenuId = null) {
		$this->navigationMenuId = isset($navigationMenuId) ? (int) $navigationMenuId : null;
		$this->contextId = $contextId;

		parent::__construct('manager/navigationMenus/navigationMenuForm.tpl');

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
			$activeThemeNavigationAreas = array(0 => __('common.none'));
		}

		foreach ($activeThemeOptions['navigationMenuArea'] as $navigationMenuArea) {
			$activeThemeNavigationAreas[$navigationMenuArea] = $navigationMenuArea;
		}


		$templateMgr->assign(array(
			'enabledThemes' => $enabledThemes,
			'activeThemeNavigationAreas' => $activeThemeNavigationAreas,
		));

		$templateMgr->assign('navigationMenuId', $this->navigationMenuId);
		$templateMgr->assign('title', $this->getData('title'));
		$templateMgr->assign('navigationMenuArea', $this->getData('area_name'));

		return parent::fetch($request, 'controllers/grid/navigationMenus/form/navigationMenuForm.tpl');
	}

	/**
	 * Initialize form data from current NavigationMenu.
	 */
	function initData() {
		$navigationMenusDao = DAORegistry::getDAO('NavigationMenuDAO');

		if (isset($this->navigationMenuId) && $this->navigationMenuId != 0) {
		    $navigationMenu = $navigationMenusDao->getById($this->navigationMenuId);

		    if ($navigationMenu != null) {
		        $this->_data = array(
		            'title' => $navigationMenu->getTitle(),
					'navigationMenuId' => $navigationMenu->getId(),
					'navigationMenuArea' => $navigationMenu->getAreaName()
		        );
		    } else {
		        $this->navigationMenuId = null;
		    }
		}
	}

	/**
	 * Assign form data to user-submitted data.
	 */
	function readInputData() {
		$this->readUserVars(array('title', 'navigationMenuId', 'area_name'));

	}

	/**
	 * Save NavigationMenu .
	 */
	function execute() {
		$navigationMenusDao = DAORegistry::getDAO('NavigationMenuDAO');

		if (isset($this->navigationMenuId)) {
			$navigationMenu = $navigationMenusDao->getById($this->navigationMenuId);
		}

		if (!isset($navigationMenu)) {
			$navigationMenu = $navigationMenusDao->newDataObject();
		}

		$navigationMenu->setContextId($this->contextId);
		$navigationMenu->setTitle($this->getData('title'));
		$navigationMenu->setAreaName($this->getData('area_name'));

		// Update or insert NavigationMenu
		if ($navigationMenu->getId() != null) {
			$navigationMenusDao->updateObject($navigationMenu);
		} else {
			$navigationMenusDao->insertObject($navigationMenu);
		}
	}

	/**
	 * Perform additional validation checks
	 * @copydoc Form::validate
	 */
	function validate() {
		$navigationMenuDao = DAORegistry::getDAO('NavigationMenuDAO');

		$navigationMenu = $navigationMenuDao->getByTitle($this->contextId, $this->getData('title'));
		if (isset($navigationMenu) && $navigationMenu->getId() != $this->navigationMenuId) {
			$this->addError('path', __('manager.navigationMenus.form.duplicateTitle'));
		}

		return parent::validate();
	}
}

?>

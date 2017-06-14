<?php

/**
 * @file controllers/grid/navigationMenus/form/NavigationMenuItemsManagementForm.inc.php
 *
 * Copyright (c) 2014-2017 Simon Fraser University
 * Copyright (c) 2000-2017 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class NavigationMenuItemsManagementForm
 * @ingroup controllers_grid_navigationMenus
 *
 * @brief Form for managers to create/edit navigationMenuItems.
 */


import('lib.pkp.classes.form.Form');

class NavigationMenuItemsManagementForm extends Form {
	/** @var $navigationMenuId int the ID of the navigationMenu */
	var $navigationMenuId;

	/** @var int */
	var $_contextId;

	/**
	 * Constructor
	 * @param $contextId int
	 * @param $navigationMenuId int
	 */
	function __construct($contextId, $navigationMenuId) {
		$this->_contextId = $contextId;
		$this->navigationMenuId = $navigationMenuId;

		parent::__construct('manager/navigationMenus/navigationMenuItemsManagementForm.tpl');

		$this->addCheck(new FormValidatorPost($this));
		$this->addCheck(new FormValidatorCSRF($this));
	}


	//
	// Getters and setters.
	//
	/**
	 * Return if this form is read only or not.
	 */
	function isReadOnly() {
		return $this->_readOnly;
	}

	/**
	 * Get the current context id.
	 * @return int
	 */
	function getContextId() {
		return $this->_contextId;
	}


	//
	// Extended methods from Form.
	//

	/**
	 * @copydoc Form::fetch()
	 */
	function fetch($request) {
		$templateMgr = TemplateManager::getManager($request);

		$navigationMenuDao = DAORegistry::getDAO('NavigationMenuDAO');
		$navigationMenus = $navigationMenuDao->getByContextId($this->getContextId());

		$navigationMenuOptions = array();
		if (!$navigationMenus->wasEmpty()) {
			$navigationMenuOptions = array(0 => __('common.none'));
		}
		while ($navigationMenu = $navigationMenus->next()) {
			$navigationMenuOptions[$navigationMenu->getId()] = $navigationMenu->getTitle();
		}
		$templateMgr->assign('navigationMenus', $navigationMenuOptions);
		$templateMgr->assign('navigationMenuItemId', $this->navigationMenuItemId);
		$templateMgr->assign('navigationMenuId', $this->navigationMenuId);

		return parent::fetch($request, 'controllers/grid/navigationMenus/form/navigationMenuItemsManagementForm.tpl');
	}

	/**
	 * Initialize form data from current navigation menu item.
	 */
	function initData() {
		$navigationMenuItemDao = DAORegistry::getDAO('NavigationMenuItemDAO');
		$navigationMenuItem = $navigationMenuItemDao->getById($this->navigationMenuItemId);

		if ($navigationMenuItem) {
			$this->_data = array(
				'navigationMenuId' => $navigationMenuItem->getNavigationMenuId(),
				'path' => $navigationMenuItem->getPath(),
				'title' => $navigationMenuItem->getTitle(null)
			);
		} else {
			$this->navigationMenuItemId = null;
		}


	}

	/**
	 * Assign form data to user-submitted data.
	 */
	function readInputData() {
		$this->readUserVars(array('navigationMenuItemId', 'navigationMenuId', 'title', 'path'));
	}

	/**
	 * Save NavigationMenuItem.
	 * @param $request PKPRequest
	 */
	function execute($request) {
		$navigationMenuItemDao = DAORegistry::getDAO('NavigationMenuItemDAO');

		$navigationMenuItem = $navigationMenuItemDao->getById($this->navigationMenuItemId);
		if (!$navigationMenuItem) {
			$navigationMenuItem = $navigationMenuItemDao->newDataObject();
		}

		$navigationMenuItem->setNavigationMenuId($this->getData('navigationMenuId'));
		$navigationMenuItem->setPath($this->getData('path'));
		$navigationMenuItem->setAssocId($this->getData('assoc_id'));
		$navigationMenuItem->setTitle($this->getData('title'), null); // Localized
		$navigationMenuItem->setSeq(0);
		$navigationMenuItem->setDefaultMenu(0);
		$navigationMenuItem->setEnabled(1);
		$navigationMenuItem->setContextId($this->getContextId());

		// Update or insert navigation menu item
		if ($navigationMenuItem->getId()) {
			$navigationMenuItemDao->updateObject($navigationMenuItem);
		} else {
			$navigationMenuItemDao->insertObject($navigationMenuItem);
		}

		return $navigationMenuItem->getId();
	}

	/**
	 * Perform additional validation checks
	 * @copydoc Form::validate
	 */
	function validate() {
		// Validate that the a NavigationMenu has been selected.
		$navigationMenuId = $this->getData('navigationMenuId');

		if (!isset($navigationMenuId) || $navigationMenuId < 1) {
			$this->addError('navigationMenuId', __('manager.navigationMenus.form.navigationMenuRequired'));
		}

		return parent::validate();
	}

}

?>

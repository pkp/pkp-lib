<?php

/**
 * @file controllers/grid/navigationMenus/form/NavigationMenuItemsForm.inc.php
 *
 * Copyright (c) 2014-2017 Simon Fraser University
 * Copyright (c) 2000-2017 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class NavigationMenuItemsForm
 * @ingroup controllers_grid_navigationMenus
 *
 * @brief Form for managers to create/edit navigationMenuItems.
 */


import('lib.pkp.classes.form.Form');

class NavigationMenuItemsForm extends Form {
	/** @var $navigationMenuItemId int the ID of the navigationMenuItem */
	var $navigationMenuItemId;

	/** @var $navigationMenuIdParent int the ID of the navigationMenu that is the parent of the navigationMenuItem */
	var $navigationMenuIdParent;

	/** @var $navigationMenuId int the ID of the navigationMenu */
	var $navigationMenuId;

	/** @var int */
	var $_contextId;

	/**
	 * Constructor
	 * @param $contextId int
	 * @param $navigationMenuItemId int
	 * @param $navigationMenuIdParent int
	 */
	function __construct($contextId, $navigationMenuItemId, $navigationMenuIdParent) {
		$this->_contextId = $contextId;
		$this->navigationMenuItemId = $navigationMenuItemId;
		$this->navigationMenuIdParent = $navigationMenuIdParent;

		parent::__construct('manager/navigationMenus/navigationMenuItemsForm.tpl');

		$this->addCheck(new FormValidatorPost($this));
		$this->addCheck(new FormValidatorCSRF($this));
		$this->addCheck(new FormValidatorRegExp($this, 'path', 'required', 'manager.navigationMenus.form.pathRegEx', '/^[a-zA-Z0-9\/._-]+$/'));
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
		$templateMgr->assign('navigationMenuIdParent', $this->navigationMenuIdParent);
		$templateMgr->assign('navigationMenuId', $this->navigationMenuId);

		$context = $request->getContext();
		if ($context) $templateMgr->assign('allowedVariables', array(
			'contactName' => __('plugins.generic.tinymce.variables.principalContactName', array('value' => $context->getSetting('contactName'))),
			'contactEmail' => __('plugins.generic.tinymce.variables.principalContactEmail', array('value' => $context->getSetting('contactEmail'))),
			'supportName' => __('plugins.generic.tinymce.variables.supportContactName', array('value' => $context->getSetting('supportName'))),
			'supportPhone' => __('plugins.generic.tinymce.variables.supportContactPhone', array('value' => $context->getSetting('supportPhone'))),
			'supportEmail' => __('plugins.generic.tinymce.variables.supportContactEmail', array('value' => $context->getSetting('supportEmail'))),
		));

		return parent::fetch($request, 'controllers/grid/navigationMenus/form/navigationMenuItemsForm.tpl');
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
				'title' => $navigationMenuItem->getTitle(null),
				'parentNavigationMenuItemId' => $navigationMenuItem->getAssocId(),
				'page' => $navigationMenuItem->getPage(),
			);
			$this->setData('content', $navigationMenuItem->getContent(null)); // Localized
		} else {
			$this->navigationMenuItemId = null;
			$this->setData('content', "");
			$this->setData('page', 'navigationMenu');
		}


	}

	/**
	 * Assign form data to user-submitted data.
	 */
	function readInputData() {
		$this->readUserVars(array('navigationMenuItemId', 'content', 'navigationMenuId', 'navigationMenuIdParent', 'title', 'path', 'assoc_id', 'page'));
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
		$navigationMenuItem->setContent($this->getData('content'), null); // Localized
		$navigationMenuItem->setSequence($navigationMenuItem->getSequence());
		$navigationMenuItem->setDefaultMenu($navigationMenuItem->getDefaultMenu());
		$navigationMenuItem->setContextId($this->getContextId());
		$navigationMenuItem->setPage($this->getData('page'));

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

		$navigationMenuItemDao = DAORegistry::getDAO('NavigationMenuItemDAO');

		$navigationMenuItem = $navigationMenuItemDao->getByPath($this->_contextId, $this->getData('path'));
		if (isset($navigationMenuItem) && $navigationMenuItem->getId() != $this->navigationMenuItemId) {
			$this->addError('path', __('manager.navigationMenus.form.duplicatePath'));
		}

		$selectedNavigationMenuItemId = $this->getData('assoc_id');
		$selectedNavigationMenuItem = $navigationMenuItemDao->getById($selectedNavigationMenuItemId);
		if (isset($selectedNavigationMenuItem) && $selectedNavigationMenuItem->getAssocId() != 0) {
			$this->addError('path', __('manager.navigationMenus.form.parentNMICantBeChild'));
		}

		return parent::validate();
	}

}

?>

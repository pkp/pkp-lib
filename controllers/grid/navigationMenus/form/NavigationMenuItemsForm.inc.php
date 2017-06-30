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

	/** @var int */
	var $_contextId;

	/**
	 * Constructor
	 * @param $contextId int
	 * @param $navigationMenuItemId int
	 */
	function __construct($contextId, $navigationMenuItemId) {
		$this->_contextId = $contextId;
		$this->navigationMenuItemId = $navigationMenuItemId;

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

		$templateMgr->assign('navigationMenuItemId', $this->navigationMenuItemId);

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
				'path' => $navigationMenuItem->getPath(),
				'title' => $navigationMenuItem->getTitle(null),
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
		$this->readUserVars(array('navigationMenuItemId', 'content', 'title', 'path', 'page'));
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

		$navigationMenuItem->setPath($this->getData('path'));
		$navigationMenuItem->setTitle($this->getData('title'), null); // Localized
		$navigationMenuItem->setContent($this->getData('content'), null); // Localized
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
		$navigationMenuItemDao = DAORegistry::getDAO('NavigationMenuItemDAO');

		$navigationMenuItem = $navigationMenuItemDao->getByPath($this->_contextId, $this->getData('path'));
		if (isset($navigationMenuItem) && $navigationMenuItem->getId() != $this->navigationMenuItemId) {
			$this->addError('path', __('manager.navigationMenus.form.duplicatePath'));
		}

		return parent::validate();
	}

}

?>

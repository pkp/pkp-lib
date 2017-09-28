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
		$templateMgr =& TemplateManager::getManager($request);

		$templateMgr->assign('navigationMenuItemId', $this->navigationMenuItemId);

		$context = $request->getContext();
		if ($context) $templateMgr->assign('allowedVariables', array(
			'contactName' => __('plugins.generic.tinymce.variables.principalContactName', array('value' => $context->getSetting('contactName'))),
			'contactEmail' => __('plugins.generic.tinymce.variables.principalContactEmail', array('value' => $context->getSetting('contactEmail'))),
			'supportName' => __('plugins.generic.tinymce.variables.supportContactName', array('value' => $context->getSetting('supportName'))),
			'supportPhone' => __('plugins.generic.tinymce.variables.supportContactPhone', array('value' => $context->getSetting('supportPhone'))),
			'supportEmail' => __('plugins.generic.tinymce.variables.supportContactEmail', array('value' => $context->getSetting('supportEmail'))),
		));

		$types = array();

		import('classes.core.ServicesContainer');
		$types = ServicesContainer::instance()
			->get('navigationMenu')
			->getMenuItemTypes();

		$templateMgr->assign('navigationMenuTypes', $types);

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
				'url' => $navigationMenuItem->getUrl(),
				'type' => $navigationMenuItem->getType(),
			);
			$this->setData('content', $navigationMenuItem->getContent(null)); // Localized
		} else {
			$this->navigationMenuItemId = null;
			$this->setData('content', "");
		}


	}

	/**
	 * Assign form data to user-submitted data.
	 */
	function readInputData() {
		$this->readUserVars(array('navigationMenuItemId', 'content', 'title', 'path', 'url','type'));
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
		$navigationMenuItem->setDefault($navigationMenuItem->getDefault());
		$navigationMenuItem->setContextId($this->getContextId());
		$navigationMenuItem->setUrl($this->getData('url'));
		$navigationMenuItem->setType($this->getData('type'));

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
		if ($this->getData('type') && $this->getData('type') != "") {
			if ($this->getData('type') == NMI_TYPE_CUSTOM) {
				if (!preg_match('/^[a-zA-Z0-9\/._-]+$/', $this->getData('path'))) {
					$this->addError('path', __('manager.navigationMenus.form.pathRegEx'));
				}

				$navigationMenuItemDao = DAORegistry::getDAO('NavigationMenuItemDAO');

				$navigationMenuItem = $navigationMenuItemDao->getByPath($this->_contextId, $this->getData('path'));
				if (isset($navigationMenuItem) && $navigationMenuItem->getId() != $this->navigationMenuItemId) {
					$this->addError('path', __('manager.navigationMenus.form.duplicatePath'));
				}
			} elseif ($this->getData('type') == NMI_TYPE_REMOTE_URL) {
				if(!filter_var($this->getData('url'), FILTER_VALIDATE_URL)) {
					$this->addError('url', __('manager.navigationMenus.form.customUrlError'));
				}
			}
		} else {
			$this->addError('path', __('manager.navigationMenus.form.typeMissing'));
		}

		return parent::validate();
	}

}

?>

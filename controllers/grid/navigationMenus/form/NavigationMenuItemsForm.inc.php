<?php

/**
 * @file controllers/grid/navigationMenus/form/NavigationMenuItemsForm.inc.php
 *
 * Copyright (c) 2014-2018 Simon Fraser University
 * Copyright (c) 2000-2018 John Willinsky
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
	function fetch($request, $template = 'controllers/grid/navigationMenus/form/navigationMenuItemsForm.tpl', $display = false) {
		AppLocale::requireComponents(LOCALE_COMPONENT_APP_MANAGER);

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
		import('classes.core.ServicesContainer');
		$types = ServicesContainer::instance()
			->get('navigationMenu')
			->getMenuItemTypes();

		$typeTitles = array(0 => __('grid.navigationMenus.navigationMenu.selectType'));
		foreach ($types as $type => $settings) {
			$typeTitles[$type] = $settings['title'];
		}

		$typeDescriptions = array();
		foreach ($types as $type => $settings) {
			$typeDescriptions[$type] = $settings['description'];
		}

		$typeConditionalWarnings = array();
		foreach ($types as $type => $settings) {
			if (array_key_exists('conditionalWarning', $settings)) {
				$typeConditionalWarnings[$type] = $settings['conditionalWarning'];
			}
		}

		$templateArray = array(
			'navigationMenuItemTypeTitles' => $typeTitles,
			'navigationMenuItemTypeDescriptions' => json_encode($typeDescriptions),
			'navigationMenuItemTypeConditionalWarnings' => json_encode($typeConditionalWarnings),
		);

		\HookRegistry::call('NavigationMenus::nmiFormTemplateParameters', array(&$templateArray));

		$templateMgr->assign($templateArray);

		return parent::fetch($request, $template, $display);
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
				'menuItemType' => $navigationMenuItem->getType(),
				'selectedRelatedObjectId' => $navigationMenuItem->getPath(),
			);
			$this->setData('content', $navigationMenuItem->getContent(null)); // Localized
		}
	}

	/**
	 * Assign form data to user-submitted data.
	 */
	function readInputData() {
		$this->readUserVars(array('navigationMenuItemId', 'content', 'title', 'path', 'url','menuItemType', 'relatedSeriesId', 'relatedCategoryId'));
	}

	/**
	 * Save NavigationMenuItem.
	 */
	function execute() {
		$navigationMenuItemDao = DAORegistry::getDAO('NavigationMenuItemDAO');

		$navigationMenuItem = $navigationMenuItemDao->getById($this->navigationMenuItemId);
		if (!$navigationMenuItem) {
			$navigationMenuItem = $navigationMenuItemDao->newDataObject();
		}

		$navigationMenuItem->setPath($this->getData('path'));
		$navigationMenuItem->setTitle($this->getData('title'), null); // Localized
		$navigationMenuItem->setContent($this->getData('content'), null); // Localized
		$navigationMenuItem->setContextId($this->getContextId());
		$navigationMenuItem->setUrl($this->getData('url'));
		$navigationMenuItem->setType($this->getData('menuItemType'));

		if ($this->getData('menuItemType') == NMI_TYPE_SERIES) {
			$navigationMenuItem->setPath($this->getData('relatedSeriesId'));
		} else if ($this->getData('menuItemType') == NMI_TYPE_CATEGORY) {
			$navigationMenuItem->setPath($this->getData('relatedCategoryId'));
		}


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
	function validate($callHooks = true) {
		import('lib.pkp.classes.navigationMenu.NavigationMenuItem');
		if ($this->getData('menuItemType') && $this->getData('menuItemType') != "") {
			if ($this->getData('menuItemType') == NMI_TYPE_CUSTOM) {
				if (!preg_match('/^[a-zA-Z0-9\/._-]+$/', $this->getData('path'))) {
					$this->addError('path', __('manager.navigationMenus.form.pathRegEx'));
				}

				$navigationMenuItemDao = DAORegistry::getDAO('NavigationMenuItemDAO');

				$navigationMenuItem = $navigationMenuItemDao->getByPath($this->_contextId, $this->getData('path'));
				if (isset($navigationMenuItem) && $navigationMenuItem->getId() != $this->navigationMenuItemId) {
					$this->addError('path', __('manager.navigationMenus.form.duplicatePath'));
				}
			} elseif ($this->getData('menuItemType') == NMI_TYPE_REMOTE_URL) {
				if(!filter_var($this->getData('url'), FILTER_VALIDATE_URL)) {
					$this->addError('url', __('manager.navigationMenus.form.customUrlError'));
				}
			}
		} else {
			$this->addError('path', __('manager.navigationMenus.form.typeMissing'));
		}

		return parent::validate($callHooks);
	}

}



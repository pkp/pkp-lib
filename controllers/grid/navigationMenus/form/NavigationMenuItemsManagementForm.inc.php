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
	/** @var $navigationMenuIdParent int the ID of the parent navigationMenu */
	var $navigationMenuIdParent;

	/** @var int */
	var $_contextId;

	/**
	 * Constructor
	 * @param $contextId int
	 * @param $navigationMenuId int
	 */
	function __construct($contextId, $navigationMenuIdParent) {
		$this->_contextId = $contextId;
		$this->navigationMenuIdParent = $navigationMenuIdParent;

		// parent::__construct('manager/navigationMenus/navigationMenuItemsManagementForm.tpl');

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

		$templateMgr->assign('navigationMenuIdParent', $this->navigationMenuIdParent);

		return parent::fetch($request, 'controllers/grid/navigationMenus/form/navigationMenuItemsManagementForm.tpl');
	}

	/**
	 * Initialize form data from current navigation menu item.
	 */
	function initData() {

	}

	/**
	 * Assign form data to user-submitted data.
	 */
	function readInputData() {
		$this->readUserVars(array('navigationMenuIdParent'));
	}

	/**
	 * Save NavigationMenuItem.
	 * @param $request PKPRequest
	 */
	function execute($request) {
		import('lib.pkp.classes.controllers.listbuilder.ListbuilderHandler');
		ListbuilderHandler::unpack($request, $request->getUserVar('navigation_menu_items'), array($this, 'deleteEntry'), array($this, 'insertEntry'), array($this, 'updateEntry'));
	}

	/**
	 * Overriden method from ListbuilderHandler.
	 * @param $request Request
	 * @param $rowId mixed
	 * @param $newRowId array
	 */
	function updateEntry($request, $rowId, $newRowId) {
		$navigationMenuId = $request->getUserVar('navigationMenuIdParent');
		//$context = $request->getContext();
		//$contextId = $context->getId();

		$navigationMenuItemDao = DAORegistry::getDAO('NavigationMenuItemDAO');
		$navigationMenuItem = $navigationMenuItemDao->getById($rowId);
		switch ($newRowId['listId']) {
			case 'unselectedMenuItems':
				$navigationMenuItem->setNavigationMenuId(0);
				$navigationMenuItem->setSequence(0);
				break;
			case 'selectedMenuItems':
				$navigationMenuItem->setNavigationMenuId($navigationMenuId);
				$navigationMenuItem->setSequence((int) $newRowId['sequence']);
				break;
			default:
				assert(false);
		}

		$navigationMenuItemDao->updateObject($navigationMenuItem);
	}

	/**
	 * Avoid warnings when Listbuilder::unpack tries to call this method.
	 */
	function deleteEntry() {
		return false;
	}

	/**
	 * Avoid warnings when Listbuilder::unpack tries to call this method.
	 */
	function insertEntry() {
		return false;
	}

	/**
	 * Perform additional validation checks
	 * @copydoc Form::validate
	 */
	function validate() {
		// Validate that the a NavigationMenu has been selected.
		//$navigationMenuId = $this->getData('navigationMenuId');

		//if (!isset($navigationMenuId) || $navigationMenuId < 1) {
		//    $this->addError('navigationMenuId', __('manager.navigationMenus.form.navigationMenuRequired'));
		//}

		return parent::validate();
	}

}

?>

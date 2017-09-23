<?php

/**
 * @file controllers/grid/navigationMenus/form/NavigationMenuItemAssignmentsForm.inc.php
 *
 * Copyright (c) 2014-2017 Simon Fraser University
 * Copyright (c) 2000-2017 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class NavigationMenuItemAssignmentsForm
 * @ingroup controllers_grid_navigationMenus
 *
 * @brief Form for managers to edit navigationMenuItemAssignments.
 */


import('lib.pkp.classes.form.Form');

class NavigationMenuItemAssignmentsForm extends Form {
	/** @var $_navigationMenuItemAssignmentId int the ID of the navigationMenuItemAssignment */
	var $_navigationMenuItemAssignmentId;

	/** @var int */
	var $_contextId;

	/**
	 * Constructor
	 * @param $contextId int
	 * @param $navigationMenuItemAssignmentId int
	 */
	function __construct($contextId, $navigationMenuItemAssignmentId) {
		$this->_contextId = $contextId;
		$this->_navigationMenuItemAssignmentId = $navigationMenuItemAssignmentId;

		parent::__construct('manager/navigationMenus/navigationMenuItemAssignmentsForm.tpl');

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

		$templateMgr->assign('navigationMenuItemAssignmentId', $this->_navigationMenuItemAssignmentId);

		return parent::fetch($request, 'controllers/grid/navigationMenus/form/navigationMenuItemAssignmentsForm.tpl');
	}

	/**
	 * Initialize form data from current navigation menu item Assignment.
	 */
	function initData() {
		$navigationMenuItemAssignmentDao = DAORegistry::getDAO('NavigationMenuItemAssignmentDAO');
		$navigationMenuItemAssignment = $navigationMenuItemAssignmentDao->getById($this->_navigationMenuItemAssignmentId);

		if ($navigationMenuItemAssignment) {
			$this->_data = array(
				'title' => $navigationMenuItemAssignment->getTitle(null),
				'navigationMenuId' => $navigationMenuItemAssignment->getMenuId(),
				'navigationMenuItemId' => $navigationMenuItemAssignment->getMenuItemId(),
				'seq' => $navigationMenuItemAssignment->getSequence(),
				'parentId' => $navigationMenuItemAssignment->getParentId(),
			);
		}
	}

	/**
	 * Assign form data to user-submitted data.
	 */
	function readInputData() {
		$this->readUserVars(array('navigationMenuItemAssignmentId', 'title'));
	}

	/**
	 * Save NavigationMenuItemAssignment.
	 * @param $request PKPRequest
	 */
	function execute($request) {
		$navigationMenuItemAssignmentDao = DAORegistry::getDAO('NavigationMenuItemAssignmentDAO');

		$navigationMenuItemAssignment = $navigationMenuItemAssignmentDao->getById($this->_navigationMenuItemAssignmentId);

		$navigationMenuItemAssignment->setTitle($this->getData('title'), null); // Localized

		$navigationMenuItemAssignmentDao->updateObject($navigationMenuItemAssignment);

		return $navigationMenuItemAssignmentDao->getId();
	}

	/**
	 * Perform additional validation checks
	 * @copydoc Form::validate
	 */
	function validate() {
		return parent::validate();
	}

}

?>

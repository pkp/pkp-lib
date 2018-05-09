<?php

/**
 * @file controllers/grid/settings/user/form/UserForm.inc.php
 *
 * Copyright (c) 2014-2018 Simon Fraser University
 * Copyright (c) 2003-2018 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class UserForm
 * @ingroup controllers_grid_settings_user_form
 *
 * @brief Base class for user forms.
 */

import('lib.pkp.classes.form.Form');

class UserForm extends Form {

	/** @var Id of the user being edited */
	var $userId;

	/** @var Context for which to edit user roles */
	var $contextId;

	/**
	 * Constructor.
	 * @param $request PKPRequest
	 * @param $userId int optional
	 * @param $contextId int optional
	 */
	function __construct($template, $userId = null, $contextId = null) {
		parent::__construct($template);

		$request = Application::getRequest();

		if (is_null($contextId)) {
			$context = $request->getContext();
			$contextId = $context ? $context->getId() : CONTEXT_ID_NONE;
		}

		$this->userId = isset($userId) ? (int) $userId : null;
		$this->contextId = $contextId;

		if (!is_null($userId) && $request->getContext()) {
			$this->addCheck(new FormValidator($this, 'userGroupIds', 'required', 'manager.users.roleRequired'));
		}
	}

	/**
	 * Initialize form data from current user profile.
	 * @param $args array
	 * @param $request PKPRequest
	 */
	public function initData($args, $request) {
		if ($this->contextId) {
			$userGroupDao = DAORegistry::getDAO('UserGroupDAO');
			$userGroups = $userGroupDao->getByUserId($this->userId, $this->contextId);
			$userGroupIds = array();
			while ($userGroup = $userGroups->next()) {
				$userGroupIds[] = $userGroup->getId();
			}
			$this->setData('userGroupIds', $userGroupIds);
		}
	}

	/**
	 * @copydoc Form::readInputData()
	 */
	public function readInputData() {
		$this->readUserVars(array('userGroupIds'));
		parent::readInputData();
	}

	/**
	 * Display the form.
	 * @param $args array
	 * @param $request PKPRequest
	 */
	public function display($args, $request) {
		$templateMgr = TemplateManager::getManager($request);

		if ($this->contextId) {
			import('lib.pkp.controllers.list.users.SelectRoleListHandler');
			$selectRoleList = new SelectRoleListHandler(array(
				'contextId' => $this->contextId,
				'title' => 'grid.user.userRoles',
				'inputName' => 'userGroupIds[]',
				'selected' => $this->getData('userGroupIds'),
			));
			$templateMgr->assign(array(
				'contextId' => $this->contextId,
				'selectRoleListData' => json_encode($selectRoleList->getConfig()),
			));
		}


		return $this->fetch($request);
	}

	/**
	 * @copydoc Form::execute()
	 */
	function execute($args, $request) {

		if (isset($this->userId) && $this->contextId) {
			import('lib.pkp.classes.security.UserGroupAssignmentDAO');
			$userGroupAssignmentDao = DAORegistry::getDAO('UserGroupAssignmentDAO');
			$userGroupAssignmentDao->deleteAssignmentsByContextId($this->contextId, $this->userId);
			if ($this->getData('userGroupIds')) {
				$userGroupDao = DAORegistry::getDAO('UserGroupDAO');
				foreach ($this->getData('userGroupIds') as $userGroupId) {
					$userGroupDao->assignUserToGroup($this->userId, $userGroupId);
				}
			}
		}

		parent::execute($request);
	}

}

?>

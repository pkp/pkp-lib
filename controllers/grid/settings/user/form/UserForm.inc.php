<?php

/**
 * @file controllers/grid/settings/user/form/UserForm.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
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

	/**
	 * Constructor.
	 * @param $request PKPRequest
	 * @param $userId int optional
	 * @param $author Author optional
	 */
	function __construct($template, $userId = null) {
		parent::__construct($template);

		$this->userId = isset($userId) ? (int) $userId : null;

		if (!is_null($userId)) {
			$this->addCheck(new FormValidator($this, 'userGroupIds', 'required', 'manager.users.roleRequired'));
		}
	}

	/**
	 * Initialize form data from current user profile.
	 */
	public function initData() {

		$userGroupDao = DAORegistry::getDAO('UserGroupDAO'); /* @var $userGroupDao UserGroupDAO */
		$userGroups = $userGroupDao->getByUserId($this->userId);
		$userGroupIds = array();
		while ($userGroup = $userGroups->next()) {
			$userGroupIds[] = $userGroup->getId();
		}
		$this->setData('userGroupIds', $userGroupIds);

		parent::initData();
	}

	/**
	 * @copydoc Form::readInputData()
	 */
	public function readInputData() {
		$this->readUserVars(array('userGroupIds'));
		parent::readInputData();
	}

	/**
	 * @copydoc Form::display
	 */
	public function display($request = null, $template = null) {
		$context = $request->getContext();
		$contextId = $context ? $context->getId() : CONTEXT_ID_NONE;
		$templateMgr = TemplateManager::getManager($request);

		$allUserGroups = [];
		$userGroupDao = DAORegistry::getDAO('UserGroupDAO'); /* @var $userGroupDao UserGroupDAO */
		$userGroups = $userGroupDao->getByContextId($contextId);
		while ($userGroup = $userGroups->next()) {
			$allUserGroups[(int) $userGroup->getId()] = $userGroup->getLocalizedName();
		}

		$templateMgr->assign([
			'allUserGroups' => $allUserGroups,
			'assignedUserGroups' => array_map('intval', $this->getData('userGroupIds')),
		]);

		return $this->fetch($request);
	}

	/**
	 * @copydoc Form::execute()
	 */
	function execute(...$functionArgs) {
		if (isset($this->userId)) {
			$contextId = Application::get()->getRequest()->getContext()->getId();

			import('lib.pkp.classes.security.UserGroupAssignmentDAO');
			$userGroupAssignmentDao = DAORegistry::getDAO('UserGroupAssignmentDAO'); /* @var $userGroupAssignmentDao UserGroupAssignmentDAO */
			$userGroupAssignmentDao->deleteAssignmentsByContextId($contextId, $this->userId);
			if ($this->getData('userGroupIds')) {
				$userGroupDao = DAORegistry::getDAO('UserGroupDAO'); /* @var $userGroupDao UserGroupDAO */
				$contextUserGroupIds = array_keys($userGroupDao->getByContextId($contextId)->toAssociativeArray());

				// Only consider user groups that are in the current context
				$userGroupIds = array_intersect($contextUserGroupIds, $this->getData('userGroupIds'));
				foreach ($userGroupIds as $userGroupId) {
					$userGroupDao->assignUserToGroup($this->userId, $userGroupId);
				}
			}
		}

		parent::execute(...$functionArgs);
	}

}

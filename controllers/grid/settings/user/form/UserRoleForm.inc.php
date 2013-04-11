<?php

/**
 * @file controllers/grid/settings/user/form/UserRoleForm.inc.php
 *
 * Copyright (c) 2003-2013 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class UserRoleForm
 * @ingroup controllers_grid_settings_user_form
 *
 * @brief Form for managing roles for a newly created user.
 */

import('lib.pkp.controllers.grid.settings.user.form.UserForm');

class UserRoleForm extends UserForm {

	/* @var string Ûser full name */
	var $_userFullName;

	/**
	 * Constructor.
	 * @param int $userId
	 * @param string $userFullName
	 */
	function UserRoleForm($userId, $userFullName) {
		parent::UserForm('controllers/grid/settings/user/form/userRoleForm.tpl', $userId);

		$this->_userFullName = $userFullName;
		$this->addCheck(new FormValidatorPost($this));
	}

	/**
	 * Display the form.
	 * @param $args array
	 * @param $request PKPRequest
	 */
	function display($args, $request) {
		$helpTopicId = 'context.users.createNewUser';
		$templateMgr = TemplateManager::getManager($request);

		$templateMgr->assign('userId', $this->userId);
		$templateMgr->assign('userFullName', $this->_userFullName);
		$templateMgr->assign('helpTopicId', $helpTopicId);

		return $this->fetch($request);
	}

	/**
	 * Update user's roles.
	 * @param $args array
	 * @param $request PKPRequest
	 */
	function &execute($args, $request) {
		parent::execute($request);

		// Role management handled by parent form, just return user.
		$userDao = DAORegistry::getDAO('UserDAO');
		$user =& $userDao->getById($this->userId);
		return $user;
	}
}

?>

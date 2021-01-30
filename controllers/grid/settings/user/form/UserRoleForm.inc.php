<?php

/**
 * @file controllers/grid/settings/user/form/UserRoleForm.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class UserRoleForm
 * @ingroup controllers_grid_settings_user_form
 *
 * @brief Form for managing roles for a newly created user.
 */

import('lib.pkp.controllers.grid.settings.user.form.UserForm');

class UserRoleForm extends UserForm {

	/* @var string User full name */
	var $_userFullName;

	/**
	 * Constructor.
	 * @param int $userId
	 * @param string $userFullName
	 */
	function __construct($userId, $userFullName) {
		parent::__construct('controllers/grid/settings/user/form/userRoleForm.tpl', $userId);

		$this->_userFullName = $userFullName;
		$this->addCheck(new FormValidatorPost($this));
		$this->addCheck(new FormValidatorCSRF($this));
	}

	/**
	 * @copydoc UserForm::display
	 */
	function display($request = null, $template = null) {
		$templateMgr = TemplateManager::getManager($request);
		$templateMgr->assign(array(
			'userId' => $this->userId,
			'userFullName' => $this->_userFullName,
		));
		return parent::display($request, $template);
	}

	/**
	 * Update user's roles.
	 */
	function execute(...$functionParams) {
		parent::execute(...$functionParams);

		// Role management handled by parent form, just return user.
		$userDao = DAORegistry::getDAO('UserDAO'); /* @var $userDao UserDAO */
		return $userDao->getById($this->userId);
	}
}



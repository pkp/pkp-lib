<?php

/**
 * @file classes/user/form/BaseProfileForm.inc.php
 *
 * Copyright (c) 2014-2016 Simon Fraser University Library
 * Copyright (c) 2003-2016 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class BaseProfileForm
 * @ingroup user_form
 *
 * @brief Base form to edit an aspect of user profile.
 */

import('lib.pkp.classes.form.Form');

abstract class BaseProfileForm extends Form {

	/** @var User */
	var $_user;

	/**
	 * Constructor.
	 * @param $template string
	 * @param $user PKPUser
	 */
	function BaseProfileForm($template, $user) {
		parent::Form($template);

		$this->_user = $user;
		assert($user);

		// Validation checks for this form
		$this->addCheck(new FormValidatorPost($this));
	}

	/**
	 * Get the user associated with this profile
	 */
	function getUser() {
		return $this->_user;
	}

	/**
	 * Save profile settings.
	 * @param $request PKPRequest
	 */
	function execute($request, $user) {
		$userDao = DAORegistry::getDAO('UserDAO');
		$userDao->updateObject($user);

		if ($user->getAuthId()) {
			$authDao = DAORegistry::getDAO('AuthSourceDAO');
			$auth = $authDao->getPlugin($user->getAuthId());
		}

		if (isset($auth)) {
			$auth->doSetUserInfo($user);
		}
	}
}

?>

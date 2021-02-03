<?php

/**
 * @file classes/user/form/LoginChangePasswordForm.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class LoginChangePasswordForm
 * @ingroup user_form
 *
 * @brief Form to change a user's password in order to login.
 */

import('lib.pkp.classes.form.Form');

class LoginChangePasswordForm extends Form {

	/**
	 * Constructor.
	 */
	function __construct($site) {
		parent::__construct('user/loginChangePassword.tpl');

		// Validation checks for this form
		$form = $this;
		$this->addCheck(new FormValidatorCustom($this, 'oldPassword', 'required', 'user.profile.form.oldPasswordInvalid', function($password) use ($form) {
			return Validation::checkCredentials($form->getData('username'),$password);
		}));
		$this->addCheck(new FormValidatorLength($this, 'password', 'required', 'user.register.form.passwordLengthRestriction', '>=', $site->getMinPasswordLength()));
		$this->addCheck(new FormValidator($this, 'password', 'required', 'user.profile.form.newPasswordRequired'));
		$this->addCheck(new FormValidatorCustom($this, 'password', 'required', 'user.register.form.passwordsDoNotMatch', function($password) use ($form) {
			return $password == $form->getData('password2');
		}));
		$this->addCheck(new FormValidatorPost($this));
		$this->addCheck(new FormValidatorCSRF($this));
	}

	/**
	 * @copydoc Form::display
	 */
	function display($request = null, $template = null) {
		$templateMgr = TemplateManager::getManager($request);
		$site = $request->getSite();
		$templateMgr->assign('minPasswordLength', $site->getMinPasswordLength());
		parent::display($request, $template);
	}

	/**
	 * Assign form data to user-submitted data.
	 */
	function readInputData() {
		$this->readUserVars(array('username', 'oldPassword', 'password', 'password2'));
	}

	/**
	 * @copydoc Form::execute()
	 * @return boolean success
	 */
	function execute(...$functionArgs) {
		$userDao = DAORegistry::getDAO('UserDAO'); /* @var $userDao UserDAO */
		$user = $userDao->getByUsername($this->getData('username'), false);
		parent::execute(...$functionArgs);
		if ($user != null) {
			if ($user->getAuthId()) {
				$authDao = DAORegistry::getDAO('AuthSourceDAO'); /* @var $authDao AuthSourceDAO */
				$auth = $authDao->getPlugin($user->getAuthId());
			}

			if (isset($auth)) {
				$auth->doSetUserPassword($user->getUsername(), $this->getData('password'));
				$user->setPassword(Validation::encryptCredentials($user->getId(), Validation::generatePassword())); // Used for PW reset hash only
			} else {
				$user->setPassword(Validation::encryptCredentials($user->getUsername(), $this->getData('password')));
			}

			$user->setMustChangePassword(0);
			$userDao->updateObject($user);
			return true;

		} else {
			return false;
		}
	}
}



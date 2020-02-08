<?php

/**
 * @file classes/user/form/ChangePasswordForm.inc.php
 *
 * Copyright (c) 2014-2020 Simon Fraser University
 * Copyright (c) 2003-2020 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class ChangePasswordForm
 * @ingroup user_form
 *
 * @brief Form to change a user's password.
 */


import('lib.pkp.classes.form.Form');

class ChangePasswordForm extends Form {

	/** @var object */
	var $_user;

	/** @var object */
	var $_site;

	/**
	 * Constructor.
	 */
	function __construct($user, $site) {
		parent::__construct('user/changePassword.tpl');

		$this->_user = $user;
		$this->_site = $site;

		// Validation checks for this form
		$this->addCheck(new FormValidatorCustom($this, 'oldPassword', 'required', 'user.profile.form.oldPasswordInvalid', function($password) use ($user) {
			return Validation::checkCredentials($user->getUsername(),$password);
		}));
		$this->addCheck(new FormValidatorLength($this, 'password', 'required', 'user.register.form.passwordLengthRestriction', '>=', $site->getMinPasswordLength()));
		$this->addCheck(new FormValidator($this, 'password', 'required', 'user.profile.form.newPasswordRequired'));
		$form = $this;
		$this->addCheck(new FormValidatorCustom($this, 'password', 'required', 'user.register.form.passwordsDoNotMatch', function($password) use ($form) {
			return $password == $form->getData('password2');
		}));
		$this->addCheck(new FormValidatorCustom($this, 'password', 'required', 'user.profile.form.passwordSameAsOld', function($password) use ($form) {
			return $password != $form->getData('oldPassword');
		}));

		$this->addCheck(new FormValidatorPost($this));
		$this->addCheck(new FormValidatorCSRF($this));
	}

	/**
	 * Get the user associated with this password
	 */
	function getUser() {
		return $this->_user;
	}

	/**
	 * Get the site
	 */
	function getSite() {
		return $this->_site;
	}

	/**
	 * @copydoc Form::fetch
	 */
	function fetch($request, $template = null, $display = false) {
		$templateMgr = TemplateManager::getManager();
		$templateMgr->assign(array(
			'minPasswordLength' => $this->getSite()->getMinPasswordLength(),
			'username' =>  $this->getUser()->getUsername(),
		));
		return parent::fetch($request, $template, $display);
	}

	/**
	 * Assign form data to user-submitted data.
	 */
	function readInputData() {
		$this->readUserVars(array('oldPassword', 'password', 'password2'));
	}

	/**
	 * @copydoc Form::execute()
	 */
	function execute(...$functionArgs) {
		$user = $this->getUser();

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

		parent::execute(...$functionArgs);

		$userDao = DAORegistry::getDAO('UserDAO'); /* @var $userDao UserDAO */
		$userDao->updateObject($user);
	}
}



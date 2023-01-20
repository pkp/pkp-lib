<?php

/**
 * @file classes/user/form/ResetPasswordForm.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class ResetPasswordForm
 * @ingroup user_form
 *
 * @brief Form to reset a user's forgotten password.
 */


import('lib.pkp.classes.form.Form');

class ResetPasswordForm extends Form {

	/** @var object */
	protected $_user;

	/** @var object */
	protected $_site;

	/** @var string */
	protected $_hash;

	/**
	 * Constructor.
	 */
	public function __construct($user, $site, $hash) {
		parent::__construct('user/userPasswordReset.tpl');

		$this->_user = $user;
		$this->_site = $site;
		$this->_hash = $hash;

		$this->addCheck(new FormValidatorLength($this, 'password', 'required', 'user.register.form.passwordLengthRestriction', '>=', $site->getMinPasswordLength()));
		$this->addCheck(new FormValidator($this, 'password', 'required', 'user.profile.form.newPasswordRequired'));
		$form = $this;
		$this->addCheck(new FormValidatorCustom($this, 'password', 'required', 'user.register.form.passwordsDoNotMatch', function($password) use ($form) {
			return $password == $form->getData('password2');
		}));

		$this->addCheck(new FormValidatorPost($this));
		$this->addCheck(new FormValidatorCSRF($this));
	}

	/**
	 * Get the user associated with this password
	 */
	public function getUser() 
	{
		return $this->_user;
	}

	/**
	 * Get the site
	 */
	public function getSite() 
	{
		return $this->_site;
	}

	/**
	 * Get the password reset hash
	 */
	public function getHash()
	{
		return $this->_hash;
	}

	/**
	 * @copydoc Form::display
	 */
	public function display($request = null, $template = null) {
		$templateMgr = TemplateManager::getManager($request);
		$templateMgr->assign([
			'minPasswordLength' => $this->getSite()->getMinPasswordLength(),
			'username' =>  $this->getUser()->getUsername(),
			'hash' => $this->getHash(),
		]);
		parent::display($request, $template);
	}

	/**
	 * Assign form data to user-submitted data.
	 */
	public function readInputData() {
		$this->readUserVars(array('username', 'hash', 'password', 'password2'));
	}

	/**
	 * @copydoc Form::execute()
	 */
	public function execute(...$functionArgs) {
        
		$user = $this->getUser();
		$userDao = DAORegistry::getDAO('UserDAO'); /** @var UserDAO $userDao */

		if ($user->getAuthId()) {
			$authDao = DAORegistry::getDAO('AuthSourceDAO'); /** @var AuthSourceDAO $authDao */
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

		parent::execute(...$functionArgs);

		return true;
	}

	/**
	 * Validate the password reset hash
	 */
	public function validatePasswordResetHash()
	{
		if (Validation::verifyPasswordResetHash($this->getUser()->getId(), $this->getHash())) {
			return true;
		}

		return false;
	}

	/**
	 * Display the error page when passed invalid password reset hash
	 */
	public function displayInvalidHashErrorMessage($request, $template = null)
	{
		$this->setTemplate('frontend/pages/error.tpl');

		$templateMgr = TemplateManager::getManager($request);

		$templateMgr->assign([
			'errorMsg' => 'user.login.lostPassword.invalidHash',
			'backLink' => $request->url(null, null, 'lostPassword'),
			'backLinkLabel' => 'user.login.resetPassword',
		]);

		parent::display($request, $template);
	}

}



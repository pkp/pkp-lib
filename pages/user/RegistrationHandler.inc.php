<?php

/**
 * @file pages/user/RegistrationHandler.inc.php
 *
 * Copyright (c) 2014-2016 Simon Fraser University Library
 * Copyright (c) 2003-2016 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class RegistrationHandler
 * @ingroup pages_user
 *
 * @brief Handle requests for user registration.
 */


import('pages.user.UserHandler');

class RegistrationHandler extends UserHandler {
	/**
	 * Constructor
	 */
	function RegistrationHandler() {
		parent::UserHandler();
	}

	/**
	 * @see PKPHandler::initialize()
	 */
	function initialize($request, &$args) {
		AppLocale::requireComponents(LOCALE_COMPONENT_APP_COMMON);
		parent::initialize($request, $args);
	}

	/**
	 * Display registration form for new users.
	 * @param $args array
	 * @param $request PKPRequest
	 */
	function register($args, $request) {
		$this->validate($request);
		$this->setupTemplate($request);

		import('lib.pkp.classes.user.form.RegistrationForm');
		$regForm = new RegistrationForm($request->getSite());
		$regForm->initData($request);
		$regForm->display($request);
	}

	/**
	 * Validate user registration information and register new user.
	 * @param $args array
	 * @param $request PKPRequest
	 */
	function registerUser($args, $request) {
		$this->validate($request);
		$this->setupTemplate($request);

		import('lib.pkp.classes.user.form.RegistrationForm');
		$regForm = new RegistrationForm($request->getSite());
		$regForm->readInputData();
		if (!$regForm->validate()) {
			return $regForm->display($request);
		}

		$regForm->execute($request);

		// Inform the user of the email validation process. This must be run
		// before the disabled account check to ensure new users don't see the
		// disabled account message.
		if (Config::getVar('email', 'require_validation')) {
			$this->setupTemplate($request);
			$templateMgr = TemplateManager::getManager($request);
			$templateMgr->assign('requireValidation', true);
			$templateMgr->assign('pageTitle', 'user.login.registrationPendingValidation');
			$templateMgr->assign('messageTranslated', __('user.login.accountNotValidated', array('email' => $regForm->getData('email'))));
			return $templateMgr->fetch('frontend/pages/message.tpl');
		}

		$reason = null;
		if (Config::getVar('security', 'implicit_auth')) {
			Validation::login('', '', $reason);
		} else {
			Validation::login($regForm->getData('username'), $regForm->getData('password'), $reason);
		}

		if ($reason !== null) {
			$this->setupTemplate($request);
			$templateMgr = TemplateManager::getManager($request);
			$templateMgr->assign('pageTitle', 'user.login');
			$templateMgr->assign('errorMsg', $reason==''?'user.login.accountDisabled':'user.login.accountDisabledWithReason');
			$templateMgr->assign('errorParams', array('reason' => $reason));
			$templateMgr->assign('backLink', $request->url(null, 'login'));
			$templateMgr->assign('backLinkLabel', 'user.login');
			return $templateMgr->fetch('frontend/pages/error.tpl');
		}

		if ($source = $request->getUserVar('source')) {
			return $request->redirectUrlJson($source);
		} else {
			$request->redirect(null, 'user', 'registrationComplete');
		}
	}

	/**
	 * A landing page once users complete registration
	 * @param $args array
	 * @param $request PKPRequest
	 */
	function registrationComplete($args, $request) {
		if (!Validation::isLoggedIn()) {
			$request->redirect(null, 'login');
		}
		$this->setupTemplate($request);
		$templateMgr = TemplateManager::getManager($request);
		$templateMgr->assign('pageTitle', 'user.login.registrationComplete');
		return $templateMgr->fetch('frontend/pages/userRegisterComplete.tpl');
	}

	/**
	 * Check credentials and activate a new user
	 * @param $args array
	 * @param $request PKPRequest
	 */
	function activateUser($args, $request) {
		$username = array_shift($args);
		$accessKeyCode = array_shift($args);

		$userDao = DAORegistry::getDAO('UserDAO');
		$user = $userDao->getByUsername($username);
		if (!$user) $request->redirect(null, 'login');

		// Checks user and token
		import('lib.pkp.classes.security.AccessKeyManager');
		$accessKeyManager = new AccessKeyManager();
		$accessKeyHash = AccessKeyManager::generateKeyHash($accessKeyCode);
		$accessKey = $accessKeyManager->validateKey(
			'RegisterContext',
			$user->getId(),
			$accessKeyHash
		);

		if ($accessKey != null && $user->getDateValidated() === null) {
			// Activate user
			$user->setDisabled(false);
			$user->setDisabledReason('');
			$user->setDateValidated(Core::getCurrentDate());
			$userDao->updateObject($user);

			$templateMgr = TemplateManager::getManager($request);
			$templateMgr->assign('message', 'user.login.activated');
			return $templateMgr->display('frontend/pages/message.tpl');
		}
		$request->redirect(null, 'login');
	}

	/**
	 * Validation check.
	 * Checks if context allows user registration.
	 * @param $request PKPRequest
	 */
	function validate($request) {
		$context = $request->getContext();
		if ($context) {
			if ($context->getSetting('disableUserReg')) {
				// Users cannot register themselves for this context
				$this->setupTemplate($request);
				$templateMgr = TemplateManager::getManager($request);
				$templateMgr->assign('pageTitle', 'user.register');
				$templateMgr->assign('errorMsg', 'user.register.registrationDisabled');
				$templateMgr->assign('backLink', $request->url(null, 'login'));
				$templateMgr->assign('backLinkLabel', 'user.login');
				$templateMgr->display('frontend/pages/error.tpl');
				exit;
			}
		}
	}
}

?>

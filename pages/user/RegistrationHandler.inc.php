<?php

/**
 * @file pages/user/RegistrationHandler.inc.php
 *
 * Copyright (c) 2014 Simon Fraser University Library
 * Copyright (c) 2003-2014 John Willinsky
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
		$this->setupTemplate($request, true);

		$context = $request->getContext();
		$site = $request->getSite();

		if ($context != null) {
			import('classes.user.form.RegistrationForm');

			$existingUser = $request->getUserVar('existingUser') ? 1 : 0;

			$regForm = new RegistrationForm($site, $existingUser);

			$regForm->initData();
			$regForm->display($request);
		} else {
			$contextDao = Application::getContextDAO();
			$contexts = $contextDao->getAll(true);

			$templateMgr = TemplateManager::getManager($request);
			$templateMgr->assign('source', $request->getUserVar('source'));
			$templateMgr->assign('contexts', $contexts);
			$templateMgr->display('user/registerSite.tpl');
		}
	}

	/**
	 * Validate user registration information and register new user.
	 * @param $args array
	 * @param $request PKPRequest
	 */
	function registerUser($args, $request) {
		$this->validate($request);
		$this->setupTemplate($request, true);
		import('classes.user.form.RegistrationForm');

		$existingUser = $request->getUserVar('existingUser') ? 1 : 0;
		$site = $request->getSite();

		$regForm = new RegistrationForm($site, $existingUser);
		$regForm->readInputData();

		if ($regForm->validate()) {
			$regForm->execute($request);
			if (Config::getVar('email', 'require_validation')) {
				// Send them home; they need to deal with the
				// registration email.
				$request->redirect(null, 'index');
			}

			$reason = null;

			if (Config::getVar('security', 'implicit_auth')) {
				Validation::login('', '', $reason);
			} else {
				Validation::login($regForm->getData('username'), $regForm->getData('password'), $reason);
			}

			if ($reason !== null) {
				$this->setupTemplate($request, true);
				$templateMgr = TemplateManager::getManager($request);
				$templateMgr->assign('pageTitle', 'user.login');
				$templateMgr->assign('errorMsg', $reason==''?'user.login.accountDisabled':'user.login.accountDisabledWithReason');
				$templateMgr->assign('errorParams', array('reason' => $reason));
				$templateMgr->assign('backLink', $request->url(null, 'login'));
				$templateMgr->assign('backLinkLabel', 'user.login');
				return $templateMgr->display('common/error.tpl');
			}
			if($source = $request->getUserVar('source'))
				$request->redirectUrl($source);

			else $request->redirect(null, 'login');

		} else {
			$regForm->display($request);
		}
	}

	/**
	 * Show error message if user registration is not allowed.
	 * @param $args array
	 * @param $request PKPRequest
	 */
	function registrationDisabled($args, $request) {
		$this->setupTemplate($request, true);
		$templateMgr = TemplateManager::getManager($request);
		$templateMgr->assign('pageTitle', 'user.register');
		$templateMgr->assign('errorMsg', 'user.register.registrationDisabled');
		$templateMgr->assign('backLink', $request->url(null, 'login'));
		$templateMgr->assign('backLinkLabel', 'user.login');
		$templateMgr->display('common/error.tpl');
	}

	/**
	 * Check credentials and activate a new user
	 * @author Marc Bria <marc.bria@uab.es>
	 */
	function activateUser($args, $request) {
		$username = array_shift($args);
		$accessKeyCode = array_shift($args);

		$context = $request->getContext();
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
			return $templateMgr->display('common/message.tpl');
		}
		$request->redirect(null, 'login');
	}

	/**
	 * Validation check.
	 * Checks if context allows user registration.
	 */
	function validate($request) {
		$context = $request->getContext();
		if ($context != null) {
			$contextSettingsDao = $context->getSettingsDAO();
			if ($contextSettingsDao->getSetting($context->getId(), 'disableUserReg')) {
				// Users cannot register themselves for this context
				$this->registrationDisabled();
				exit;
			}
		}
	}
}

?>

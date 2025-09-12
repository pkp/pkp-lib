<?php

/**
 * @file pages/login/LoginHandler.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class LoginHandler
 * @ingroup pages_login
 *
 * @brief Handle login/logout requests.
 */


import('classes.handler.Handler');

class LoginHandler extends Handler {
	/**
	 * @copydoc PKPHandler::authorize()
	 */
	function authorize($request, &$args, $roleAssignments) {
		switch ($op = $request->getRequestedOp()) {
			case 'signInAsUser':
				import('lib.pkp.classes.security.authorization.RoleBasedHandlerOperationPolicy');
				$this->addPolicy(new RoleBasedHandlerOperationPolicy($request, array(ROLE_ID_MANAGER, ROLE_ID_SITE_ADMIN), array('signInAsUser')));
				break;
		}
		return parent::authorize($request, $args, $roleAssignments);
	}

	/**
	 * Display user login form.
	 * Redirect to user index page if user is already validated.
	 */
	function index($args, $request) {
		$this->setupTemplate($request);
		if (Validation::isLoggedIn()) {
			$this->sendHome($request);
		}

		if (Config::getVar('security', 'force_login_ssl') && $request->getProtocol() != 'https') {
			// Force SSL connections for login
			$request->redirectSSL();
		}

		$sessionManager = SessionManager::getManager();
		$session = $sessionManager->getUserSession();

		$templateMgr = TemplateManager::getManager($request);
		$templateMgr->assign(array(
			'loginMessage' => $request->getUserVar('loginMessage'),
			'username' => $request->getUserVar('username') ?? $session->getSessionVar('username'),
			'remember' => $request->getUserVar('remember'),
			'source' => $request->getUserVar('source'),
			'showRemember' => Config::getVar('general', 'session_lifetime') > 0,
		));

		// For force_login_ssl with base_url[...]: make sure SSL used for login form
		$loginUrl = $request->url(null, 'login', 'signIn');
		if (Config::getVar('security', 'force_login_ssl')) {
			$loginUrl = PKPString::regexp_replace('/^http:/', 'https:', $loginUrl);
		}
		$templateMgr->assign('loginUrl', $loginUrl);

		if (Config::getVar('captcha', 'recaptcha') && Config::getVar('captcha', 'captcha_on_login')) {
			$publicKey = Config::getVar('captcha', 'recaptcha_public_key');
			$reCaptchaHtml = '<div class="g-recaptcha" data-sitekey="' . $publicKey . '"></div><label for="g-recaptcha-response" style="display:none;">Recaptcha response</label>';
			$templateMgr->assign(['recaptchaHtml' => $reCaptchaHtml]);
		}

		$templateMgr->display('frontend/pages/userLogin.tpl');
	}

	/**
	 * After a login has completed, direct the user somewhere.
	 * @param $request PKPRequest
	 */
	function _redirectAfterLogin($request) {
		$context = $this->getTargetContext($request);
		// If there's a context, send them to the dashboard after login.
		if ($context && $request->getUserVar('source') == '' && array_intersect(
			array(ROLE_ID_SITE_ADMIN, ROLE_ID_MANAGER, ROLE_ID_SUB_EDITOR, ROLE_ID_AUTHOR, ROLE_ID_REVIEWER, ROLE_ID_ASSISTANT),
			(array) $this->getAuthorizedContextObject(ASSOC_TYPE_USER_ROLES)
		)) {
			return $request->redirect($context->getPath(), 'dashboard');
		}

		$request->redirectHome();
	}

	/**
	 * Validate a user's credentials and log the user in.
	 */
	function signIn($args, $request) {
		$this->setupTemplate($request);
		if (Validation::isLoggedIn()) $this->sendHome($request);

		if (Config::getVar('security', 'force_login_ssl') && $request->getProtocol() != 'https') {
			// Force SSL connections for login
			$request->redirectSSL();
		}

		$captchaEnabled = Config::getVar('captcha', 'captcha_on_login') && Config::getVar('captcha', 'recaptcha');
		if ($captchaEnabled) {
			import('lib.pkp.classes.form.Form');
			$form = new Form();
			$form->setData('g-recaptcha-response', $request->getUserVar('g-recaptcha-response'));

			import('lib.pkp.classes.form.validation.FormValidatorReCaptcha');
			$captchaValidator = new FormValidatorReCaptcha(
				$form,
				$request->getRemoteAddr(),
				'common.captcha.error.invalid-input-response',
				$request->getServerHost()
			);

			if (!$captchaValidator->isValid()) {
				$templateMgr = TemplateManager::getManager($request);
				$templateMgr->assign([
					'username' => $request->getUserVar('username'),
					'remember' => $request->getUserVar('remember'),
					'source' => $request->getUserVar('source'),
					'showRemember' => Config::getVar('general', 'session_lifetime') > 0,
					'error' => $captchaValidator->getMessage(),
					'reCaptchaHtml' => '<div class="g-recaptcha" data-sitekey="' . Config::getVar('captcha', 'recaptcha_public_key') . '"></div><label for="g-recaptcha-response" style="display:none;">Recaptcha response</label>',
				]);
				$templateMgr->display('frontend/pages/userLogin.tpl');
				return;
			}
		}

		$user = Validation::login($request->getUserVar('username'), $request->getUserVar('password'), $reason, $request->getUserVar('remember') == null ? false : true);
		if ($user !== false) {
			if ($user->getMustChangePassword()) {
				// User must change their password in order to log in
				Validation::logout();
				$request->redirect(null, null, 'changePassword', $user->getUsername());

			} else {
				$source = str_replace('@', '', $request->getUserVar('source'));
				$redirectNonSsl = Config::getVar('security', 'force_login_ssl') && !Config::getVar('security', 'force_ssl');
				if (preg_match('#^/\w#', $source ?? '') === 1) {
					$request->redirectUrl($source);
				}
				if ($redirectNonSsl) {
					$request->redirectNonSSL();
				} else {
					$this->_redirectAfterLogin($request);
				}
			}

		} else {
			$sessionManager = SessionManager::getManager();
			$session = $sessionManager->getUserSession();
			$templateMgr = TemplateManager::getManager($request);
			$templateMgr->assign(array(
				'username' => $request->getUserVar('username'),
				'remember' => $request->getUserVar('remember'),
				'source' => $request->getUserVar('source'),
				'showRemember' => Config::getVar('general', 'session_lifetime') > 0,
				'error' => $reason===null?'user.login.loginError':($reason===''?'user.login.accountDisabled':'user.login.accountDisabledWithReason'),
				'reason' => $reason,
			));
			$templateMgr->display('frontend/pages/userLogin.tpl');
		}
	}

	/**
	 * Log a user out.
	 */
	function signOut($args, $request) {
		$this->setupTemplate($request);
		if (Validation::isLoggedIn()) {
			Validation::logout();
		}

		$source = str_replace('@', '', $request->getUserVar('source'));
		if (isset($source) && !empty($source)) {
			$request->redirectUrl($request->getProtocol() . '://' . $request->getServerHost() . '/' . $source, false);
		} else {
			$request->redirect(null, $request->getRequestedPage());
		}
	}

	/**
	 * Display form to reset a user's password.
	 */
	function lostPassword($args, $request) {
		if (Validation::isLoggedIn()) {
			$this->sendHome($request);
		}

		$this->setupTemplate($request);
		$templateMgr = TemplateManager::getManager($request);

		// Add recaptcha if enabled
		$captchaEnabled = Config::getVar('captcha', 'captcha_on_password_reset') && Config::getVar('captcha', 'recaptcha');
		if ($captchaEnabled) {
			$publicKey = Config::getVar('captcha', 'recaptcha_public_key');
			$reCaptchaHtml = '<div class="g-recaptcha" data-sitekey="' . $publicKey . '"></div><label for="g-recaptcha-response" style="display:none;">Recaptcha response</label>';
			$templateMgr->assign([
				'reCaptchaHtml' => $reCaptchaHtml,
				'captchaEnabled' => true,
			]);
		}
		$templateMgr->display('frontend/pages/userLostPassword.tpl');
	}

	/**
	 * Send a request to reset a user's password
	 */
	function requestResetPassword($args, $request) {
		$this->setupTemplate($request);
		$templateMgr = TemplateManager::getManager($request);

		// Check if reCaptcha is enabled and validate it if so
		$captchaEnabled = Config::getVar('captcha', 'captcha_on_password_reset') && Config::getVar('captcha', 'recaptcha');
		if ($captchaEnabled) {
			import('lib.pkp.classes.form.Form');
			$form = new Form();
			$form->setData('g-recaptcha-response', $request->getUserVar('g-recaptcha-response'));

			import('lib.pkp.classes.form.validation.FormValidatorReCaptcha');
			$captchaValidator = new FormValidatorReCaptcha(
				$form,
				$request->getRemoteAddr(),
				'common.captcha.error.invalid-input-response',
				$request->getServerHost()
			);

			if (!$captchaValidator->isValid()) {
				$templateMgr->assign([
					'reCaptchaHtml' => '<div class="g-recaptcha" data-sitekey="' . Config::getVar('captcha', 'recaptcha_public_key') . '"></div><label for="g-recaptcha-response" style="display:none;">Recaptcha response</label>',
					'error' => $captchaValidator->getMessage(),
					'email' => $request->getUserVar('email'),
				]);
				$templateMgr->display('frontend/pages/userLostPassword.tpl');
				return;
			}
		}

		$email = $request->getUserVar('email');
		$userDao = DAORegistry::getDAO('UserDAO'); /** @var UserDAO $userDao */
		$user = $userDao->getUserByEmail($email);

		if ($user !== null && ($hash = Validation::generatePasswordResetHash($user->getId())) !== false) {

			if ($user->getDisabled()) {
				$templateMgr
					->assign([
						'error' => 'user.login.lostPassword.confirmationSentFailedWithReason',
						'reason' => empty($reason = $user->getDisabledReason() ?? '')
							? __('user.login.accountDisabled')
							: __('user.login.accountDisabledWithReason', ['reason' => $reason])
					])
					->display('frontend/pages/userLostPassword.tpl');

				return;
			}

			// Send email confirming password reset as all check has passed
			import('lib.pkp.classes.mail.MailTemplate');
			$mail = new MailTemplate('PASSWORD_RESET_CONFIRM');
			$site = $request->getSite();
			$this->_setMailFrom($request, $mail, $site);
			$mail->assignParams([
				'url' => $request->url(null, 'login', 'resetPassword', $user->getUsername(), array('confirm' => $hash)),
				'siteTitle' => htmlspecialchars($site->getLocalizedTitle()),
				'recipientUsername' => $user->getUsername(),
			]);
			$mail->addRecipient($user->getEmail(), $user->getFullName());
			if ($mail->isEnabled()) {
				$mail->send();
			}
		}

		$templateMgr->assign([
			'pageTitle' => 'user.login.resetPassword',
			'message' => 'user.login.lostPassword.confirmationSent',
			'backLink' => $request->url(null, $request->getRequestedPage(), null, null, $user ? ['username' => $user->getUsername()] : []),
			'backLinkLabel' => 'user.login',
		])->display('frontend/pages/message.tpl');

	}

	/**
	 * Present the password reset form to reset user's password
	 * @param $args array first param contains the username of the user whose password is to be reset
	 */
	function resetPassword($args, $request) {

		if (Validation::isLoggedIn()) {
			$this->sendHome($request);
		}

		$this->_isBackendPage = true;
		$this->setupTemplate($request);
		$templateMgr = TemplateManager::getManager($request);
		$templateMgr->setupBackendPage();
		$templateMgr->assign([
			'pageTitle' => 'user.login.resetPassword',
		]);

		$username = isset($args[0]) ? $args[0] : null;
		$userDao = DAORegistry::getDAO('UserDAO'); /** @var UserDAO $userDao */
		$confirmHash = $request->getUserVar('confirm');

		if ($username == null || ($user = $userDao->getByUsername($username)) == null) {
			$request->redirect(null, null, 'lostPassword');
		}

		if ($user->getDisabled()) {
			$templateMgr
				->assign([
					'backLink' => $request->url(null, $request->getRequestedPage()),
					'backLinkLabel' => 'user.login',
					'messageTranslated' => __(
						'user.login.lostPassword.confirmationSentFailedWithReason',
						[
							'reason' => empty($reason = $user->getDisabledReason() ?? '')
								? __('user.login.accountDisabled')
								: __('user.login.accountDisabledWithReason', ['reason' => $reason])
						]
					),
				])
				->display('frontend/pages/message.tpl');

			return;
		}

		import('lib.pkp.classes.user.form.ResetPasswordForm');

		$passwordResetForm = new ResetPasswordForm($user, $request->getSite(), $confirmHash);
		$passwordResetForm->initData();


		$passwordResetForm->validatePasswordResetHash($request)
			? $passwordResetForm->display($request)
			: $passwordResetForm->displayInvalidHashErrorMessage($request);
	}

	/**
	 * Reset a user's password
	 * @param $args array first param contains the username of the user whose password is to be reset
	 */
	public function updateResetPassword($args, $request)
	{
		$this->_isBackendPage = true;
		$this->setupTemplate($request);
		$templateMgr = TemplateManager::getManager($request);

		$username = $request->getUserVar('username');
		$userDao = DAORegistry::getDAO('UserDAO'); /** @var UserDAO $userDao */
		$confirmHash = $request->getUserVar('hash');

		if ($username == null || ($user = $userDao->getByUsername($username)) == null) {
			$request->redirect(null, null, 'lostPassword');
		}

		import('lib.pkp.classes.user.form.ResetPasswordForm');

		$passwordResetForm = new ResetPasswordForm($user, $request->getSite(), $confirmHash);
		$passwordResetForm->readInputData();

		if ( !$passwordResetForm->validatePasswordResetHash($request) ) {
			return $passwordResetForm->displayInvalidHashErrorMessage($request);
		}

		if ($passwordResetForm->validate()) {
			if ($passwordResetForm->execute()) {
				$templateMgr->assign([
					'pageTitle' => 'user.login.resetPassword',
					'message' => 'user.login.resetPassword.passwordUpdated',
					'backLink' => $request->url(null, $request->getRequestedPage(), null, null, ['username' => $user->getUsername()]),
					'backLinkLabel' => 'user.login',
				]);

				$templateMgr->display('frontend/pages/message.tpl');
			}
		} else {
			$passwordResetForm->display($request);
		}
	}

	/**
	 * Display form to change user's password.
	 * @param $args array first argument may contain user's username
	 */
	function changePassword($args, $request) {
		$this->_isBackendPage = true;
		$this->setupTemplate($request);
		$templateMgr = TemplateManager::getManager($request);
		$templateMgr->setupBackendPage();
		$templateMgr->assign([
			'pageTitle' => __('user.changePassword'),
		]);

		import('lib.pkp.classes.user.form.LoginChangePasswordForm');
		$passwordForm = new LoginChangePasswordForm($request->getSite());
		$passwordForm->initData();
		if (isset($args[0])) {
			$passwordForm->setData('username', $args[0]);
		}
		$passwordForm->display($request);
	}

	/**
	 * Save user's new password.
	 */
	function savePassword($args, $request) {
		$this->_isBackendPage = true;
		$this->setupTemplate($request);

		import('lib.pkp.classes.user.form.LoginChangePasswordForm');

		$passwordForm = new LoginChangePasswordForm($request->getSite());
		$passwordForm->readInputData();

		if ($passwordForm->validate()) {
			if ($passwordForm->execute()) {
				$user = Validation::login($passwordForm->getData('username'), $passwordForm->getData('password'), $reason);
			}
			$this->sendHome($request);
		} else {
			$passwordForm->display($request);
		}
	}

	/**
	 * Sign in as another user.
	 * @param $args array ($userId)
	 * @param $request PKPRequest
	 */
	function signInAsUser($args, $request) {
		if (isset($args[0]) && !empty($args[0])) {
			$userId = (int)$args[0];
			$session = $request->getSession();
			if (!Validation::canAdminister($userId, $session->getUserId())) {
				$this->setupTemplate($request);
				// We don't have administrative rights
				// over this user. Display an error.
				$templateMgr = TemplateManager::getManager($request);
				$templateMgr->assign(array(
					'pageTitle' => 'manager.people',
					'errorMsg' => 'manager.people.noAdministrativeRights',
					'backLink' => $request->url(null, null, 'people', 'all'),
					'backLinkLabel' => 'manager.people.allUsers',
				));
				return $templateMgr->display('frontend/pages/error.tpl');
			}

			$userDao = DAORegistry::getDAO('UserDAO'); /** @var UserDAO $userDao */
			$newUser = $userDao->getById($userId);

			if (isset($newUser) && $session->getUserId() != $newUser->getId()) {
				$session->setSessionVar('signedInAs', $session->getUserId());
				$session->setSessionVar('userId', $userId);
				$session->setUserId($userId);
				$session->setSessionVar('username', $newUser->getUsername());
				$this->_redirectByURL($request);
			}
		}

		$request->redirect(null, $request->getRequestedPage());
	}


	/**
	 * Restore original user account after signing in as a user.
	 * @param $args array
	 * @param $request PKPRequest
	 */
	function signOutAsUser($args, $request) {
		$session = $request->getSession();
		$signedInAs = $session->getSessionVar('signedInAs');

		if (isset($signedInAs) && !empty($signedInAs)) {
			$signedInAs = (int)$signedInAs;

			$userDao = DAORegistry::getDAO('UserDAO'); /** @var UserDAO $userDao */
			$oldUser = $userDao->getById($signedInAs);

			$session->unsetSessionVar('signedInAs');

			if (isset($oldUser)) {
				$session->setSessionVar('userId', $signedInAs);
				$session->setUserId($signedInAs);
				$session->setSessionVar('username', $oldUser->getUsername());
			}
		}
		$this->_redirectByURL($request);
	}


	/**
	 * Redirect to redirectURL if exists else send to Home
	 * @param $request PKPRequest
	 */
	function _redirectByURL($request) {
		$requestVars  = $request->getUserVars();
		if (isset($requestVars['redirectUrl']) && !empty($requestVars['redirectUrl'])) {
			$request->redirectUrl($requestVars['redirectUrl']);
		} else {
			$this->sendHome($request);
		}
	}


	/**
	 * Helper function - set mail From
	 * can be overriden by child classes
	 * @param $request PKPRequest
	 * @param MailTemplate $mail
	 * @param $site Site
	 */
	function _setMailFrom($request, $mail, $site) {
		$mail->setReplyTo($site->getLocalizedContactEmail(), $site->getLocalizedContactName());
		return true;
	}

	/**
	 * Send the user "home" (typically to the dashboard, but that may not
	 * always be available).
	 * @param $request PKPRequest
	 */
	protected function sendHome($request) {
		if ($request->getContext()) $request->redirect(null, 'submissions');
		else $request->redirect(null, 'user');
	}

	/**
	 * Configure the template for display.
	 */
	function setupTemplate($request) {
		AppLocale::requireComponents(LOCALE_COMPONENT_APP_MANAGER, LOCALE_COMPONENT_PKP_MANAGER);
		parent::setupTemplate($request);
	}
}


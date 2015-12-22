<?php

/**
 * @file pages/login/PKPLoginHandler.inc.php
 *
 * Copyright (c) 2014-2015 Simon Fraser University Library
 * Copyright (c) 2000-2015 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class PKPLoginHandler
 * @ingroup pages_login
 *
 * @brief Handle login/logout requests.
 */


import('classes.handler.Handler');

class PKPLoginHandler extends Handler {
	/**
	 * Constructor
	 */
	function PKPLoginHandler() {
		parent::Handler();
	}

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
			$request->redirect(null, 'dashboard');
		}

		if (Config::getVar('security', 'force_login_ssl') && $request->getProtocol() != 'https') {
			// Force SSL connections for login
			$request->redirectSSL();
		}

		$sessionManager = SessionManager::getManager();
		$session = $sessionManager->getUserSession();

		$templateMgr = TemplateManager::getManager($request);

		// If the user wasn't expecting a login page, i.e. if they're new to the
		// site and want to submit a paper, it helps to explain why they need to
		// register.
		if($request->getUserVar('loginMessage'))
			$templateMgr->assign('loginMessage', $request->getUserVar('loginMessage'));

		$templateMgr->assign('username', $session->getSessionVar('username'));
		$templateMgr->assign('remember', $request->getUserVar('remember'));
		$templateMgr->assign('source', $request->getUserVar('source'));
		$templateMgr->assign('showRemember', Config::getVar('general', 'session_lifetime') > 0);

		// For force_login_ssl with base_url[...]: make sure SSL used for login form
		$loginUrl = $this->_getLoginUrl($request);
		if (Config::getVar('security', 'force_login_ssl')) {
			$loginUrl = String::regexp_replace('/^http:/', 'https:', $loginUrl);
		}
		$templateMgr->assign('loginUrl', $loginUrl);

		$templateMgr->display('frontend/pages/userLogin.tpl');
	}

	/**
	 * Handle login when implicitAuth is enabled.
	 * If the user came in on a non-ssl url - then redirect back to the ssl url
	 */
	function implicitAuthLogin($args, $request) {
		if ($request->getProtocol() != 'https')
			$request->redirectSSL();

		$wayf_url = Config::getVar('security', 'implicit_auth_wayf_url');

		if ($wayf_url == '')
			die('Error in implicit authentication. WAYF URL not set in config file.');

		$request->redirectUrl($wayf_url . '?target=' . urlencode($request->url('index', 'login', 'implicitAuthReturn')));
	}

	/**
	 * This is the function that Shibboleth redirects to - after the user has authenticated.
	 */
	function implicitAuthReturn($args, $request) {
		if (Validation::isLoggedIn()) {
			$request->redirect(null, 'dashboard');
		}

		// Login - set remember to false
		$user = Validation::login($request->getUserVar('username'), $request->getUserVar('password'), $reason, false);

		$request->redirect(null, 'dashboard');
	}

	/**
	 * After a login has completed, direct the user somewhere.
	 * (May be extended by subclasses.)
	 * @param $request PKPRequest
	 */
	function _redirectAfterLogin($request) {
		$request->redirectHome();
	}

	/**
	 * Validate a user's credentials and log the user in.
	 */
	function signIn($args, $request) {
		$this->setupTemplate($request);
		if (Validation::isLoggedIn()) {
			$request->redirect(null, 'dashboard');
		}

		if (Config::getVar('security', 'force_login_ssl') && $request->getProtocol() != 'https') {
			// Force SSL connections for login
			$request->redirectSSL();
		}

		$user = Validation::login($request->getUserVar('username'), $request->getUserVar('password'), $reason, $request->getUserVar('remember') == null ? false : true);
		if ($user !== false) {
			if ($user->getMustChangePassword()) {
				// User must change their password in order to log in
				Validation::logout();
				$request->redirect(null, null, 'changePassword', $user->getUsername());

			} else {
				$source = $request->getUserVar('source');
				$redirectNonSsl = Config::getVar('security', 'force_login_ssl') && !Config::getVar('security', 'force_ssl');
				if (isset($source) && !empty($source)) {
					$request->redirectUrl($source);
				} elseif ($redirectNonSsl) {
					$request->redirectNonSSL();
				} else {
					$this->_redirectAfterLogin($request);
				}
			}

		} else {
			$sessionManager = SessionManager::getManager();
			$session = $sessionManager->getUserSession();

			$templateMgr = TemplateManager::getManager($request);
			$templateMgr->assign('username', $request->getUserVar('username'));
			$templateMgr->assign('remember', $request->getUserVar('remember'));
			$templateMgr->assign('source', $request->getUserVar('source'));
			$templateMgr->assign('showRemember', Config::getVar('general', 'session_lifetime') > 0);
			$templateMgr->assign('error', $reason===null?'user.login.loginError':($reason===''?'user.login.accountDisabled':'user.login.accountDisabledWithReason'));
			$templateMgr->assign('reason', $reason);
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

		$source = $request->getUserVar('source');
		if (isset($source) && !empty($source)) {
			$request->redirectUrl($request->getProtocol() . '://' . $request->getServerHost() . $source, false);
		} else {
			$request->redirect(null, $request->getRequestedPage());
		}
	}

	/**
	 * Display form to reset a user's password.
	 */
	function lostPassword($args, $request) {
		$this->setupTemplate($request);
		$templateMgr = TemplateManager::getManager($request);
		$templateMgr->display('frontend/pages/userLostPassword.tpl');
	}

	/**
	 * Send a request to reset a user's password
	 */
	function requestResetPassword($args, $request) {
		$this->setupTemplate($request);
		$templateMgr = TemplateManager::getManager($request);

		$email = $request->getUserVar('email');
		$userDao = DAORegistry::getDAO('UserDAO');
		$user = $userDao->getUserByEmail($email);

		if ($user == null || ($hash = Validation::generatePasswordResetHash($user->getId())) == false) {
			$templateMgr->assign('error', 'user.login.lostPassword.invalidUser');
			$templateMgr->display('frontend/pages/userLostPassword.tpl');

		} else {
			$site = $request->getSite();

			// Send email confirming password reset
			import('lib.pkp.classes.mail.MailTemplate');
			$mail = new MailTemplate('PASSWORD_RESET_CONFIRM');
			$this->_setMailFrom($request, $mail, $site);
			$mail->assignParams(array(
				'url' => $request->url(null, 'login', 'resetPassword', $user->getUsername(), array('confirm' => $hash)),
				'siteTitle' => $site->getLocalizedTitle()
			));
			$mail->addRecipient($user->getEmail(), $user->getFullName());
			$mail->send();
			$templateMgr->assign('pageTitle',  'user.login.resetPassword');
			$templateMgr->assign('message', 'user.login.lostPassword.confirmationSent');
			$templateMgr->assign('backLink', $request->url(null, $request->getRequestedPage()));
			$templateMgr->assign('backLinkLabel',  'user.login');
			$templateMgr->display('frontend/pages/message.tpl');
		}
	}

	/**
	 * Reset a user's password
	 * @param $args array first param contains the username of the user whose password is to be reset
	 */
	function resetPassword($args, $request) {
		$this->setupTemplate($request);

		$username = isset($args[0]) ? $args[0] : null;
		$userDao = DAORegistry::getDAO('UserDAO');
		$confirmHash = $request->getUserVar('confirm');

		if ($username == null || ($user = $userDao->getByUsername($username)) == null) {
			$request->redirect(null, null, 'lostPassword');
		}

		$templateMgr = TemplateManager::getManager($request);

		if (!Validation::verifyPasswordResetHash($user->getId(), $confirmHash)) {
			$templateMgr->assign('errorMsg', 'user.login.lostPassword.invalidHash');
			$templateMgr->assign('backLink', $request->url(null, null, 'lostPassword'));
			$templateMgr->assign('backLinkLabel',  'user.login.resetPassword');
			$templateMgr->display('frontend/pages/error.tpl');

		} else {
			// Reset password
			$newPassword = Validation::generatePassword();

			if ($user->getAuthId()) {
				$authDao = DAORegistry::getDAO('AuthSourceDAO');
				$auth = $authDao->getPlugin($user->getAuthId());
			}

			if (isset($auth)) {
				$auth->doSetUserPassword($user->getUsername(), $newPassword);
				$user->setPassword(Validation::encryptCredentials($user->getId(), Validation::generatePassword())); // Used for PW reset hash only
			} else {
				$user->setPassword(Validation::encryptCredentials($user->getUsername(), $newPassword));
			}

			$user->setMustChangePassword(1);
			$userDao->updateObject($user);

			// Send email with new password
			$site = $request->getSite();
			import('lib.pkp.classes.mail.MailTemplate');
			$mail = new MailTemplate('PASSWORD_RESET');
			$this->_setMailFrom($request, $mail, $site);
			$mail->assignParams(array(
				'username' => $user->getUsername(),
				'password' => $newPassword, // DEPRECATED: This should only exist in old templates
				'siteTitle' => $site->getLocalizedTitle()
			));
			$mail->addRecipient($user->getEmail(), $user->getFullName());
			$mail->send();
			$templateMgr->assign('pageTitle',  'user.login.resetPassword');
			$templateMgr->assign('message', 'user.login.lostPassword.passwordSent');
			$templateMgr->assign('backLink', $request->url(null, $request->getRequestedPage()));
			$templateMgr->assign('backLinkLabel',  'user.login');
			$templateMgr->display('frontend/pages/message.tpl');
		}
	}

	/**
	 * Display form to change user's password.
	 * @param $args array first argument may contain user's username
	 */
	function changePassword($args, $request) {
		$this->setupTemplate($request);

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
		$this->setupTemplate($request);

		import('lib.pkp.classes.user.form.LoginChangePasswordForm');

		$passwordForm = new LoginChangePasswordForm($request->getSite());
		$passwordForm->readInputData();

		if ($passwordForm->validate()) {
			if ($passwordForm->execute()) {
				$user = Validation::login($passwordForm->getData('username'), $passwordForm->getData('password'), $reason);
			}
			$request->redirect(null, 'dashboard');

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
				$templateMgr->assign('pageTitle', 'manager.people');
				$templateMgr->assign('errorMsg', 'manager.people.noAdministrativeRights');
				$templateMgr->assign('backLink', $request->url(null, null, 'people', 'all'));
				$templateMgr->assign('backLinkLabel', 'manager.people.allUsers');
				return $templateMgr->display('frontend/pages/error.tpl');
			}

			$userDao = DAORegistry::getDAO('UserDAO');
			$newUser = $userDao->getById($userId);

			if (isset($newUser) && $session->getUserId() != $newUser->getId()) {
				$session->setSessionVar('signedInAs', $session->getUserId());
				$session->setSessionVar('userId', $userId);
				$session->setUserId($userId);
				$session->setSessionVar('username', $newUser->getUsername());
				$request->redirect(null, 'dashboard');
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

			$userDao = DAORegistry::getDAO('UserDAO');
			$oldUser = $userDao->getById($signedInAs);

			$session->unsetSessionVar('signedInAs');

			if (isset($oldUser)) {
				$session->setSessionVar('userId', $signedInAs);
				$session->setUserId($signedInAs);
				$session->setSessionVar('username', $oldUser->getUsername());
			}
		}

		$request->redirect(null, 'dashboard');
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
}

?>

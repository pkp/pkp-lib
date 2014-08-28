<?php

/**
 * @file pages/login/PKPLoginHandler.inc.php
 *
 * Copyright (c) 2013-2014 Simon Fraser University Library
 * Copyright (c) 2000-2014 John Willinsky
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
	 * Display user login form.
	 * Redirect to user index page if user is already validated.
	 */
	function index($args, &$request) {
		$this->validate();
		$this->setupTemplate($request);
		if (Validation::isLoggedIn()) {
			$request->redirect(null, 'user');
		}

		if (Config::getVar('security', 'force_login_ssl') && $request->getProtocol() != 'https') {
			// Force SSL connections for login
			$request->redirectSSL();
		}

		$sessionManager =& SessionManager::getManager();
		$session =& $sessionManager->getUserSession();

		$templateMgr =& TemplateManager::getManager();

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

		$templateMgr->display('user/login.tpl');
	}

	/**
	 * Handle login when implicitAuth is enabled.
	 * If the user came in on a non-ssl url - then redirect back to the ssl url
	 */
	function implicitAuthLogin($args, &$request) {
		if ($request->getProtocol() != 'https')
			$request->redirectSSL();

		$wayf_url = Config::getVar('security', 'implicit_auth_wayf_url');

		if ($wayf_url == '')
			die('Error in implicit authentication. WAYF URL not set in config file.');

		$url = $wayf_url . '?target=https://' . $request->getServerHost() . $request->getBasePath() . '/index.php/index/login/implicitAuthReturn';

		$request->redirectUrl($url);
	}

	/**
	 * This is the function that Shibboleth redirects to - after the user has authenticated.
	 */
	function implicitAuthReturn($args, &$request) {
		$this->validate();

		if (Validation::isLoggedIn()) {
			$request->redirect(null, 'user');
		}

		// Login - set remember to false
		$user = Validation::login($request->getUserVar('username'), $request->getUserVar('password'), $reason, false);

		$request->redirect(null, 'user');
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
	function signIn($args, &$request) {
		$this->validate();
		$this->setupTemplate($request);
		if (Validation::isLoggedIn()) {
			$request->redirect(null, 'user');
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
			$sessionManager =& SessionManager::getManager();
			$session =& $sessionManager->getUserSession();

			$templateMgr =& TemplateManager::getManager();
			$templateMgr->assign('username', $request->getUserVar('username'));
			$templateMgr->assign('remember', $request->getUserVar('remember'));
			$templateMgr->assign('source', $request->getUserVar('source'));
			$templateMgr->assign('showRemember', Config::getVar('general', 'session_lifetime') > 0);
			$templateMgr->assign('error', $reason===null?'user.login.loginError':($reason===''?'user.login.accountDisabled':'user.login.accountDisabledWithReason'));
			$templateMgr->assign('reason', $reason);
			$templateMgr->display('user/login.tpl');
		}
	}

	/**
	 * Log a user out.
	 */
	function signOut($args, &$request) {
		$this->validate();
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
	function lostPassword($args, &$request) {
		$this->validate();
		$this->setupTemplate($request);
		$templateMgr =& TemplateManager::getManager();
		$templateMgr->display('user/lostPassword.tpl');
	}

	/**
	 * Send a request to reset a user's password
	 */
	function requestResetPassword($args, &$request) {
		$this->validate();
		$this->setupTemplate($request);
		$templateMgr =& TemplateManager::getManager();

		$email = $request->getUserVar('email');
		$userDao =& DAORegistry::getDAO('UserDAO');
		$user =& $userDao->getUserByEmail($email);

		if ($user == null || ($hash = Validation::generatePasswordResetHash($user->getId())) == false) {
			$templateMgr->assign('error', 'user.login.lostPassword.invalidUser');
			$templateMgr->display('user/lostPassword.tpl');

		} else {
			$site =& $request->getSite();

			// Send email confirming password reset
			import('classes.mail.MailTemplate');
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
			$templateMgr->display('common/message.tpl');
		}
	}

	/**
	 * Reset a user's password
	 * @param $args array first param contains the username of the user whose password is to be reset
	 */
	function resetPassword($args, &$request) {
		$this->validate();
		$this->setupTemplate($request);
		$site =& $request->getSite();
		$oneStepReset = $site->getSetting('oneStepReset') ? true : false;

		$username = isset($args[0]) ? $args[0] : null;
		$userDao =& DAORegistry::getDAO('UserDAO');
		$confirmHash = $request->getUserVar('confirm');

		if ($username == null || ($user =& $userDao->getByUsername($username)) == null) {
			$request->redirect(null, null, 'lostPassword');
		}

		$templateMgr =& TemplateManager::getManager();

		$hash = Validation::generatePasswordResetHash($user->getId());
		if ($hash == false || $confirmHash != $hash) {
			$templateMgr->assign('errorMsg', 'user.login.lostPassword.invalidHash');
			$templateMgr->assign('backLink', $request->url(null, null, 'lostPassword'));
			$templateMgr->assign('backLinkLabel',  'user.login.resetPassword');
			$templateMgr->display('common/error.tpl');

		} else if (!$oneStepReset) {
			// Reset password
			$newPassword = Validation::generatePassword();

			if ($user->getAuthId()) {
				$authDao =& DAORegistry::getDAO('AuthSourceDAO');
				$auth =& $authDao->getPlugin($user->getAuthId());
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
			import('classes.mail.MailTemplate');
			$mail = new MailTemplate('PASSWORD_RESET');
			$this->_setMailFrom($request, $mail, $site);
			$mail->assignParams(array(
				'username' => $user->getUsername(),
				'password' => $newPassword,
				'siteTitle' => $site->getLocalizedTitle()
			));
			$mail->addRecipient($user->getEmail(), $user->getFullName());
			$mail->send();
			$templateMgr->assign('pageTitle',  'user.login.resetPassword');
			$templateMgr->assign('message', 'user.login.lostPassword.passwordSent');
			$templateMgr->assign('backLink', $request->url(null, $request->getRequestedPage()));
			$templateMgr->assign('backLinkLabel',  'user.login');
			$templateMgr->display('common/message.tpl');
		} else {
			import('classes.user.form.LoginChangePasswordForm');

			$passwordForm = new LoginChangePasswordForm($confirmHash);
			$passwordForm->initData();
			if (isset($args[0])) {
				$passwordForm->setData('username', $username);
			}
			$passwordForm->display();
		}
	}

	/**
	 * Display form to change user's password.
	 * @param $args array first argument may contain user's username
	 */
	function changePassword($args, &$request) {
		$this->validate();
		$this->setupTemplate($request);

		import('classes.user.form.LoginChangePasswordForm');

		$passwordForm = new LoginChangePasswordForm();
		$passwordForm->initData();
		if (isset($args[0])) {
			$passwordForm->setData('username', $args[0]);
		}
		$passwordForm->display();
	}

	/**
	 * Save user's new password.
	 */
	function savePassword($args, &$request) {
		$this->validate();
		$this->setupTemplate($request);
		$site = $request->getSite();
		$oneStepReset = $site->getSetting('oneStepReset') ? true : false;
		$confirmHash = null;
		if ($oneStepReset) {
			$confirmHash = $request->getUserVar('confirmHash');
		}
		import('classes.user.form.LoginChangePasswordForm');

		$passwordForm = new LoginChangePasswordForm($confirmHash);
		$passwordForm->readInputData();

		if ($passwordForm->validate()) {
			if ($passwordForm->execute()) {
				$user = Validation::login($passwordForm->getData('username'), $passwordForm->getData('password'), $reason);
			}
			$request->redirect(null, 'user');

		} else {
			$passwordForm->display();
		}
	}

	/**
	 * Helper function - set mail From
	 * can be overriden by child classes
	 * @param $request PKPRequest
	 * @param MailTemplate $mail
	 * @param $site Site
	 */
	function _setMailFrom($request, &$mail, &$site) {
		$mail->setFrom($site->getLocalizedContactEmail(), $site->getLocalizedContactName());
		return true;
	}
}

?>

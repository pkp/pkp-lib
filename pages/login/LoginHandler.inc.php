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

use APP\facades\Repo;
use APP\handler\Handler;
use APP\notification\NotificationManager;
use APP\template\TemplateManager;
use PKP\config\Config;
use PKP\core\PKPString;
use PKP\db\DAORegistry;
use PKP\mail\MailTemplate;
use PKP\notification\PKPNotification;
use PKP\security\authorization\RoleBasedHandlerOperationPolicy;
use PKP\security\Role;
use PKP\security\Validation;
use PKP\session\SessionManager;
use PKP\user\form\LoginChangePasswordForm;
use PKP\validation\FormValidatorReCaptcha;

class LoginHandler extends Handler
{
    /**
     * @copydoc PKPHandler::authorize()
     */
    public function authorize($request, &$args, $roleAssignments)
    {
        switch ($op = $request->getRequestedOp()) {
            case 'signInAsUser':
                $this->addPolicy(new RoleBasedHandlerOperationPolicy($request, [Role::ROLE_ID_MANAGER, Role::ROLE_ID_SITE_ADMIN], ['signInAsUser']));
                break;
        }
        return parent::authorize($request, $args, $roleAssignments);
    }

    /**
     * Display user login form.
     * Redirect to user index page if user is already validated.
     */
    public function index($args, $request)
    {
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
        $templateMgr->assign([
            'loginMessage' => $request->getUserVar('loginMessage'),
            'username' => $session->getSessionVar('username'),
            'remember' => $request->getUserVar('remember'),
            'source' => $request->getUserVar('source'),
            'showRemember' => Config::getVar('general', 'session_lifetime') > 0,
        ]);

        // For force_login_ssl with base_url[...]: make sure SSL used for login form
        $loginUrl = $request->url(null, 'login', 'signIn');
        if (Config::getVar('security', 'force_login_ssl')) {
            $loginUrl = PKPString::regexp_replace('/^http:/', 'https:', $loginUrl);
        }
        $templateMgr->assign('loginUrl', $loginUrl);

        $isCaptchaEnabled = Config::getVar('captcha', 'recaptcha') && Config::getVar('captcha', 'captcha_on_login');
        if ($isCaptchaEnabled) {
            $templateMgr->assign('recaptchaPublicKey', Config::getVar('captcha', 'recaptcha_public_key'));
        }

        $templateMgr->display('frontend/pages/userLogin.tpl');
    }

    /**
     * After a login has completed, direct the user somewhere.
     *
     * @param PKPRequest $request
     */
    public function _redirectAfterLogin($request)
    {
        $context = $this->getTargetContext($request);
        // If there's a context, send them to the dashboard after login.
        if ($context && $request->getUserVar('source') == '' && array_intersect(
            [Role::ROLE_ID_SITE_ADMIN, Role::ROLE_ID_MANAGER, Role::ROLE_ID_SUB_EDITOR, Role::ROLE_ID_AUTHOR, Role::ROLE_ID_REVIEWER, Role::ROLE_ID_ASSISTANT],
            (array) $this->getAuthorizedContextObject(ASSOC_TYPE_USER_ROLES)
        )) {
            return $request->redirect($context->getPath(), 'dashboard');
        }

        $request->redirectHome();
    }

    /**
     * Validate a user's credentials and log the user in.
     */
    public function signIn($args, $request)
    {
        $this->setupTemplate($request);
        $templateMgr = TemplateManager::getManager($request);

        if (Validation::isLoggedIn()) {
            $this->sendHome($request);
        }
        if (Config::getVar('security', 'force_login_ssl') && $request->getProtocol() != 'https') {
            // Force SSL connections for login
            $request->redirectSSL();
        }

        $error = null;
        $isCaptchaEnabled = Config::getVar('captcha', 'captcha_on_login') && Config::getVar('captcha', 'recaptcha');
        if ($isCaptchaEnabled) {
            $templateMgr->assign('recaptchaPublicKey', Config::getVar('captcha', 'recaptcha_public_key'));
            try {
                FormValidatorReCaptcha::validateResponse($request->getUserVar('g-recaptcha-response'), $request->getRemoteAddr(), $request->getServerHost());
            } catch (Exception $exception) {
                $error = 'common.captcha.error.missing-input-response';
            }
        }

        $reason = null;
        $user = $error ? false : Validation::login($request->getUserVar('username'), $request->getUserVar('password'), $reason, !!$request->getUserVar('remember'));
        if ($user) {
            if ($user->getMustChangePassword()) {
                // User must change their password in order to log in
                Validation::logout();
                $request->redirect(null, null, 'changePassword', $user->getUsername());
            }
            $source = $request->getUserVar('source');
            if (preg_match('#^/\w#', $source) === 1) {
                $request->redirectUrl($source);
            }
            $redirectNonSsl = Config::getVar('security', 'force_login_ssl') && !Config::getVar('security', 'force_ssl');
            if ($redirectNonSsl) {
                $request->redirectNonSSL();
            }
            $this->_redirectAfterLogin($request);
        }

        if ($reason) {
            $error = 'user.login.accountDisabledWithReason';
        } elseif ($reason !== null) {
            $error = 'user.login.accountDisabled';
        }
        $error ??= 'user.login.loginError';


        $templateMgr->assign([
            'username' => $request->getUserVar('username'),
            'remember' => $request->getUserVar('remember'),
            'source' => $request->getUserVar('source'),
            'showRemember' => Config::getVar('general', 'session_lifetime') > 0,
            'error' => $error,
            'reason' => $reason,
        ]);
        $templateMgr->display('frontend/pages/userLogin.tpl');
    }

    /**
     * Log a user out.
     */
    public function signOut($args, $request)
    {
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
    public function lostPassword($args, $request)
    {
        $this->setupTemplate($request);
        $templateMgr = TemplateManager::getManager($request);
        $templateMgr->display('frontend/pages/userLostPassword.tpl');
    }

    /**
     * Send a request to reset a user's password
     */
    public function requestResetPassword($args, $request)
    {
        $this->setupTemplate($request);
        $templateMgr = TemplateManager::getManager($request);

        $email = $request->getUserVar('email');
        $user = Repo::user()->getByEmail($email);

        if ($user !== null && ($hash = Validation::generatePasswordResetHash($user->getId())) !== false) {
            // Send email confirming password reset
            $mail = new MailTemplate('PASSWORD_RESET_CONFIRM');
            $site = $request->getSite();
            $this->_setMailFrom($request, $mail, $site);
            $mail->assignParams([
                'passwordResetUrl' => $request->url(null, 'login', 'resetPassword', $user->getUsername(), ['confirm' => $hash]),
                'siteTitle' => $site->getLocalizedTitle()
            ]);
            $mail->addRecipient($user->getEmail(), $user->getFullName());
            $mail->send();
        }

        $templateMgr->assign([
            'pageTitle' => 'user.login.resetPassword',
            'message' => 'user.login.lostPassword.confirmationSent',
            'backLink' => $request->url(null, $request->getRequestedPage()),
            'backLinkLabel' => 'user.login',
        ]);
        $templateMgr->display('frontend/pages/message.tpl');
    }

    /**
     * Reset a user's password
     *
     * @param array $args first param contains the username of the user whose password is to be reset
     */
    public function resetPassword($args, $request)
    {
        $this->setupTemplate($request);

        $username = $args[0] ?? null;
        $confirmHash = $request->getUserVar('confirm');

        if ($username == null || ($user = Repo::user()->getByUsername($username)) == null) {
            $request->redirect(null, null, 'lostPassword');
        }

        $templateMgr = TemplateManager::getManager($request);

        if (!Validation::verifyPasswordResetHash($user->getId(), $confirmHash)) {
            $templateMgr->assign([
                'errorMsg' => 'user.login.lostPassword.invalidHash',
                'backLink' => $request->url(null, null, 'lostPassword'),
                'backLinkLabel' => 'user.login.resetPassword',
            ]);
            $templateMgr->display('frontend/pages/error.tpl');
        } else {
            // Reset password
            $newPassword = Validation::generatePassword();

            if ($user->getAuthId()) {
                $authDao = DAORegistry::getDAO('AuthSourceDAO'); /** @var AuthSourceDAO $authDao */
                $auth = $authDao->getPlugin($user->getAuthId());
            }

            if (isset($auth)) {
                $auth->doSetUserPassword($user->getUsername(), $newPassword);
                $user->setPassword(Validation::encryptCredentials($user->getId(), Validation::generatePassword())); // Used for PW reset hash only
            } else {
                $user->setPassword(Validation::encryptCredentials($user->getUsername(), $newPassword));
            }

            $user->setMustChangePassword(1);
            Repo::user()->edit($user);

            // Send email with new password
            $site = $request->getSite();
            $mail = new MailTemplate('PASSWORD_RESET');
            $this->_setMailFrom($request, $mail, $site);
            $mail->assignParams([
                'recipientUsername' => $user->getUsername(),
                'password' => $newPassword,
                'siteTitle' => $site->getLocalizedTitle()
            ]);
            $mail->addRecipient($user->getEmail(), $user->getFullName());
            if (!$mail->send()) {
                $notificationMgr = new NotificationManager();
                $notificationMgr->createTrivialNotification($user->getId(), PKPNotification::NOTIFICATION_TYPE_ERROR, ['contents' => __('email.compose.error')]);
            }

            $templateMgr->assign([
                'pageTitle' => 'user.login.resetPassword',
                'message' => 'user.login.lostPassword.passwordSent',
                'backLink' => $request->url(null, $request->getRequestedPage()),
                'backLinkLabel' => 'user.login',
            ]);
            $templateMgr->display('frontend/pages/message.tpl');
        }
    }

    /**
     * Display form to change user's password.
     *
     * @param array $args first argument may contain user's username
     */
    public function changePassword($args, $request)
    {
        $this->_isBackendPage = true;
        $this->setupTemplate($request);
        $templateMgr = TemplateManager::getManager($request);
        $templateMgr->setupBackendPage();
        $templateMgr->assign([
            'pageTitle' => __('user.changePassword'),
        ]);

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
    public function savePassword($args, $request)
    {
        $this->_isBackendPage = true;
        $this->setupTemplate($request);

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
     *
     * @param array $args ($userId)
     * @param PKPRequest $request
     */
    public function signInAsUser($args, $request)
    {
        if (isset($args[0]) && !empty($args[0])) {
            $userId = (int)$args[0];
            $session = $request->getSession();
            if (!Validation::canAdminister($userId, $session->getUserId())) {
                $this->setupTemplate($request);
                // We don't have administrative rights
                // over this user. Display an error.
                $templateMgr = TemplateManager::getManager($request);
                $templateMgr->assign([
                    'pageTitle' => 'manager.people',
                    'errorMsg' => 'manager.people.noAdministrativeRights',
                    'backLink' => $request->url(null, null, 'people', 'all'),
                    'backLinkLabel' => 'manager.people.allUsers',
                ]);
                return $templateMgr->display('frontend/pages/error.tpl');
            }

            $newUser = Repo::user()->get($userId, true);

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
     *
     * @param array $args
     * @param PKPRequest $request
     */
    public function signOutAsUser($args, $request)
    {
        $session = $request->getSession();
        $signedInAs = $session->getSessionVar('signedInAs');

        if (isset($signedInAs) && !empty($signedInAs)) {
            $signedInAs = (int)$signedInAs;

            $oldUser = Repo::user()->get($signedInAs, true);

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
     *
     * @param PKPRequest $request
     */
    public function _redirectByURL($request)
    {
        $requestVars = $request->getUserVars();
        if (isset($requestVars['redirectUrl']) && !empty($requestVars['redirectUrl'])) {
            $request->redirectUrl($requestVars['redirectUrl']);
        } else {
            $this->sendHome($request);
        }
    }


    /**
     * Helper function - set mail From
     * can be overriden by child classes
     *
     * @param PKPRequest $request
     * @param MailTemplate $mail
     * @param Site $site
     */
    public function _setMailFrom($request, $mail, $site)
    {
        $mail->setReplyTo($site->getLocalizedContactEmail(), $site->getLocalizedContactName());
        return true;
    }

    /**
     * Send the user "home" (typically to the dashboard, but that may not
     * always be available).
     *
     * @param PKPRequest $request
     */
    protected function sendHome($request)
    {
        if ($request->getContext()) {
            $request->redirect(null, 'submissions');
        } else {
            $request->redirect(null, 'user');
        }
    }
}

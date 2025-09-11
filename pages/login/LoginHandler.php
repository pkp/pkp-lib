<?php

/**
 * @file pages/login/LoginHandler.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class LoginHandler
 *
 * @ingroup pages_login
 *
 * @brief Handle login/logout requests.
 */

namespace PKP\pages\login;

use APP\core\Application;
use APP\facades\Repo;
use APP\handler\Handler;
use APP\template\TemplateManager;
use Exception;
use Illuminate\Support\Facades\Mail;
use PKP\config\Config;
use PKP\context\Context;
use PKP\core\PKPApplication;
use PKP\core\PKPRequest;
use PKP\core\PKPString;
use PKP\form\validation\FormValidatorReCaptcha;
use PKP\mail\mailables\PasswordResetRequested;
use PKP\security\authorization\RoleBasedHandlerOperationPolicy;
use PKP\security\Role;
use PKP\security\Validation;
use PKP\session\SessionManager;
use PKP\site\Site;
use PKP\user\form\LoginChangePasswordForm;
use PKP\user\form\ResetPasswordForm;
use PKP\user\User;

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
            'username' => $session->getSessionVar('email') ?? $session->getSessionVar('username'),
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
            (array) $this->getAuthorizedContextObject(Application::ASSOC_TYPE_USER_ROLES)
        )) {
            return $request->redirect($context->getPath(), 'dashboard');
        }

        $request->getRouter()->redirectHome($request);
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

        $username = $request->getUserVar('username');
        $reason = null;
        $user = $error || !strlen($username ?? '')
            ? null
            : Validation::login($username, $request->getUserVar('password'), $reason, !!$request->getUserVar('remember'));
        if ($user) {
            if ($user->getMustChangePassword()) {
                // User must change their password in order to log in
                Validation::logout();
                $request->redirect(null, null, 'changePassword', $user->getUsername());
            }
            $source = str_replace('@', '', $request->getUserVar('source'));
            if (preg_match('#^/\w#', (string) $source) === 1) {
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
            'username' => $username,
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
    public function lostPassword($args, $request)
    {
        if (Validation::isLoggedIn()) {
            $this->sendHome($request);
        }

        $this->setupTemplate($request);
        $templateMgr = TemplateManager::getManager($request);

        $isCaptchaEnabled = Config::getVar('captcha', 'recaptcha') && Config::getVar('captcha', 'captcha_on_lost_password');
        if ($isCaptchaEnabled) {
            $templateMgr->assign('recaptchaPublicKey', Config::getVar('captcha', 'recaptcha_public_key'));
        }

        $templateMgr->display('frontend/pages/userLostPassword.tpl');
    }

    /**
     * Send a request to reset a user's password
     */
    public function requestResetPassword($args, $request)
    {
        $this->setupTemplate($request);
        $templateMgr = TemplateManager::getManager($request);

        $error = null;
        $isCaptchaEnabled = Config::getVar('captcha', 'recaptcha') && Config::getVar('captcha', 'captcha_on_lost_password');
        if ($isCaptchaEnabled) {
            $templateMgr->assign('recaptchaPublicKey', Config::getVar('captcha', 'recaptcha_public_key'));
            try {
                FormValidatorReCaptcha::validateResponse($request->getUserVar('g-recaptcha-response'), $request->getRemoteAddr(), $request->getServerHost());
            } catch (Exception $exception) {
                $error = 'common.captcha.error.missing-input-response';
                $templateMgr->assign([
                    'error' => $error,
                    'email' => $request->getUserVar('email')
                ]);
                $templateMgr->display('frontend/pages/userLostPassword.tpl');
                return;
            }
        }

        $email = (string) $request->getUserVar('email');
        $user = $email ? Repo::user()->getByEmail($email, true) : null;
        if ($user !== null) {
            if ($user->getDisabled()) {
                $templateMgr
                    ->assign([
                        'error' => 'user.login.lostPassword.confirmationSentFailedWithReason',
                        'reason' => empty($reason = $user->getDisabledReason() ?? '')
                            ? __('user.login.accountDisabled')
                            : __('user.login.accountDisabledWithReason', ['reason' => htmlspecialchars($reason)])
                    ])
                    ->display('frontend/pages/userLostPassword.tpl');

                return;
            }

            // Send email confirming password reset
            $site = $request->getSite(); /** @var Site $site */
            $context = $request->getContext(); /** @var Context $context */
            $template = Repo::emailTemplate()->getByKey(
                $context ? $context->getId() : PKPApplication::CONTEXT_SITE,
                PasswordResetRequested::getEmailTemplateKey()
            );
            $mailable = (new PasswordResetRequested($site))
                ->recipients($user)
                ->from($site->getLocalizedContactEmail(), $site->getLocalizedContactName())
                ->body($template->getLocalizedData('body'))
                ->subject($template->getLocalizedData('subject'));
            Mail::send($mailable);
        }

        $templateMgr->assign([
            'pageTitle' => 'user.login.resetPassword',
            'message' => 'user.login.lostPassword.confirmationSent',
            'backLink' => $request->url(null, $request->getRequestedPage(), null, null),
            'backLinkLabel' => 'user.login',
        ])->display('frontend/pages/message.tpl');
    }

    /**
     * Present the password reset form to reset user's password
     *
     * @param array $args first param contains the username of the user whose password is to be reset
     */
    public function resetPassword($args, $request)
    {
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

        $username = $args[0] ?? null;
        $confirmHash = $request->getUserVar('confirm');

        if ($username == null || ($user = Repo::user()->getByUsername($username, true)) == null) {
            return $request->redirect(null, null, 'lostPassword');
        }

        if ($user->getDisabled()) {
            $templateMgr
                ->assign([
                    'backLink' => $request->url(null, $request->getRequestedPage()),
                    'backLinkLabel' => 'user.login',
                    'messageTranslated' => __('user.login.lostPassword.confirmationSentFailedWithReason', [
                        'reason' => empty($reason = $user->getDisabledReason() ?? '')
                            ? __('user.login.accountDisabled')
                            : __('user.login.accountDisabledWithReason', ['reason' => htmlspecialchars($reason)])
                    ]),
                ])
                ->display('frontend/pages/message.tpl');

            return;
        }

        $passwordResetForm = new ResetPasswordForm($user, $request->getSite(), $confirmHash);
        $passwordResetForm->initData();

        $passwordResetForm->validatePasswordResetHash()
            ? $passwordResetForm->display($request)
            : $passwordResetForm->displayInvalidHashErrorMessage($request);
    }

    /**
     * Reset a user's password
     *
     * @param array $args first param contains the username of the user whose password is to be reset
     */
    public function updateResetPassword($args, $request)
    {
        $this->_isBackendPage = true;
        $this->setupTemplate($request);
        $templateMgr = TemplateManager::getManager($request);

        $username = $request->getUserVar('username');
        $confirmHash = $request->getUserVar('hash');

        if ($username == null || ($user = Repo::user()->getByUsername($username, true)) == null) {
            return $request->redirect(null, null, 'lostPassword');
        }

        $passwordResetForm = new ResetPasswordForm($user, $request->getSite(), $confirmHash);
        $passwordResetForm->readInputData();

        if (!$passwordResetForm->validatePasswordResetHash()) {
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

                $sessionManager = SessionManager::getManager();
                $sessionManager->invalidateSessions($user->getId(), $sessionManager->getUserSession()->getId());
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
            if (Validation::getAdministrationLevel($userId, $session->getUserId()) !== Validation::ADMINISTRATION_FULL) {
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

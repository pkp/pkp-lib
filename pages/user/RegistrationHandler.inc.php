<?php

/**
 * @file pages/user/RegistrationHandler.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class RegistrationHandler
 * @ingroup pages_user
 *
 * @brief Handle requests for user registration.
 */


import('pages.user.UserHandler');

use APP\facades\Repo;
use APP\template\TemplateManager;
use PKP\notification\PKPNotification;
use PKP\notification\PKPNotificationManager;
use PKP\observers\events\UserRegisteredContext;
use PKP\observers\events\UserRegisteredSite;
use PKP\security\AccessKeyManager;
use PKP\user\form\RegistrationForm;

class RegistrationHandler extends UserHandler
{
    /**
     * Display registration form for new users, validate and execute that form,
     * or display a registration success page if the user is logged in.
     *
     * @param array $args
     * @param PKPRequest $request
     */
    public function register($args, $request)
    {
        if (Config::getVar('security', 'force_login_ssl') && $request->getProtocol() != 'https') {
            // Force SSL connections for registration
            $request->redirectSSL();
        }

        // If the user is logged in, show them the registration success page
        if (Validation::isLoggedIn()) {
            $this->setupTemplate($request);
            $templateMgr = TemplateManager::getManager($request);
            $templateMgr->assign('pageTitle', 'user.login.registrationComplete');
            return $templateMgr->display('frontend/pages/userRegisterComplete.tpl');
        }

        $this->validate(null, $request);
        $this->setupTemplate($request);

        $regForm = new RegistrationForm($request->getSite());

        // Initial GET request to register page
        if (!$request->isPost()) {
            $regForm->initData();
            return $regForm->display($request);
        }

        // Form submitted
        $regForm->readInputData();
        if (!$regForm->validate()) {
            return $regForm->display($request);
        }

        $userId = $regForm->execute();

        $user = Repo::user()->get($userId);

        try {
            if ($context = $request->getContext()) {
                event(new UserRegisteredContext($user, $context));
            } else {
                event(new UserRegisteredSite($user, $request->getSite()));
            }
        } catch(Swift_TransportException $e) {
            $notificationMgr = new PKPNotificationManager();
            $notificationMgr->createTrivialNotification(
                $userId,
                PKPNotification::NOTIFICATION_TYPE_ERROR,
                ['contents' => __('email.compose.error')]
            );
            trigger_error($e->getMessage(), E_USER_WARNING);
        }

        // Inform the user of the email validation process. This must be run
        // before the disabled account check to ensure new users don't see the
        // disabled account message.
        if (Config::getVar('email', 'require_validation')) {
            $this->setupTemplate($request);
            $templateMgr = TemplateManager::getManager($request);
            $templateMgr->assign([
                'requireValidation' => true,
                'pageTitle' => 'user.login.registrationPendingValidation',
                'messageTranslated' => __('user.login.accountNotValidated', ['email' => $regForm->getData('email')]),
            ]);
            return $templateMgr->display('frontend/pages/message.tpl');
        }

        $reason = null;
        Validation::login($regForm->getData('username'), $regForm->getData('password'), $reason);

        if ($reason !== null) {
            $this->setupTemplate($request);
            $templateMgr = TemplateManager::getManager($request);
            $templateMgr->assign([
                'pageTitle' => 'user.login',
                'errorMsg' => $reason == '' ? 'user.login.accountDisabled' : 'user.login.accountDisabledWithReason',
                'errorParams' => ['reason' => $reason],
                'backLink' => $request->url(null, 'login'),
                'backLinkLabel' => 'user.login',
            ]);
            return $templateMgr->display('frontend/pages/error.tpl');
        }

        $source = $request->getUserVar('source');
        if (preg_match('#^/\w#', $source) === 1) {
            return $request->redirectUrl($source);
        } else {
            // Make a new request to update cookie details after login
            $request->redirect(null, 'user', 'register');
        }
    }

    /**
     * Re-route request to the register method.
     * Backwards-compatible with third-party themes that submit the registration
     * form to the registerUser method.
     *
     * @see RegistrationHandler::register
     */
    public function registerUser($args, $request)
    {
        $this->register($args, $request);
    }

    /**
     * Check credentials and activate a new user
     *
     * @param array $args
     * @param PKPRequest $request
     */
    public function activateUser($args, $request)
    {
        $username = array_shift($args);
        $accessKeyCode = array_shift($args);
        $user = Repo::user()->getByUsername($username);
        if (!$user) {
            $request->redirect(null, 'login');
        }

        // Checks user and token
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
            Repo::user()->edit($user);

            $templateMgr = TemplateManager::getManager($request);
            $templateMgr->assign('message', 'user.login.activated');
            return $templateMgr->display('frontend/pages/message.tpl');
        }
        $request->redirect(null, 'login');
    }

    /**
     * @copydoc PKPHandler::validate
     *
     * @param null|mixed $requiredContexts
     * @param null|mixed $request
     */
    public function validate($requiredContexts = null, $request = null)
    {
        $context = $request->getContext();
        $disableUserReg = false;
        if (!$context) {
            $contextDao = Application::getContextDAO();
            $contexts = $contextDao->getAll(true)->toArray();
            $contextsForRegistration = [];
            foreach ($contexts as $context) {
                if (!$context->getData('disableUserReg')) {
                    $contextsForRegistration[] = $context;
                }
            }
            if (empty($contextsForRegistration)) {
                $disableUserReg = true;
            }
        } elseif ($context->getData('disableUserReg')) {
            $disableUserReg = true;
        }

        if ($disableUserReg) {
            $this->setupTemplate($request);
            $templateMgr = TemplateManager::getManager($request);
            $templateMgr->assign([
                'pageTitle' => 'user.register',
                'errorMsg' => 'user.register.registrationDisabled',
                'backLink' => $request->url(null, 'login'),
                'backLinkLabel' => 'user.login',
            ]);
            $templateMgr->display('frontend/pages/error.tpl');
            exit;
        }
    }
}

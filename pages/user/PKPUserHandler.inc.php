<?php

/**
 * @file pages/user/PKPUserHandler.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PKPUserHandler
 * @ingroup pages_user
 *
 * @brief Handle requests for user functions.
 */

use APP\handler\Handler;

use APP\template\TemplateManager;

use PKP\core\JSONMessage;
use PKP\facades\Locale;
use PKP\user\InterestManager;

class PKPUserHandler extends Handler
{
    /**
     * Index page; redirect to profile
     */
    public function index($args, $request)
    {
        $request->redirect(null, null, 'profile');
    }

    /**
     * Change the locale for the current user.
     *
     * @param array $args first parameter is the new locale
     */
    public function setLocale($args, $request)
    {
        $setLocale = array_shift($args);

        $site = $request->getSite();
        $context = $request->getContext();
        if ($context != null) {
            $contextSupportedLocales = (array) $context->getSupportedLocales();
        }

        if (Locale::isLocaleValid($setLocale) && (!isset($contextSupportedLocales) || in_array($setLocale, $contextSupportedLocales)) && in_array($setLocale, $site->getSupportedLocales())) {
            $session = $request->getSession();
            $session->setSessionVar('currentLocale', $setLocale);
        }

        $source = $request->getUserVar('source');
        if (preg_match('#^/\w#', $source) === 1) {
            $request->redirectUrl($source);
        }

        if (isset($_SERVER['HTTP_REFERER'])) {
            $request->redirectUrl($_SERVER['HTTP_REFERER']);
        }

        $request->redirect(null, 'index');
    }

    /**
     * Get interests for reviewer interests autocomplete.
     *
     * @param array $args
     * @param PKPRequest $request
     *
     * @return JSONMessage JSON object
     */
    public function getInterests($args, $request)
    {
        return new JSONMessage(
            true,
            (new InterestManager())->getAllInterests($request->getUserVar('term'))
        );
    }

    /**
     * Display an authorization denied message.
     *
     * @param array $args
     * @param Request $request
     */
    public function authorizationDenied($args, $request)
    {
        if (!Validation::isLoggedIn()) {
            Validation::redirectLogin();
        }

        // Get message with sanity check (for XSS or phishing)
        $authorizationMessage = $request->getUserVar('message');
        if (!preg_match('/^[a-zA-Z0-9.]+$/', $authorizationMessage)) {
            fatalError('Invalid locale key for auth message.');
        }

        $this->setupTemplate($request);
        AppLocale::requireComponents(LOCALE_COMPONENT_PKP_USER, LOCALE_COMPONENT_PKP_REVIEWER);
        $templateMgr = TemplateManager::getManager($request);
        $templateMgr->assign('message', $authorizationMessage);
        return $templateMgr->display('frontend/pages/message.tpl');
    }
}

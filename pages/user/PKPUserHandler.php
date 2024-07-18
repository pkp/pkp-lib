<?php

/**
 * @file pages/user/PKPUserHandler.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PKPUserHandler
 *
 * @ingroup pages_user
 *
 * @brief Handle requests for user functions.
 */

namespace PKP\pages\user;

use APP\core\Request;
use APP\handler\Handler;
use APP\template\TemplateManager;
use PKP\core\JSONMessage;
use PKP\core\PKPRequest;
use PKP\security\Validation;
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
            throw new \Exception('Invalid locale key for auth message.');
        }

        $this->setupTemplate($request);
        $templateMgr = TemplateManager::getManager($request);
        $templateMgr->assign('message', $authorizationMessage);
        return $templateMgr->display('frontend/pages/message.tpl');
    }
}

<?php

/**
 * @file classes/middleware/PKPStartSession.php
 *
 * Copyright (c) 2024 Simon Fraser University
 * Copyright (c) 2024 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PKPStartSession
 *
 * @brief Handle session and cookie data before user authentication
 */

namespace PKP\middleware;

use Illuminate\Http\Request;

class PKPStartSession extends \Illuminate\Session\Middleware\StartSession
{
    /**
     * @copydoc \Illuminate\Session\Middleware\StartSession::startSession(Request $request, $session)
     */
    protected function startSession(Request $request, $session)
    {
        return tap($session, function ($session) use ($request) {
            $session->setRequestOnHandler($request);
            
            $session->start();
            
            app()->get('auth.driver')->setSession($session);
        });
    }
}

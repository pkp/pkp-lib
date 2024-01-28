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
 * @ingroup middleware
 *
 * @brief 
 */

namespace PKP\middleware;

use Illuminate\Http\Request;
use Illuminate\Contracts\Session\Session;

class PKPStartSession extends \Illuminate\Session\Middleware\StartSession
{
    /**
     * Start the session for the given request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Illuminate\Contracts\Session\Session  $session
     * @return \Illuminate\Contracts\Session\Session
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

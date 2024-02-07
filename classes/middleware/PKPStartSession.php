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
use Illuminate\Support\Str;

class PKPStartSession extends \Illuminate\Session\Middleware\StartSession
{
    /**
     * NOTE :   The following method override from \Illuminate\Session\Middleware\StartSession
     *          to handle the case when user migrating from old session handler to laravel
     *          session/cookie management service but browser store and pass old session id
     *          along with laravel specific session id. This cause to target and get the older
     *          session id which cause it not found in session storage(for now only DB) and cause
     *          to regenrate session information in loop but still pass along with older
     *          session id as cookie data which result in unable to login and need to clear users
     *          browser cookie.
     * 
     *          For example, after the migration of to new session management, we can get cookie
     *          in the following format as : 
     *          OJSSID=bth7drsgkc9cfa1fpua1evvjsc; OJSSID=cGrxCGAZpgDsieFwA8aknEtlY3J7I7FGRcDNHH01
     *          where first OJSSID represent older session id and second one represent new one based
     *          on new implementation.   
     * 
     * Get the session implementation from the manager.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Contracts\Session\Session
     */
    public function getSession(Request $request)
    {
        return tap($this->manager->driver(), function ($session) use ($request) {
            $val = null;

            Str::of($request->server->get('HTTP_COOKIE'))
                ->explode("; ")
                ->each(function ($cookie) use (&$val, $session) {
                    $data = Str::of($cookie)->explode("=");
                    if ($data->first() === "OJSSID" && $session->isValidId($data->last())) {
                        $val = $data->last();
                    }
                });

            $session->setId($val ?? $request->cookies->get($session->getName()));
        });
    }

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

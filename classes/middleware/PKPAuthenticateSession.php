<?php

/**
 * @file classes/middleware/PKPAuthenticateSession.php
 *
 * Copyright (c) 2024 Simon Fraser University
 * Copyright (c) 2024 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PKPAuthenticateSession
 *
 * @brief Monitor session data to control authentication flow
 */

namespace PKP\middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use PKP\config\Config;
use PKP\security\AuditLog;
use PKP\security\Validation;
use Psr\Log\LogLevel;

class PKPAuthenticateSession extends \Illuminate\Session\Middleware\AuthenticateSession
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     */
    public function handle($request, Closure $next)
    {
        $response = parent::handle($request, $next);

        if ($request->hasSession()
            && $request->user()
            && Config::getVar('security', 'session_check_ip')
            && ($loginIp = $request->session()->get('login_ip'))
            && $loginIp !== $request->ip()
        ) {
            $this->logout($request);
        }

        return $response;
    }

    /**
     * Log the user out of the application.
     *
     * @param  \Illuminate\Http\Request  $request
     */
    protected function logout($request)
    {
        // session terminated because the client IP changed mid-session. Capture the
        // actor + old/new IP before Auth::logout()/invalidate() tears the session down.
        AuditLog::log('auth.logout.forced_ip_change', LogLevel::WARNING, [
            'loggedInUserId' => $request->user()?->getId(),
            'oldIp' => $request->session()->get('login_ip'),
            'newIp' => $request->ip(),
        ]);

        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return $this->redirectTo($request);
    }

    /**
     * Get the path the user should be redirected to when their session is not authenticated.
     *
     * @return string|null
     */
    protected function redirectTo(Request $request)
    {
        Validation::redirectLogin();
    }
}

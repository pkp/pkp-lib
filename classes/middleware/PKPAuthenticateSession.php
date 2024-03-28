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

use PKP\security\Validation;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;

class PKPAuthenticateSession extends \Illuminate\Session\Middleware\AuthenticateSession
{
    /**
     * Log the user out of the application.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return void
     */
    protected function logout($request)
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return $this->redirectTo($request);
    }

    /**
     * Get the path the user should be redirected to when their session is not authenticated.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return string|null
     */
    protected function redirectTo(Request $request)
    {
        Validation::redirectLogin();
    }
}

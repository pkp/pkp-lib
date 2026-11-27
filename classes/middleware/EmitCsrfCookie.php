<?php

/**
 * @file classes/middleware/EmitCsrfCookie.php
 *
 * Copyright (c) 2026 Simon Fraser University
 * Copyright (c) 2026 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class EmitCsrfCookie
 *
 * @brief Emit the XSRF-TOKEN cookie reflecting the current session's CSRF token,
 *  giving JS clients a live, cross-tab-synced source of truth for the token.
 */

namespace PKP\middleware;

use APP\core\Application;
use Closure;
use Illuminate\Http\Request;
use PKP\core\PKPSessionGuard;

class EmitCsrfCookie
{
    /**
     * Emit the XSRF-TOKEN cookie on the outgoing response.
     *
     * Pure response-side enhancer — no gating on auth or API-token presence:
     *
     * - No isApiRequest() bypass: emitting the cookie on Bearer-auth responses
     *   is harmless. Browsers with a session cookie benefit; non-browser
     *   clients ignore Set-Cookie.
     * - No HasUser dependency: the session's _token exists regardless of auth
     *   state (Session::start() generates it). Token rotation can happen on
     *   unauthenticated routes — the login POST itself is the canonical case.
     */
    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);

        if (PKPSessionGuard::isSessionDisable()) {                                                                                                                  
            return $response;                                                                                                                                       
        }

        $session = Application::get()->getRequest()->getSession();
        if (!$session) {
            return $response;
        }

        $config = app()->get('config')->get('session');

        $response->headers->setCookie(PKPSessionGuard::buildXsrfTokenCookie($session->token(), $config));

        return $response;
    }
}

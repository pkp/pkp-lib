<?php

/**
 * @file classes/middleware/RedirectGuestToLogin.php
 *
 * Copyright (c) 2026 Simon Fraser University
 * Copyright (c) 2026 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class RedirectGuestToLogin
 *
 * @brief   Routing middleware to redirect an unauthenticated browser request to the
 *          login page, while letting API-token requests fall through to the auth layer. it
 *          is(and should be) intended to use right before the `has.user` middleware on routes
 *          which would provide same login-redirect experience as the reader front end instead
 *          of a raw JSON response.
 */

namespace PKP\middleware;

use Closure;
use Illuminate\Http\Request;
use PKP\security\Validation;
use PKP\user\User;

class RedirectGuestToLogin
{
    /**
     * Redirect an anonymous browser request to the login page.
     */
    public function handle(Request $request, Closure $next)
    {
        // Authenticated (via session or API key), so continue
        if ($request->user() instanceof User) {
            return $next($request);
        }

        // Anonymous request, check as following cases
        //  - request has a bearer token
        //  - request has a apiToken as query param
        //  - request has header `Accept` as `application/json`
        // determined if a browser redirect or json response based on above rules
        $isApiClient = $request->bearerToken() !== null
            || $request->query('apiToken') !== null
            || $request->expectsJson();

        // Browser navigation (no token or asking for JSON), redirect to the login page
        if (!$isApiClient) {
            Validation::redirectLogin();
        }

        // Looks like an API client, let it continue. It's not this middleware's job to
        // validate the API token.
        // (with the token, if any, already decoded by `DecodeApiTokenWithValidation` from
        // the global middleware stack), which returns the JSON unauthorized response.
        return $next($request);
    }
}

<?php

/**
 * @file classes/middleware/ValidateCsrfToken.php
 *
 * Copyright (c) 2023 Simon Fraser University
 * Copyright (c) 2023 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class ValidateCsrfToken
 *
 * @ingroup middleware
 *
 * @brief Routing middleware to verify CSRF token
 */

namespace PKP\middleware;

use APP\core\Application;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class ValidateCsrfToken
{
    /**
     * Determine and validate CSRF token
     *
     */
    public function handle(Request $request, Closure $next)
    {
        if($this->isApiRequest($request)) {
            return $next($request);
        }

        if (!$this->isCsrfRequiredForRequest($request)) {
            return $next($request);
        }

        if (!$this->isCsrfValid($request)) {
            return response()->json([
                'error' => __('form.csrfInvalid')
            ], Response::HTTP_FORBIDDEN);
        }

        return $next($request);
    }

    /**
     * Check if this is a API request
     */
    protected function isApiRequest(Request $request): bool
    {
        return $request->query('apiToken', null) ? true : false;
    }

    /**
     * Check request require CSRF token
     */
    protected function isCsrfRequiredForRequest(Request $request): bool
    {
        return in_array($request->server('REQUEST_METHOD'), ['PUT', 'PATCH', 'POST', 'DELETE']);
    }

    /**
     * Validate the CSRF token
     */
    protected function isCsrfValid(Request $request): bool
    {
        $requestCsrfToken = $request->server('HTTP_X_CSRF_TOKEN', null);

        if($requestCsrfToken === null) {
            return false;
        }

        $pkpSession = Application::get()->getRequest()->getSession();

        return $pkpSession->token() === $requestCsrfToken;
    }
}

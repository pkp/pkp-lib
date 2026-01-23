<?php

/**
 * @file classes/middleware/SiteAdminAuthorizer.php
 *
 * Copyright (c) 2024 Simon Fraser University
 * Copyright (c) 2024 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class SiteAdminAuthorizer
 *
 * @brief Middleware to authorize site administrator access for Laravel web routes
 */

namespace PKP\middleware;

use APP\core\Application;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use PKP\core\PKPApplication;
use PKP\security\Role;

class SiteAdminAuthorizer
{
    /**
     * Handle an incoming request.
     *
     * Ensures the user is logged in and has site administrator role.
     */
    public function handle(Request $request, Closure $next): mixed
    {
        $pkpRequest = Application::get()->getRequest();
        $user = $pkpRequest->getUser();

        // Check if user is logged in
        if (!$user) {
            return $this->unauthorized($request, 'user.authorization.loginRequired', Response::HTTP_UNAUTHORIZED);
        }

        // Check if user has site admin role
        $isSiteAdmin = $user->hasRole(
            [Role::ROLE_ID_SITE_ADMIN],
            PKPApplication::SITE_CONTEXT_ID
        );

        if (!$isSiteAdmin) {
            return $this->unauthorized($request, 'user.authorization.siteAdminRequired', Response::HTTP_FORBIDDEN);
        }

        return $next($request);
    }

    /**
     * Return an unauthorized response
     */
    protected function unauthorized(Request $request, string $messageKey, int $statusCode): Response
    {
        $message = __($messageKey);

        // For JSON/API requests, return JSON response
        if ($request->expectsJson() || $request->isJson()) {
            return response()->json([
                'error' => $messageKey,
                'errorMessage' => $message,
            ], $statusCode);
        }

        // For web requests, return HTML response
        return response($message, $statusCode);
    }
}

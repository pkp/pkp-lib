<?php

/**
 * @file classes/middleware/SiteAdminAuthorizer.php
 *
 * Copyright (c) 2026 Simon Fraser University
 * Copyright (c) 2026 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class SiteAdminAuthorizer
 *
 * @brief Middleware to authorize site administrator access for Laravel web routes, and to require a
 *        valid CSRF token on every state-changing request to them
 *
 * These routes are dispatched outside the API router, so none of the API middleware stack runs for
 * them. PKP\middleware\ValidateCsrfToken cannot supply the check: it only acts on routes that attach
 * HasUser, which these do not, and it exempts any request carrying an apiToken query parameter or an
 * Authorization header. The token is therefore compared here, against the same session token the
 * page emits in its csrf-token meta tag.
 */

namespace PKP\middleware;

use APP\core\Application;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use PKP\core\PKPApplication;
use PKP\security\Role;

class SiteAdminAuthorizer
{
    /** HTTP methods that do not change state and so need no CSRF token */
    protected const SAFE_METHODS = ['GET', 'HEAD', 'OPTIONS'];

    /**
     * Handle an incoming request.
     *
     * Ensures the user is logged in, has site administrator role, and carries the session's CSRF
     * token when the request changes state.
     */
    public function handle(Request $request, Closure $next): mixed
    {
        $pkpRequest = Application::get()->getRequest();
        $user = $pkpRequest->getUser();

        // Check if user is logged in
        if (!$user) {
            return $this->unauthorized(
                $request,
                'user.authorization.loginRequired',
                Response::HTTP_UNAUTHORIZED
            );
        }

        // Check if user has site admin role
        $isSiteAdmin = $user->hasRole([Role::ROLE_ID_SITE_ADMIN], PKPApplication::SITE_CONTEXT_ID);

        if (!$isSiteAdmin) {
            return $this->unauthorized(
                $request,
                'user.authorization.siteAdminRequired',
                Response::HTTP_FORBIDDEN
            );
        }

        // Deleting logs and clearing caches must carry the token the viewer's page exposes to its
        // scripts in the csrf-token meta tag
        if (!in_array($request->getMethod(), static::SAFE_METHODS, true)
            && !hash_equals((string) $pkpRequest->getSession()->token(), (string) $request->header('X-CSRF-TOKEN'))
        ) {
            return $this->unauthorized(
                $request,
                'form.csrfInvalid',
                Response::HTTP_FORBIDDEN
            );
        }

        return $next($request);
    }

    /**
     * Return a response refusing the request
     */
    protected function unauthorized(Request $request, string $messageKey, int $statusCode): Response|JsonResponse
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

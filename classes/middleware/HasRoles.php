<?php

/**
 * @file classes/middleware/HasRoles.php
 *
 * Copyright (c) 2023 Simon Fraser University
 * Copyright (c) 2023 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class HasRoles
 *
 * @ingroup middleware
 *
 * @brief Routing middleware to apply user role validation/authorization
 */

namespace PKP\middleware;

use APP\core\Application;
use Closure;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Str;
use PKP\security\Role;
use PKP\db\DAORegistry;

class HasRoles
{
    /**
     * Following constant define how the user role authorization and validation will be determined
     *
     * ROLES_MATCH_STRICT   It define that ALL applied roles to the routes must match be present
     *                      and associated with the requesting user
     * ROLES_MATCH_LOOSE    It define that ANY of the applied role to the routes must be present
     *                      and associated with the requesting user
     */
    public const ROLES_MATCH_STRICT = 1;
    public const ROLES_MATCH_LOOSE = 2;

    /**
     * Validate the applied user role to the route with requesting user role
     *
     * @param string                    $matchableRoles         The passed |(pipe) separated roles e.g. 1|2|3
     * @param int                       $rolesMatchingCriteria  Should the passed roles match all(strict) or any(loose) based on const HasRoles::ROLES_MATCH_*
     *
     */
    public function handle(Request $request, Closure $next, string $matchableRoles, int $rolesMatchingCriteria = HasRoles::ROLES_MATCH_LOOSE)
    {
        $user = $request->user(); /** @var \PKP\user\User $user */

        if (!$user) {
            throw new Exception("No user found in `HasRole` middleware. Ensure that `HasUser` middleware is applied before `HasRole`.");
        }

        // Get all user roles.
        $context = $request->attributes->get('context'); /** @var ?\PKP\context\Context $context */

        $matchableRoles = Str::of($matchableRoles)
            ->explode('|')
            ->map(fn($role) => (int)$role);

        $matcher = fn(int $roleId) => $user->hasRole($roleId, $roleId === Role::ROLE_ID_SITE_ADMIN ? Application::SITE_CONTEXT_ID : $context->getId());
        $isAuthorized = match ($rolesMatchingCriteria) {
            static::ROLES_MATCH_LOOSE => $matchableRoles->some($matcher),
            static::ROLES_MATCH_STRICT => $matchableRoles->every($matcher)
        };

        if (!$isAuthorized) {
            return response()->json([
                'error' => __('api.403.unauthorized'),
            ], Response::HTTP_UNAUTHORIZED);
        }

        return $next($request);
    }
}

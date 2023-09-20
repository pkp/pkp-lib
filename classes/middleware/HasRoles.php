<?php

/**
 * @file classes/middleware/
 *
 * Copyright (c) 2014-2023 Simon Fraser University
 * Copyright (c) 2000-2023 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class 
 *
 * @ingroup middleware
 *
 * @brief 
 *
 */

namespace PKP\middleware;

use APP\core\Application;
use Closure;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class HasRoles
{
    public const ROLES_MATCH_STRICT = 1;
    public const ROLES_MATCH_LOOSE = 2;

    /**
     * 
     * 
     * @param \Illuminate\Http\Request  $request
     * @param Closure                   $next
     * @param string                    $matchableRoles         The passed |(pipe) separated roles e.g. 1|2|3
     * @param int                       $rolesMatchingCriteria  Should the passed roles match all(strict) or any(loose) based on const HasRoles::ROLES_MATCH_*
     * 
     * @return mixed
     */
    public function handle(Request $request, Closure $next, string $matchableRoles, int $rolesMatchingCriteria = HasRoles::ROLES_MATCH_LOOSE)
    {
        $user = $request->user(); /** @var \PKP\user\User $user */
        $context = $request->attributes->get('context'); /** @var \PKP\context\Context $context */

        $userRoles = collect($user->getRoles($context?->getId() ?? Application::CONTEXT_SITE))
            ->map(fn ($role) => $role->getId())
            ->sort();

        $matchableRoles = Str::of($matchableRoles)
            ->explode('|')
            ->map(fn($role) => (int)$role)
            ->sort();

        $matchedRoles = $userRoles->intersect($matchableRoles);

        // if no roles matched
        // Or if role matching set to strict and not all roles matched
        if ($matchedRoles->isEmpty() || ($rolesMatchingCriteria === self::ROLES_MATCH_STRICT && !$matchableRoles->diff($matchedRoles)->isEmpty())) {
            return response()->json([
                'error' => __('api.403.unauthorized'),
            ], Response::HTTP_UNAUTHORIZED);
        }

        return $next($request);
    }
}
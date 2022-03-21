<?php

declare(strict_types=1);

/**
 * @file classes/core/middleware/permissions/MatchRoles.php
 *
 * Copyright (c) 2014-2022 Simon Fraser University
 * Copyright (c) 2000-2022 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class MatchRoles
 * @ingroup core_middleware_permissions
 *
 * @brief Middleware to validate user roles
 */

namespace PKP\core\middleware\permissions;

use Closure;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Throwable;

class MatchRoles
{
    protected function getUserRoles($request): array
    {
        if (!$request->user()) {
            throw new AuthorizationException(
                'api.403.unauthorized',
                Response::HTTP_UNAUTHORIZED
            );
        }

        $contextId = $request->attributes->get('pkpContext')->getData('id');

        $roles = $request->user()->getRoles($contextId);

        $finalRoles = [];
        foreach ($roles as $role) {
            $finalRoles[] = $role->getId();
        }

        return $finalRoles;
    }

    /**
     * Verify if current user has the roles
     *
     * @param \Illuminate\Http\Request $request
     * @param string $roles Comma-separated list with roles
     */
    public function handle($request, Closure $next, string ...$roles)
    {
        try {
            $userRoles = $this->getUserRoles($request);

            if (array_intersect($userRoles, $roles) === []) {
                throw new AuthorizationException(
                    'api.403.unauthorized',
                    Response::HTTP_UNAUTHORIZED
                );
            }

            return $next($request);
        } catch (Throwable $e) {
            return new JsonResponse(
                ['error' => $e->getMessage()],
                $e->getCode()
            );
        }
    }
}

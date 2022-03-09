<?php

declare(strict_types=1);

namespace PKP\core\Controllers\Middlewares\Permissions;

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
            $finalRoles[$role->getId()] = true;
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

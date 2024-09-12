<?php

/**
 * @file classes/middleware/traits/HasRequiredMiddleware.php
 *
 * Copyright (c) 2024 Simon Fraser University
 * Copyright (c) 2024 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class HasRequiredMiddleware
 *
 * @brief Trait to find the required middleware attachments in a middleware
 */

namespace PKP\middleware\traits;

use Exception;
use Illuminate\Http\Request;
use PKP\core\PKPBaseController;

trait HasRequiredMiddleware
{
    /**
     * Following constant define how the required middleware validation will be handled
     *
     * MIDDLEWARE_MATCH_STRICT  All required middleware must be attached to target route
     * MIDDLEWARE_MATCH_LOOSE   At least  required middleware must be attached to target route
     */
    public const MIDDLEWARE_MATCH_STRICT = 1;
    public const MIDDLEWARE_MATCH_LOOSE = 2;

    /**
     * List of required middleware for this middleware to run passing logic
     */
    abstract public function requiredMiddleware(): array;

    /**
     * Determine if required middleware attached to target routes
     */
    public function hasRequiredMiddleware(Request $request, int $matchingCriteria = self::MIDDLEWARE_MATCH_STRICT): bool
    {
        $requiredMiddleware = collect($this->requiredMiddleware());

        if ($requiredMiddleware->count() === 0) {
            throw new Exception(
                sprintf(
                    'Empty required middleware list provided for middleware class %s',
                    static::class
                )
            );
        }

        $currentRoute = PKPBaseController::getRequestedRoute($request);
        $routeMiddleware = collect($currentRoute?->middleware() ?? []);

        $router = app('router'); /** @var \Illuminate\Routing\Router $router */
        $routerMiddleware = $router->getMiddleware();

        // need to replace the alias name with full class path
        $routeMiddleware = $routeMiddleware->map(function (string $middleware) use ($routerMiddleware): string {
            // extract the middleware class or alias name if in format
            // such as `has.roles:1|16|17` or `PKP\middleware\HasRoles:1|16|17`
            $fragments = array_pad(explode(':', $middleware, 2), 2, []);
            $middleware = array_shift($fragments);

            if (class_exists($middleware)) {
                return $middleware;
            }

            return $routerMiddleware[$middleware] ?? $middleware;
        });

        return match($matchingCriteria) {
            static::MIDDLEWARE_MATCH_STRICT
                => $routeMiddleware->intersect($requiredMiddleware)->count() === $requiredMiddleware->count(),
            static::MIDDLEWARE_MATCH_LOOSE
                => $routeMiddleware->intersect($requiredMiddleware)->count() > 0,
        };
    }
}

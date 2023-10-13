<?php

/**
 * @file classes/middleware/PolicyAuthorizer.php
 *
 * Copyright (c) 2023 Simon Fraser University
 * Copyright (c) 2023 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PolicyAuthorizer
 *
 * @ingroup middleware
 *
 * @brief Routing middleware to apply policy authorization
 */

namespace PKP\middleware;

use APP\core\Application;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\RouteCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use PKP\core\PKPBaseController;
use PKP\core\Registry;
use ReflectionFunction;

class PolicyAuthorizer
{
    /**
     * Run the policy authorization process
     *
     * It run the route associated controller's "authorize" method that contains the
     * policies before running the controller action .
     *
     */
    public function handle(Request $request, Closure $next)
    {
        $router = app('router'); /** @var \Illuminate\Routing\Router $router */

        $routeController = PKPBaseController::getRouteController($request);

        $pkpRequest = Application::get()->getRequest();

        if (!$pkpRequest->getUser()) {
            $user = $request->user();
            Registry::set('user', $user);
        }

        $args = [$request];
        $roleAssignments = $this->getRoleAssignmentMap($router->getRoutes());

        $hasAuthorized = $routeController->authorize(
            $pkpRequest,
            $args,
            $roleAssignments->toArray()
        );

        if (!$hasAuthorized) {

            $authorizationMessage = $routeController->getLastAuthorizationMessage();

            return response()->json([
                'error' => empty($authorizationMessage) ? __('api.403.unauthorized') : $authorizationMessage,
                'errorMessage' => empty($authorizationMessage) ? '' : __($authorizationMessage),
            ], Response::HTTP_UNAUTHORIZED);
        }

        return $next($request);
    }

    /**
     *  Return the collection of ROLE_ID_* constants to route controller actions
     *  maps in the follwoing format
     *  [
     *      ROLE_ID_... => [...allowed route controller operations...],
     *      ...
     *  ]
     */
    protected function getRoleAssignmentMap(RouteCollection $routes): Collection
    {
        $operationToRolesMap = collect($routes->getRoutes())
            ->flatMap(function ($route) { /** @var \Illuminate\Routing\Route $route */
                return [
                    (new ReflectionFunction($route->action['uses']))->getName() => collect($route->action['middleware'])
                        ->filter(function ($middleware) {
                            return strpos($middleware, 'has.roles') === 0 || strpos($middleware, "PKP\middleware\HasRoles") === 0;
                        })
                        ->flatMap(function ($middleware) {
                            return Str::of($middleware)
                                ->replace('has.roles:', '')
                                ->replace("PKP\middleware\HasRoles:", '')
                                ->explode('|');
                        })
                        ->unique()
                ];
            });

        $roles = collect([]);

        $operationToRolesMap->each(function ($values) use (&$roles) {
            $roles = $roles->merge($values);
        });

        $roles = $roles->unique()->flip()->map(fn ($role) => collect([]));

        collect($operationToRolesMap)->each(function ($roleList, $operation) use (&$roles) {
            collect($roleList)->each(function ($role) use (&$roles, $operation) {
                $roles->get($role)->push($operation);
            });
        });

        return $roles;
    }
}

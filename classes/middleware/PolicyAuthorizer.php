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

use Closure;
use Throwable;
use PKP\core\Registry;
use ReflectionFunction;
use APP\core\Application;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use PKP\core\PKPBaseController;
use Illuminate\Support\Collection;
use Illuminate\Routing\RouteCollection;


class PolicyAuthorizer
{
    /**
     * 
     * 
     * @param \Illuminate\Http\Request  $request
     * @param Closure                   $next
     * 
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        $router = app('router'); /** @var \Illuminate\Routing\Router $router */

        $routeController = PKPBaseController::getRouteController($request);

        $user = $request->user();
        Registry::set('user', $user);

        $pkpRequest         = Application::get()->getRequest();
        $args               = [$request];
        $roleAssignments    = $this->getRoleAssignmentMap($router->getRoutes());

        $hasAuthorized = $routeController->authorize(
            $pkpRequest,
            $args,
            $roleAssignments->toArray()
        );

        if (!$hasAuthorized) {
            $authorizationMessage = $routeController->getLastAuthorizationMessage();
            return response()->json([
                'error' => empty($authorizationMessage) ? __('api.403.unauthorized') : $authorizationMessage,
            ], Response::HTTP_FORBIDDEN);
        }

        return $next($request);
    }

    protected function getRoleAssignmentMap(RouteCollection $routes): Collection
    {
        $operationToRolesMap = collect($routes->getRoutes())
            ->flatMap(function($route) { /** @var \Illuminate\Routing\Route $route */
                return [
                    (new ReflectionFunction($route->action['uses']))->getName() => collect($route->action['middleware'])
                        ->filter(function($middleware) {
                            return strpos($middleware, 'has.roles') === 0;
                        })
                        ->flatMap(function($middleware){
                            return Str::of($middleware)
                                ->replace('has.roles:', '')
                                ->explode('|');
                        })
                        ->unique()
                ];
            });

        // ray(Application::get()->getRequest()->getRouter());

        $roles = collect([]);

        $operationToRolesMap->each(function($values) use (&$roles) {
            $roles = $roles->merge($values);
        });

        $roles = $roles->unique()->flip()->map(fn($role) => collect([]));

        collect($operationToRolesMap)->each(function($roleList, $operation) use (&$roles){
            collect($roleList)->each(function($role) use (&$roles, $operation){
                $roles->get($role)->push($operation);
            });
        });

        return $roles;
    }
}
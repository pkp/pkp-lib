<?php

declare(strict_types=1);

/**
 * @file classes/core/RoutingServiceProvider.php
 *
 * Copyright (c) 2014-2022 Simon Fraser University
 * Copyright (c) 2000-2022 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class RoutingServiceProvider
 * @ingroup core
 *
 * @brief Enables the Laravel router on the application
 */

namespace PKP\core;

use Illuminate\Foundation\Http\Middleware\ConvertEmptyStringsToNull;
use Illuminate\Foundation\Http\Middleware\ValidatePostSize;
use Illuminate\Http\Request;
use Illuminate\Routing\Router;
use Illuminate\Support\ServiceProvider;
use PKP\core\middleware\ConfigureBaseRequest;
use PKP\core\middleware\DecodingApiToken;
use PKP\core\middleware\FillContextBasedOnUri;
use PKP\core\middleware\HasUser;
use PKP\core\middleware\permissions\MatchRoles;
use PKP\core\middleware\permissions\NeedsContext;
use PKP\core\middleware\VerifyCsrfToken;

class RoutingServiceProvider extends ServiceProvider
{
    /**
     * The application's global HTTP middleware stack.
     *
     * These middleware are run during every request to your application.
     *
     * They will be evaluate at the order of the list. Doesn't order it alphabetically.
     *
     * @var array
     */
    protected $middleware = [
        ConfigureBaseRequest::class,
        FillContextBasedOnUri::class,
        DecodingApiToken::class,
        VerifyCsrfToken::class,
        ValidatePostSize::class,
        ConvertEmptyStringsToNull::class,
    ];

    /**
     * The application's route middleware.
     *
     * These middleware may be assigned to groups or used individually.
     *
     * @var array
     */
    protected $routeMiddleware = [
        'match.roles' => MatchRoles::class,
        'auth' => HasUser::class,
        'needs.context' => NeedsContext::class,
    ];

    /**
     * Register application services
     *
     * Services registered on the app container here can be automatically
     * injected as dependencies to classes that are instantiated by the
     * app container.
     *
     * @see https://laravel.com/docs/8.x/container#automatic-injection
     * @see https://laravel.com/docs/8.x/providers#the-register-method
     */
    public function register()
    {
        $this->registerRouter();
        $this->registerRouteMiddlewares();
        $this->registerGlobalMiddlewares();
        $this->registerRoutePatterns();
    }

    public function registerRouter(): void
    {
        $this->app->singleton('router', function ($app) {
            $request = Request::capture();
            $app->instance('Illuminate\Http\Request', $request);
            return new Router($app['events'], $app);
        });
    }

    public function registerGlobalMiddlewares(): void
    {
        $this->app->singleton('globalMiddlewares', function () {
            return $this->middleware;
        });
    }

    public function registerRouteMiddlewares(): void
    {
        foreach ($this->routeMiddleware as $key => $middleware) {
            app('router')->aliasMiddleware($key, $middleware);
        }
    }

    public function registerRoutePatterns(): void
    {
        app('router')->pattern('contextPath', '(.*?)');
        app('router')->pattern('version', '(.*?)');
    }
}

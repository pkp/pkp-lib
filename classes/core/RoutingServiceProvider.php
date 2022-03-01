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
use PKP\controllers\Middlewares\DecodingApiToken;
use PKP\controllers\Middlewares\HasUser;
use PKP\controllers\Middlewares\OnlyManagerRoles;
use PKP\controllers\Middlewares\OnlySiteAdminRoles;
use PKP\controllers\Middlewares\OnlySubEditorRoles;
use PKP\controllers\Middlewares\VerifyCsrfToken;

class RoutingServiceProvider extends ServiceProvider
{
    /**
     * The application's global HTTP middleware stack.
     *
     * These middleware are run during every request to your application.
     *
     * @var array
     */
    protected $middleware = [
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
        'only.manager.roles' => OnlyManagerRoles::class,
        'only.site.admin.roles' => OnlySiteAdminRoles::class,
        'only.sub.editor.roles' => OnlySubEditorRoles::class,
        'auth' => HasUser::class,
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

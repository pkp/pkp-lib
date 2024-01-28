<?php

/**
 * @file classes/core/PKPRoutingProvider.php
 *
 * Copyright (c) 2023 Simon Fraser University
 * Copyright (c) 2023 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PKPRoutingProvider
 *
 * @ingroup core
 *
 * @brief  The core routing service provider to handle laravel routing
 */

namespace PKP\core;

use Illuminate\Foundation\Http\Middleware\ConvertEmptyStringsToNull;
use Illuminate\Foundation\Http\Middleware\TrimStrings;
use Illuminate\Foundation\Http\Middleware\ValidatePostSize;
use Illuminate\Http\Request;
use Illuminate\Routing\Router;
use Illuminate\Routing\RoutingServiceProvider;
use Illuminate\Support\Facades\Response;
use PKP\middleware\AllowCrossOrigin;
use PKP\middleware\DecodeApiTokenWithValidation;
use PKP\middleware\HasContext;
use PKP\middleware\HasRoles;
use PKP\middleware\HasUser;
use PKP\middleware\PolicyAuthorizer;
use PKP\middleware\SetupContextBasedOnRequestUrl;
use PKP\middleware\ValidateCsrfToken;

class PKPRoutingProvider extends RoutingServiceProvider
{
    protected static $globalMiddleware = [
        AllowCrossOrigin::class,
        SetupContextBasedOnRequestUrl::class,
        DecodeApiTokenWithValidation::class,
        ValidateCsrfToken::class,
        ValidatePostSize::class,
        TrimStrings::class,
        ConvertEmptyStringsToNull::class,
        PolicyAuthorizer::class,
    ];

    /**
     * The application's route middleware.
     * These middleware can/should be assigned to specific routes or routes groups individually.
     */
    protected array $routeMiddleware = [
        'has.roles' => HasRoles::class,
        'has.user' => HasUser::class,
        'has.context' => HasContext::class,
    ];

    public static function getGlobalRouteMiddleware(): array
    {
        return self::$globalMiddleware;
    }

    /**
     * Register the service provider.
     *
     */
    public function register()
    {
        parent::register();

        $this->registerRouteMiddleware();
        $this->registerRoutePatterns();
        $this->registerResponseBindings();
    }

    /**
     * Boot the service provider.
     *
     */
    public function boot()
    {
        Response::macro('withCSV', function (array $rows, array $columns, int $maxRows) {
            return response()->stream(
                function () use ($rows, $columns) {
                    $fp = fopen('php://output', 'wt');

                    // Adds BOM (byte order mark) to enforce the UTF-8 format
                    fwrite($fp, "\xEF\xBB\xBF");

                    fputcsv($fp, ['']);
                    fputcsv($fp, $columns);

                    foreach ($rows as $row) {
                        fputcsv($fp, $row);
                    }

                    fclose($fp);
                },
                \Illuminate\Http\Response::HTTP_OK,
                [
                    'content-type' => 'text/csv',
                    'X-Total-Count' => $maxRows,
                    'content-disposition' => 'attachment; filename="user-report-' . date('Y-m-d') . '.csv"',
                ]
            );
        });
    }

    public function registerRouter(): void
    {
        $this->app->singleton('router', function ($app) {
            return new Router($app['events'], $app);
        });
    }

    public function registerRouteMiddleware(): void
    {
        $router = app('router'); /** @var \Illuminate\Routing\Router $router */

        foreach ($this->routeMiddleware as $key => $middleware) {
            $router->aliasMiddleware($key, $middleware);
        }
    }

    public function registerRoutePatterns(): void
    {
        $router = app('router'); /** @var \Illuminate\Routing\Router $router */

        $router->pattern('contextPath', '(.*?)');
        $router->pattern('version', '(.*?)');
    }

    protected function registerResponseBindings(): void
    {
        $container = PKPContainer::getInstance();

        $container->bind(\Illuminate\Routing\RouteCollectionInterface::class, \Illuminate\Routing\RouteCollection::class);
        $container->bind(
            \Illuminate\View\ViewFinderInterface::class,
            fn ($app) => new \Illuminate\View\FileViewFinder(app(\Illuminate\Filesystem\Filesystem::class), [])
        );
        $container->bind(\Illuminate\Contracts\View\Factory::class, \Illuminate\View\Factory::class);
        $container->bind(\Illuminate\Contracts\Routing\ResponseFactory::class, \Illuminate\Routing\ResponseFactory::class);
    }
}

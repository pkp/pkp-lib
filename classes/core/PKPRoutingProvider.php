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
 * @brief The core routing service provider to handle Laravel routing
 */

namespace PKP\core;

use Illuminate\Foundation\Http\Middleware\ConvertEmptyStringsToNull;
use Illuminate\Foundation\Http\Middleware\TrimStrings;
use Illuminate\Foundation\Http\Middleware\ValidatePostSize;
use Illuminate\Http\Request;
use Illuminate\Routing\Router;
use Illuminate\Routing\RoutingServiceProvider;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\URL;
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
    public const RESPONSE_CSV = [
        'mime' => 'text/csv',
        'extension' => '.csv',
        'separator' => ','
    ];

    public const RESPONSE_TSV = [
        'mime' => 'text/tab-separated-values',
        'extension' => '.tsv',
        'separator' => '\t'
    ];

    /**
     * Global middleware stack for API routes
     */
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
     * Middleware stack for web routes (Laravel packages like Log Viewer)
     *
     * NOTE: Session middleware (PKPEncryptCookies, StartSession, PKPAuthenticateSession)
     * is NOT included here because Dispatcher::initSession() already handles sessions
     * for ALL requests before routing. Running session middleware twice causes logout issues.
     */
    protected static $webMiddleware = [
        SetupContextBasedOnRequestUrl::class,
        ValidatePostSize::class,
        TrimStrings::class,
        ConvertEmptyStringsToNull::class,
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

    /**
     * Get global middleware stack for API routes
     */
    public static function getGlobalRouteMiddleware(): array
    {
        return self::$globalMiddleware;
    }

    /**
     * Get web route middleware stack for Laravel web packages
     */
    public static function getWebRouteMiddleware(): array
    {
        return self::$webMiddleware;
    }

    /**
     * Register the service provider.
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
     */
    public function boot()
    {
        $this->registerResponseMacros();
        $this->registerRequestSignatureMacros();
    }

    /**
     * Register custom response macros
     */
    protected function registerResponseMacros(): void
    {
        Response::macro('withFile', function (array $rows, array $columns, int $maxRows, array $responseType = PKPRoutingProvider::RESPONSE_CSV) {
            return response()->stream(
                function () use ($rows, $columns, $responseType) {
                    $fp = fopen('php://output', 'wt');

                    // Adds BOM (byte order mark) to enforce the UTF-8 format
                    fwrite($fp, "\xEF\xBB\xBF");

                    if (!empty($columns)) {
                        fputcsv($fp, [''], $responseType['separator']);
                        fputcsv($fp, $columns, $responseType['separator']);
                    }

                    foreach ($rows as $row) {
                        fputcsv($fp, $row, $responseType['separator']);
                    }

                    fclose($fp);
                },
                \Illuminate\Http\Response::HTTP_OK,
                [
                    'content-type' => $responseType['mime'],
                    'X-Total-Count' => $maxRows,
                    'content-disposition' => 'attachment; filename=user-report-' . date('Y-m-d') . $responseType['extension'],
                ]
            );
        });
    }

    /**
     * Register request signature validation macros
     *
     * NOTE: These macros are normally registered by Laravel's FoundationServiceProvider
     * but OJS doesn't use the full Laravel Foundation bootstrap. Required for
     * signed URL validation (e.g., Log Viewer file downloads).
     * 
     * FIXME: Remove this after the intregration of laravel core bootstrapping mechanims
     */
    protected function registerRequestSignatureMacros(): void
    {
        Request::macro('hasValidSignature', function ($absolute = true) {
            return URL::hasValidSignature($this, $absolute);
        });

        Request::macro('hasValidRelativeSignature', function () {
            return URL::hasValidSignature($this, $absolute = false);
        });

        Request::macro('hasValidSignatureWhileIgnoring', function ($ignoreQuery = [], $absolute = true) {
            return URL::hasValidSignature($this, $absolute, $ignoreQuery);
        });

        Request::macro('hasValidRelativeSignatureWhileIgnoring', function ($ignoreQuery = []) {
            return URL::hasValidSignature($this, $absolute = false, $ignoreQuery);
        });
    }

    /**
     * Register the router singleton
     */
    public function registerRouter(): void
    {
        $this->app->singleton('router', function ($app) {
            return new Router($app['events'], $app);
        });
    }

    /**
     * Register route middleware aliases
     */
    public function registerRouteMiddleware(): void
    {
        $router = app('router'); /** @var \Illuminate\Routing\Router $router */

        foreach ($this->routeMiddleware as $key => $middleware) {
            $router->aliasMiddleware($key, $middleware);
        }
    }

    /**
     * Register route parameter patterns
     */
    public function registerRoutePatterns(): void
    {
        $router = app('router'); /** @var \Illuminate\Routing\Router $router */

        $router->pattern('contextPath', '(.*?)');
        $router->pattern('version', '(.*?)');
    }

    /**
     * Register response-related bindings
     */
    protected function registerResponseBindings(): void
    {
        $this->app->bind(\Illuminate\Routing\RouteCollectionInterface::class, \Illuminate\Routing\RouteCollection::class);
        $this->app->bind(
            \Illuminate\View\ViewFinderInterface::class,
            fn ($app) => new \Illuminate\View\FileViewFinder(app(\Illuminate\Filesystem\Filesystem::class), [])
        );
        $this->app->bind(\Illuminate\Contracts\View\Factory::class, \Illuminate\View\Factory::class);
        $this->app->bind(\Illuminate\Contracts\Routing\ResponseFactory::class, \Illuminate\Routing\ResponseFactory::class);
    }
}

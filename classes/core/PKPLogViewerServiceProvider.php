<?php

/**
 * @file classes/core/PKPLogViewerServiceProvider.php
 *
 * Copyright (c) 2026 Simon Fraser University
 * Copyright (c) 2026 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PKPLogViewerServiceProvider
 *
 * @brief Custom Log Viewer service provider for OJS/OMP/OPS
 *
 * This extends the opcodesio/log-viewer service provider and overrides
 * methods that require a full Laravel application (HTTP Kernel, Octane, etc.)
 * since OJS only uses parts of Laravel as a dependency.
 *
 * Key difference from standard Laravel: Routes are NOT registered during boot.
 * Instead, routes are registered lazily by WebRouter only when handling /_pkp/* requests.
 * This prevents Log Viewer's routes from interfering with OJS's PageRouter.
 */

namespace PKP\core;

use Illuminate\Support\Str;
use Illuminate\Support\Facades\Event;
use Opcodes\LogViewer\Facades\LogViewer;
use Opcodes\LogViewer\Events\LogFileDeleted;
use Opcodes\LogViewer\LogViewerServiceProvider;

class PKPLogViewerServiceProvider extends LogViewerServiceProvider
{
    private string $name = 'log-viewer';
    
    /**
     * Track whether routes have been registered (for lazy loading)
     */
    private static bool $routesRegistered = false;

    /**
     * Override boot to skip route registration
     *
     * Routes are registered lazily via registerRoutesNow() called by WebRouter
     */
    public function boot()
    {
        if ($this->app->runningInConsole()) {
            // Publishing config and assets for artisan commands
            $this->publishes([
                self::basePath("/config/{$this->name}.php") => config_path("{$this->name}.php"),
            ], "{$this->name}-config");

            $this->publishes([
                self::basePath('/resources/views') => resource_path("views/vendor/{$this->name}"),
            ], "{$this->name}-views");
        }

        if (!$this->isEnabled()) {
            return;
        }

        // DO NOT call registerRoutes() here - routes are registered lazily
        // This is the key difference: we skip route registration during boot

        $this->registerResources();
        $this->defineAssetPublishing();
        $this->defineDefaultGates();
        
        // IMPORTANT
        // configureMiddleware() and resetStateAfterOctaneRequest() are overridden to be empty

        Event::listen(LogFileDeleted::class, function ($event) {
            LogViewer::clearFileCache();
        });
    }

    /**
     * Register Log Viewer routes on-demand
     *
     * This should be called by WebRouter before dispatching a /_pkp/* request.
     * Routes are only registered once per request lifecycle.
     */
    public static function registerRoutesNow(): void
    {
        if (self::$routesRegistered) {
            return;
        }

        $routePath = config('log-viewer.route_path', 'log-viewer');
        $router = app('router'); /** @var \Illuminate\Routing\Router $router */

        // Register API routes
        $router->group([
            'prefix' => Str::finish($routePath, '/') . 'api',
            'namespace' => 'Opcodes\LogViewer\Http\Controllers',
            'middleware' => config('log-viewer.api_middleware', []),
        ], function ($router) {
            require LogViewerServiceProvider::basePath('/routes/api.php');
        });

        // Register web routes
        $router->group([
            'prefix' => $routePath,
            'namespace' => 'Opcodes\LogViewer\Http\Controllers',
            'middleware' => config('log-viewer.middleware', []),
        ], function ($router) {
            require LogViewerServiceProvider::basePath('/routes/web.php');
        });

        // Refresh the route name lookups to ensure named routes are findable
        $router->getRoutes()->refreshNameLookups();

        // Sync the URL generator with the updated routes
        $urlGenerator = app('url'); /** @var \Illuminate\Routing\UrlGenerator $urlGenerator */
        $urlGenerator->setRoutes($router->getRoutes());

        self::$routesRegistered = true;
    }

    /**
     * Check if routes have been registered
     */
    public static function areRoutesRegistered(): bool
    {
        return self::$routesRegistered;
    }

    /**
     * Override: Skip HTTP Kernel middleware configuration
     *
     * OJS doesn't use Laravel's HTTP Kernel - it has its own routing system.
     * Middleware is configured in PKPRoutingProvider instead.
     */
    protected function configureMiddleware(): void
    {
        // Intentionally empty - OJS handles middleware via WebRouter and PKPRoutingProvider
        // probably will have no impacts in future once moved to laravel routing for page/component
        // routing
    }

    /**
     * Override: Skip Octane state reset
     *
     * OJS doesn't use Laravel Octane.
     */
    protected function resetStateAfterOctaneRequest(): void
    {
        // Intentionally empty - OJS doesn't use Octane
    }
}

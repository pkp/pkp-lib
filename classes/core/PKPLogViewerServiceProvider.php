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
 * This extends the opcodesio/log-viewer service provider with PKP-specific
 * configuration, routing, authorization, and custom log parsers.
 *
 * Key differences from standard Laravel:
 * - register() skips mergeConfigFrom() — all config is set explicitly
 * - Routes are NOT registered during boot() — registered lazily via LoadHandler hook
 * - LoadHandler hook intercepts site-level requests matching log-viewer paths
 * - Custom log parsers for PHP errors, scheduled tasks, and usage events
 */

namespace PKP\core;

use APP\core\Application;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Pipeline\Pipeline;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Opcodes\LogViewer\Events\LogFileDeleted;
use Opcodes\LogViewer\Facades\LogViewer;
use Opcodes\LogViewer\LogTypeRegistrar;
use Opcodes\LogViewer\LogViewerService;
use Opcodes\LogViewer\LogViewerServiceProvider;
use PKP\config\Config;
use PKP\logParser\PKPPhpErrorLog;
use PKP\logParser\PKPScheduledTaskLog;
use PKP\logParser\PKPUsageEventLog;
use PKP\middleware\SiteAdminAuthorizer;
use PKP\plugins\Hook;
use PKP\scheduledTask\ScheduledTaskHelper;
use PKP\security\Role;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

class PKPLogViewerServiceProvider extends LogViewerServiceProvider
{
    private string $name = 'log-viewer';

    /**
     * Track whether routes have been registered (for lazy loading)
     */
    private static bool $routesRegistered = false;

    /**
     * Override register to skip mergeConfigFrom()
     *
     * Here sets all log-viewer config explicitly in configureLogViewer().
     * The vendor's mergeConfigFrom() would load defaults that get fully overwritten anyway.
     */
    public function register()
    {
        $this->app->bind('log-viewer', LogViewerService::class);

        $this->app->bind('log-viewer-cache', function () {
            return Cache::driver(config('log-viewer.cache_driver'));
        });

        if (!$this->app->bound(LogTypeRegistrar::class)) {
            $this->app->singleton(LogTypeRegistrar::class, function () {
                return new LogTypeRegistrar();
            });
        }
    }

    /**
     * Bootstrap the log viewer service
     */
    public function boot()
    {
        // Configure all log-viewer settings
        $this->configureLogViewer();

        // Register LoadHandler hook to intercept log-viewer requests before APP page router
        Hook::add('LoadHandler', $this->handleLaravelRoutes(...), Hook::SEQUENCE_CORE);

        if (!$this->isEnabled()) {
            return;
        }

        // Vendor boot tasks (skip route registration — done lazily)
        $this->registerResources();
        $this->defineAssetPublishing();
        $this->defineDefaultGates();
        $this->remapLaravelLocaleKeys();

        Event::listen(LogFileDeleted::class, function ($event) {
            LogViewer::clearFileCache();
        });

        // Authorization and custom log parsers
        $this->configureAuthorization();
        $this->registerLogParsers();
    }

    /**
     * Configure all Log Viewer settings
     *
     * Sets the complete log-viewer config explicitly. Since register() skips
     * mergeConfigFrom(), every config key must be defined here.
     */
    protected function configureLogViewer(): void
    {
        $this->app['config']->set('log-viewer', [
            // Core settings
            'enabled' => true,
            'api_only' => false,
            'require_auth_in_production' => true,

            // Route configuration
            // Route path includes 'index' (site context) for SetupContextBasedOnRequestUrl middleware
            'route_path' => 'index/admin/log-viewer',
            'route_domain' => null,
            'assets_path' => 'vendor/log-viewer',

            // UI settings
            'back_to_system_url' => null,
            'back_to_system_label' => null,
            'timezone' => null,
            'datetime_format' => 'Y-m-d H:i:s',

            // Size limits
            'max_log_size' => 128 * 1024 * 1024, // 128 MB

            // API configuration
            'api_enabled' => true,
            'api_stateful_domains' => [],
            'api_middleware' => [
                SiteAdminAuthorizer::class,
            ],
            'middleware' => [
                SiteAdminAuthorizer::class,
            ],

            // Remote hosts
            'hosts' => [
                'local' => [
                    'name' => 'Local',
                ],
            ],

            // Log files to include - supports glob patterns and absolute paths
            'include_files' => array_merge(
                [
                    // Laravel logs under files_dir/logs/
                    storage_path('logs') . '/*.log',
                ],
                // files_dir log directories (scheduled tasks, usage stats)
                $this->getFilesDirLogPaths(),
                // Additional log paths from config.inc.php [logs] section
                $this->getConfiguredLogPaths()
            ),
            'exclude_files' => [],
            'exclude_patterns' => [],
            'hide_unknown_files' => false, // Show all configured files even if format not recognized

            // Stack trace display
            'shorter_stack_trace_excludes' => [
                'vendor/symfony',
                'vendor/laravel',
            ],
            'strip_extracted_context' => true,

            // Cache configuration
            'cache_driver' => null, // Uses app default ('file')
            'cache_key_prefix' => 'lv',

            // Scanning
            'lazy_scan_chunk_size_in_mb' => 50,

            // Pagination
            'per_page_options' => [10, 25, 50, 100, 250, 500],

            // Default UI preferences
            'defaults' => [
                'use_local_storage' => true,
                'folder_sorting_method' => \Opcodes\LogViewer\Enums\SortingMethod::ModifiedTime,
                'folder_sorting_order' => \Opcodes\LogViewer\Enums\SortingOrder::Descending,
                'file_sorting_method' => \Opcodes\LogViewer\Enums\SortingMethod::ModifiedTime,
                'log_sorting_order' => \Opcodes\LogViewer\Enums\SortingOrder::Descending,
                'per_page' => 25,
                'theme' => \Opcodes\LogViewer\Enums\Theme::System,
                'shorter_stack_traces' => false,
            ],

            // Identifiers
            'exclude_ip_from_identifiers' => false,
            'root_folder_prefix' => 'root',
        ]);
    }

    /**
     * Configure authorization — only site admins can access
     */
    protected function configureAuthorization(): void
    {
        LogViewer::auth(function ($request) {
            $pkpRequest = Application::get()->getRequest();
            $user = $pkpRequest->getUser();

            return $user && $user->hasRole([Role::ROLE_ID_SITE_ADMIN], Application::SITE_CONTEXT_ID);
        });
    }

    /**
     * Register custom log parsers for PHP errors and APP-specific log types
     */
    protected function registerLogParsers(): void
    {
        // Override built-in PhpFpmLog parser with better PHP error log support
        LogViewer::extend('php_fpm', PKPPhpErrorLog::class);

        // APP-specific log types
        LogViewer::extend('scheduled_tasks', PKPScheduledTaskLog::class);
        LogViewer::extend('usage_stats', PKPUsageEventLog::class);
    }

    /**
     * Handle Laravel routes when App page router can't find a handler
     *
     * This hook intercepts requests before APP tries to load page handlers.
     * It checks if the request matches a known Laravel route prefix (e.g., admin/log-viewer)
     * and only then attempts Laravel dispatch.
     *
     * @param string $hookName The hook name
     * @param array $args Hook arguments: [&$page, &$op, &$sourceFile, &$handler]
     *
     * @return bool Hook::ABORT if handled, Hook::CONTINUE otherwise
     */
    public function handleLaravelRoutes(string $hookName, array $args): bool
    {
        $page = $args[0];
        $op = $args[1];

        // Only handle site-level requests (context = null means site-level)
        $request = Application::get()->getRequest();
        $context = $request->getContext();

        if ($context !== null) {
            return Hook::CONTINUE;
        }

        if (!$this->isLaravelRoutePath($page, $op)) {
            return Hook::CONTINUE;
        }

        return $this->tryLaravelDispatch() ? Hook::ABORT : Hook::CONTINUE;
    }

    /**
     * Check if the given page/op could match a Laravel route
     *
     * @param string $page The requested page
     * @param string $op The requested operation
     *
     * @return bool True if this could be a Laravel route
     */
    protected function isLaravelRoutePath(string $page, string $op): bool
    {
        $routePath = config('log-viewer.route_path');
        $parts = explode('/', trim($routePath, '/'));

        // Route path format: 'index/{page}/{op}' or 'index/{page}/{op}/...'
        if (count($parts) >= 3 && $parts[0] === 'index') {
            $routePage = $parts[1];
            $routeOp = $parts[2];

            if ($page === $routePage && str_starts_with($op, $routeOp)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Attempt to dispatch request through Laravel routing
     *
     * @return bool True if request was handled, false otherwise
     */
    protected function tryLaravelDispatch(): bool
    {
        // Ensure routes are registered
        static::registerRoutesNow();

        try {
            $pkpRequest = Application::get()->getRequest();
            $router = $pkpRequest->getRouter(); /** @var \APP\core\PageRouter $router */

            $page = $router->getRequestedPage($pkpRequest);
            $op = $router->getRequestedOp($pkpRequest);
            $args = $router->getRequestedArgs($pkpRequest);

            // Include 'index' (site context path) so SetupContextBasedOnRequestUrl middleware
            // recognizes this as a site-level request
            $laravelPath = 'index/' . $page . '/' . $op;
            if (!empty($args)) {
                $laravelPath .= '/' . implode('/', $args);
            }

            $illuminateRequest = app()->get(Request::class); /** @var \Illuminate\Http\Request $illuminateRequest */

            // Preserve the base URL for signed URL validation
            $baseUrl = $illuminateRequest->getBaseUrl();
            $queryString = $illuminateRequest->server->get('QUERY_STRING', '');

            $requestUri = $baseUrl . '/' . $laravelPath;
            if ($queryString) {
                $requestUri .= '?' . $queryString;
            }

            $modifiedRequest = $illuminateRequest->duplicate(
                null, null, null, null, null,
                array_merge($illuminateRequest->server->all(), [
                    'REQUEST_URI' => $requestUri,
                    'PATH_INFO' => '/' . $laravelPath,
                ])
            );

            $response = (new Pipeline(app()))
                ->send($modifiedRequest)
                ->through(PKPRoutingProvider::getWebRouteMiddleware())
                ->then(fn($req) => app('router')->dispatch($req));

            if ($response->getStatusCode() !== Response::HTTP_NOT_FOUND) {
                $response->send();
                exit;
            }
        } catch (NotFoundHttpException $e) {
            // No Laravel route matched — expected case
        } catch (Throwable $e) {
            Log::error('Laravel route dispatch error: ' . $e->getMessage());
        }

        return false;
    }

    /**
     * Remap Laravel locale keys to PKP equivalents
     *
     * Laravel packages (e.g., pagination) use keys like 'pagination.next' which don't exist
     * in PKP's locale system. PKP has them as 'common.pagination.next' etc.
     */
    protected function remapLaravelLocaleKeys(): void
    {
        $remaps = [
            'pagination.previous' => 'common.pagination.previous',
            'pagination.next' => 'common.pagination.next',
        ];

        Hook::add('Locale::translate', function (string $hookName, array $args) use ($remaps): bool {
            $value = &$args[0];
            $key = $args[1];

            if (isset($remaps[$key])) {
                $value = __($remaps[$key]);
                return Hook::ABORT;
            }

            return Hook::CONTINUE;
        });
    }

    /**
     * Register Log Viewer routes on-demand
     *
     * Called by tryLaravelDispatch() when a matching request is detected.
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

        // Refresh route name lookups
        $router->getRoutes()->refreshNameLookups();

        // Sync URL generator with updated routes
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
     * Get log paths from files_dir subdirectories
     *
     * @return array<int, string> Log paths for Log Viewer's include_files config
     */
    protected function getFilesDirLogPaths(): array
    {
        $paths = [];
        $filesDir = Config::getVar('files', 'files_dir');

        // Scheduled task execution logs
        $scheduledTaskLogsDir = $filesDir . '/' . ScheduledTaskHelper::SCHEDULED_TASK_EXECUTION_LOG_DIR;
        if (is_dir($scheduledTaskLogsDir)) {
            $paths[] = $scheduledTaskLogsDir . '/*.log';
        }

        // Usage event logs
        $usageEventLogsDir = $filesDir . '/usageStats/usageEventLogs';
        if (is_dir($usageEventLogsDir)) {
            $paths[] = $usageEventLogsDir . '/*.log';
        }

        return $paths;
    }

    /**
     * Get log paths from config.inc.php [logs] section
     *
     * Supported config options:
     *   nginx_error_log = /var/log/nginx/error.log
     *   apache_error_log = /var/log/apache2/error.log
     *   php_error_log = /var/log/php/error.log (falls back to ini_get('error_log') if not set)
     *   postgres_log = /var/log/postgresql/postgresql.log
     *   supervisor_log = /var/log/supervisor/supervisord.log
     *   http_access_log = /var/log/nginx/access.log
     *
     * @return array<int, string> Log paths for Log Viewer's include_files config
     */
    protected function getConfiguredLogPaths(): array
    {
        $paths = [];

        // Nginx error log
        $nginxErrorLog = Config::getVar('logs', 'nginx_error_log');
        if ($nginxErrorLog && file_exists($nginxErrorLog)) {
            $paths[] = $nginxErrorLog;
        }

        // Apache error log
        $apacheErrorLog = Config::getVar('logs', 'apache_error_log');
        if ($apacheErrorLog && file_exists($apacheErrorLog)) {
            $paths[] = $apacheErrorLog;
        }

        // PHP error log - with fallback to ini_get('error_log')
        $phpErrorLog = Config::getVar('logs', 'php_error_log');
        if (!$phpErrorLog) {
            $phpErrorLog = ini_get('error_log');
        }
        if ($phpErrorLog && file_exists($phpErrorLog)) {
            $paths[] = $phpErrorLog;
        }

        // PostgreSQL log
        $postgresLog = Config::getVar('logs', 'postgres_log');
        if ($postgresLog && file_exists($postgresLog)) {
            $paths[] = $postgresLog;
        }

        // Supervisor log
        $supervisorLog = Config::getVar('logs', 'supervisor_log');
        if ($supervisorLog && file_exists($supervisorLog)) {
            $paths[] = $supervisorLog;
        }

        // HTTP access log
        $httpAccessLog = Config::getVar('logs', 'http_access_log');
        if ($httpAccessLog && file_exists($httpAccessLog)) {
            $paths[] = $httpAccessLog;
        }

        return $paths;
    }

    /**
     * Override: Skip HTTP Kernel middleware configuration
     */
    protected function configureMiddleware(): void
    {
        // Intentionally empty — APP handles middleware via PKPRoutingProvider
    }

    /**
     * Override: Skip Octane state reset
     */
    protected function resetStateAfterOctaneRequest(): void
    {
        // Intentionally empty — APP doesn't use Octane
    }
}

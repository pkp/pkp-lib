<?php

/**
 * @file classes/core/LogServiceProvider.php
 *
 * Copyright (c) 2026 Simon Fraser University
 * Copyright (c) 2026 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class LogServiceProvider
 * 
 * @brief Service provider for log viewing functionality
 *
 * Registers and configures the opcodesio/log-viewer package for OJS/OMP/OPS,
 * providing a web interface for viewing Laravel logs, server logs (Nginx, Apache),
 * and PHP logs.
 *
 * Uses the LoadHandler hook to intercept requests that OJS can't handle and
 * attempts to route them through Laravel's routing system.
 */

namespace PKP\core;

use APP\core\Application;
use Illuminate\Support\Facades\Log;
use Illuminate\Pipeline\Pipeline;
use Illuminate\Http\Request;
use Opcodes\LogViewer\Facades\LogViewer;
use PKP\core\PKPPhpErrorLog;
use PKP\core\PKPRoutingProvider;
use PKP\config\Config;
use PKP\middleware\SiteAdminAuthorizer;
use PKP\plugins\Hook;
use PKP\security\Role;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

class LogServiceProvider extends \Illuminate\Log\LogServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        parent::register();
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // Register hook to intercept unmatched routes BEFORE registering Log Viewer
        // This ensures Laravel routes are tried when OJS can't handle the request
        Hook::add('LoadHandler', $this->handleLaravelRoutes(...), Hook::SEQUENCE_CORE);

        $this->registerLogViewer();
    }

    /**
     * Handle Laravel routes when OJS page router can't find a handler
     *
     * This hook intercepts requests before OJS tries to load page handlers.
     * It checks if the request matches a known Laravel route prefix (e.g., admin/log-viewer)
     * and only then attempts Laravel dispatch. This prevents interference with normal OJS routing.
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
        $request = \APP\core\Application::get()->getRequest();
        $context = $request->getContext();

        if ($context !== null) {
            return Hook::CONTINUE; // Context-level request, let OJS handle
        }

        // Check if this request could match a Laravel route
        // Log Viewer is at 'admin/log-viewer', so check for page='admin' and op starting with 'log-viewer'
        if (!$this->isLaravelRoutePath($page, $op)) {
            return Hook::CONTINUE; // Not a Laravel route path, let OJS handle
        }

        // Try to dispatch through Laravel routes
        return $this->tryLaravelDispatch() ? Hook::ABORT : Hook::CONTINUE;
    }

    /**
     * Check if the given page/op could match a Laravel route
     *
     * This method checks against known Laravel route prefixes to avoid
     * running Laravel dispatch for every request.
     *
     * @param string $page The requested page
     * @param string $op The requested operation
     *
     * @return bool True if this could be a Laravel route
     */
    protected function isLaravelRoutePath(string $page, string $op): bool
    {
        // Get configured route path (e.g., 'index/admin/log-viewer')
        $routePath = config('log-viewer.route_path', 'index/admin/log-viewer');
        $parts = explode('/', trim($routePath, '/'));

        // Route path format: 'index/{page}/{op}' or 'index/{page}/{op}/...'
        // Skip the 'index' prefix (site context) to get page/op
        if (count($parts) >= 3 && $parts[0] === 'index') {
            $routePage = $parts[1];
            $routeOp = $parts[2];

            // Match if page equals route page and op starts with route op
            // This handles both exact match (admin/log-viewer) and sub-paths (admin/log-viewer/api/...)
            if ($page === $routePage && str_starts_with($op, $routeOp)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Attempt to dispatch request through Laravel routing
     *
     * Registers Laravel package routes and attempts to match the current request.
     * If a route matches and returns a valid response (not 404), the response is sent.
     *
     * The OJS URL structure is: /{contextPath}/{locale}/{page}/{op}/{args...}
     * Laravel routes are registered relative to page/op (e.g., 'admin/log-viewer').
     * We need to modify the Laravel request path to match the route registration.
     *
     * @return bool True if request was handled, false otherwise
     */
    protected function tryLaravelDispatch(): bool
    {
        // FIXME: Need this chekc ??
        // Check if Log Viewer package is installed
        if (!class_exists(\Opcodes\LogViewer\LogViewerServiceProvider::class)) {
            return false;
        }

        // Ensure routes are registered
        PKPLogViewerServiceProvider::registerRoutesNow();

        try {
            // Get the OJS request to extract page/op path
            $pkpRequest = Application::get()->getRequest();
            $router = $pkpRequest->getRouter(); /** @var \APP\core\PageRouter $router */

            // Build the Laravel-compatible path from OJS page/op
            // OJS URL: /index/en/admin/log-viewer -> Laravel path: admin/log-viewer
            $page = $router->getRequestedPage($pkpRequest);
            $op = $router->getRequestedOp($pkpRequest);
            $args = $router->getRequestedArgs($pkpRequest);

            // Include 'index' (site context path) so SetupContextBasedOnRequestUrl middleware
            // recognizes this as a site-level request (not a journal context)
            $laravelPath = 'index/' . $page . '/' . $op;
            if (!empty($args)) {
                $laravelPath .= '/' . implode('/', $args);
            }

            // Create a modified request with the correct path for Laravel routing
            $illuminateRequest = app()->get(Request::class); /** @var \Illuminate\Http\Request $illuminateRequest */

            // Preserve the base URL (e.g., '/index.php') to maintain URL consistency
            // for signed URL validation. The signature was generated with the full URL
            // including index.php, so we must preserve it.
            $baseUrl = $illuminateRequest->getBaseUrl(); // e.g., '/index.php' or ''
            $queryString = $illuminateRequest->server->get('QUERY_STRING', '');

            // Build the full REQUEST_URI: baseUrl + path + query string
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

            // If we got a valid response (not 404), send it and exit
            if ($response->getStatusCode() !== 404) {
                $response->send();
                exit; // Stop execution to prevent OJS from modifying headers
            }
        } catch (NotFoundHttpException $e) {
            // No Laravel route matched - this is an expectable case, not an error
        } catch (Throwable $e) {
            // Log unexpected errors but don't crash - let OJS handle the request
            Log::error('Laravel route dispatch error: ' . $e->getMessage());
        }

        return false; // Request not handled, let OJS continue
    }

    /**
     * Register and configure the Log Viewer package
     *
     * This method configures and registers the opcodesio/log-viewer package
     * if it's installed. The Log Viewer provides a web interface for viewing
     * log files at /index/admin/log-viewer
     */
    protected function registerLogViewer(): void
    {
        // FIXME: need it ??
        // Check if the Log Viewer package is installed
        if (!class_exists(\Opcodes\LogViewer\LogViewerServiceProvider::class)) {
            return;
        }

        // Configure Log Viewer settings before registration
        // Route path includes 'index' (site context) for SetupContextBasedOnRequestUrl middleware
        $this->app['config']->set('log-viewer', [
            'route_path' => 'index/admin/log-viewer',
            'back_to_system_url' => null,
            'max_log_size' => 128 * 1024 * 1024, // 128 MB
            'api_enabled' => true,
            'api_stateful_domains' => [],
            'api_middleware' => [
                SiteAdminAuthorizer::class,
            ],
            'middleware' => [
                SiteAdminAuthorizer::class,
            ],
            // Log files to include - supports glob patterns and absolute paths
            // Keys can be used to group/rename folders in the UI
            'include_files' => array_merge(
                [
                    // OJS Laravel logs in storage/logs
                    '*.log',
                    '**/*.log',
                ],
                // Additional log paths from config.inc.php [logs] section
                $this->getConfiguredLogPaths()
            ),
            'exclude_files' => [],
            'exclude_patterns' => [],
            'shorter_stack_trace_excludes' => [
                'vendor/symfony',
                'vendor/laravel',
            ],
            'cache_driver' => null,
            'timezone' => null,
            'lazy_scan_chunk_size_in_mb' => 50,
            'hide_unknown_files' => false, // Show all configured files even if format not recognized
        ]);

        // Register our custom Log Viewer service provider
        // Routes are registered lazily via LoadHandler hook
        $this->app->register(new PKPLogViewerServiceProvider($this->app));

        // FIXME: do we need this as we have attached the middleware \PKP\middleware\SiteAdminAuthorizer
        // Configure authorization - only site admins can access
        LogViewer::auth(function ($request) {
            $pkpRequest = Application::get()->getRequest();
            $user = $pkpRequest->getUser();

            return $user && $user->hasRole([Role::ROLE_ID_SITE_ADMIN], Application::SITE_CONTEXT_ID);
        });

        // Register custom PHP error log parser for better PHP-FPM/error log support
        // This parser handles PHP error formats and exceptions with stack traces
        // Using 'php_fpm' key overrides the built-in PhpFpmLog parser (prepended to list)
        LogViewer::extend('php_fpm', PKPPhpErrorLog::class);
    }

    /**
     * Get log paths from config.inc.php [logs] section
     *
     * Supported config options:
     *   nginx_error_log = /var/log/nginx/error.log
     *   apache_error_log = /var/log/apache2/error.log
     *   php_fpm_log = /var/log/php-fpm.log
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

        // PHP-FPM log
        $phpFpmLog = Config::getVar('logs', 'php_fpm_log');
        if ($phpFpmLog && file_exists($phpFpmLog)) {
            $paths[] = $phpFpmLog;
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
}

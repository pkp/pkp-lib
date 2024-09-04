<?php

/**
 * @file classes/core/Dispatcher.php
 *
 * Copyright (c) 2014-2024 Simon Fraser University
 * Copyright (c) 2000-2024 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class Dispatcher
 *
 * @brief Class dispatching HTTP requests to handlers.
 */

namespace PKP\core;

use PKP\config\Config;
use PKP\plugins\Hook;
use PKP\plugins\PluginRegistry;
use PKP\services\PKPSchemaService;

class Dispatcher
{
    public PKPApplication $_application;

    /** @var array an array of Router implementation class names */
    public array $_routerNames = [];

    /** @var array an array of Router instances */
    public array $_routerInstances = [];

    public PKPRouter $_router;

    /** @var PKPRequest Used for a callback hack - NOT GENERALLY SET. */
    public ?PKPRequest $_requestCallbackHack;

    /**
     * Get the application
     */
    public function &getApplication(): PKPApplication
    {
        return $this->_application;
    }

    /**
     * Set the application
     */
    public function setApplication(PKPApplication $application)
    {
        $this->_application = $application;
    }

    /**
     * Get the router names
     *
     * @return array an array of Router names
     */
    public function &getRouterNames(): array
    {
        return $this->_routerNames;
    }

    /**
     * Add a router name.
     *
     * NB: Routers will be called in the order that they
     * have been added to the dispatcher. The first router
     * that supports the request will be called. The last
     * router should always be a "catch-all" router that
     * supports all types of requests.
     *
     * NB: Routers must be part of the core package
     * to be accepted by this dispatcher implementation.
     *
     * @param string $routerName a class name of a router
     *  to be given the chance to route the request.
     *  NB: These are class names and not instantiated objects. We'll
     *  use lazy instantiation to reduce the performance/memory impact
     *  to a minimum.
     * @param string $shortcut a shortcut name for the router
     *  to be used for quick router instance retrieval.
     */
    public function addRouterName(string $routerName, string $shortcut)
    {
        $this->_routerNames[$shortcut] = $routerName;
    }

    /**
     * Determine the correct router for this request. Then
     * let the router dispatch the request to the appropriate
     * handler method.
     *
     * @hook Dispatcher::dispatch [[$request]]
     */
    public function dispatch(PKPRequest $request)
    {
        // Make sure that we have at least one router configured
        $routerNames = $this->getRouterNames();
        assert(count($routerNames) > 0);

        // Go through all configured routers by priority
        // and find out whether one supports the incoming request
        /** @var PKPRouter */
        $router = null;
        foreach ($routerNames as $shortcut => $routerCandidateName) {
            $routerCandidate = & $this->_instantiateRouter($routerCandidateName, $shortcut);

            // Does this router support the current request?
            if ($routerCandidate->supports($request)) {
                // Inject router and dispatcher into request
                $request->setRouter($routerCandidate);
                $request->setDispatcher($this);

                $this->setUserResolver();
                $this->initSession();

                // We've found our router and can go on
                // to handle the request.
                $router = & $routerCandidate;
                $this->_router = & $router;
                break;
            }
        }

        // None of the router handles this request? This is a development-time
        // configuration error.
        if (is_null($router)) {
            throw new \Exception('None of the configured routers supports this request.');
        }

        // Can we serve a cached response?
        if ($router->isCacheable($request)) {
            $this->_requestCallbackHack = & $request;
            if (Config::getVar('cache', 'web_cache')) {
                if ($this->_displayCached($router, $request)) {
                    exit;
                } // Success
                ob_start($this->_cacheContent(...));
            }
        } else {
            if (isset($_SERVER['HTTP_X_MOZ']) && $_SERVER['HTTP_X_MOZ'] == 'prefetch') {
                header('HTTP/1.0 403 Forbidden');
                echo '403: Forbidden<br><br>Pre-fetching not allowed.';
                exit;
            }
        }

        PluginRegistry::loadCategory('generic', true);
        PluginRegistry::loadCategory('pubIds', true);

        Hook::call('Dispatcher::dispatch', [$request]);

        // Reload the context after generic plugins have loaded so that changes to
        // the context schema can take place
        $contextSchema = app()->get('schema')->get(PKPSchemaService::SCHEMA_CONTEXT, true);
        $request->getRouter()->getContext($request, true);

        $router->route($request);
    }

    /**
     * Init the session by running through session related middleware
     */
    public function initSession(): void
    {
        if (PKPSessionGuard::isSessionDisable()) {
            return;
        }

        $illuminateRequest = app(\Illuminate\Http\Request::class); /** @var \Illuminate\Http\Request $illuminateRequest */

        (new \Illuminate\Pipeline\Pipeline(PKPContainer::getInstance()))
            ->send($illuminateRequest)
            ->through([
                \PKP\middleware\PKPEncryptCookies::class,
                \Illuminate\Session\Middleware\StartSession::class,
                \PKP\middleware\PKPAuthenticateSession::class,
            ])
            ->via('handle')
            ->then(function (\Illuminate\Http\Request $request) {
                return app()->get(\Illuminate\Http\Response::class);
            });
    }

    /**
     * Set the user resolving logic for laravel inner use purpose
     */
    public function setUserResolver(): void
    {
        $illuminateRequest = app()->get(\Illuminate\Http\Request::class); /** @var \Illuminate\Http\Request $illuminateRequest */

        $illuminateRequest->setUserResolver(fn () => \APP\core\Application::get()->getRequest()->getUser());
    }

    /**
     * Build a handler request URL into PKPApplication.
     *
     * @param $shortcut the short name of the router that should be used to construct the URL
     * @param $newContext Optional contextual path
     * @param $handler Optional name of the handler to invoke
     * @param $op Optional name of operation to invoke
     * @param $path Optional array of args to pass to handler
     * @param $params Optional set of name => value pairs to pass as user parameters
     * @param $anchor Optional name of anchor to add to URL
     * @param $escape Whether or not to escape ampersands for this URL; default false.
     * @param $urlLocaleForPage Whether or not to override locale for this URL; Use '' to exclude.
     */
    public function url(
        PKPRequest $request,
        string $shortcut,
        ?string $newContext = null,
        ?string $handler = null,
        ?string $op = null,
        ?array $path = null,
        ?array $params = null,
        ?string $anchor = null,
        bool $escape = false,
        ?string $urlLocaleForPage = null
    ): string {
        // Instantiate the requested router
        if (!isset($this->_routerNames[$shortcut])) {
            throw new \Exception('Specified router is not configured!');
        }
        $routerName = $this->_routerNames[$shortcut];
        $router = & $this->_instantiateRouter($routerName, $shortcut);

        return $router->url($request, $newContext, $handler, $op, $path, $params, $anchor, $escape, $urlLocaleForPage);
    }

    //
    // Private helper methods
    //

    /**
     * Instantiate a router
     */
    public function &_instantiateRouter(string $routerName, string $shortcut): PKPRouter
    {
        if (!isset($this->_routerInstances[$shortcut])) {
            // Instantiate the router
            $router = new $routerName();
            if (!$router instanceof \PKP\core\PKPRouter) {
                throw new \Exception('Cannot instantiate requested router. Routers must belong to the core package and be of type "PKPRouter".');
            }
            $router->setApplication($this->_application);
            $router->setDispatcher($this);

            // Save the router instance for later re-use
            $this->_routerInstances[$shortcut] = $router;
        }

        return $this->_routerInstances[$shortcut];
    }

    /**
     * Display the request contents from cache.
     */
    public function _displayCached(PKPRouter $router, PKPRequest $request): bool
    {
        $filename = $router->getCacheFilename($request);
        if (!file_exists($filename)) {
            return false;
        }

        // Allow a caching proxy to work its magic if possible
        $ifModifiedSince = $request->getIfModifiedSince();
        if ($ifModifiedSince !== null && $ifModifiedSince >= filemtime($filename)) {
            header('HTTP/1.1 304 Not Modified');
            exit;
        }

        $data = file_get_contents($filename);
        $i = strpos($data, ':');
        $time = substr($data, 0, $i);
        $contents = substr($data, $i + 1);

        if (time() > $time + Config::getVar('cache', 'web_cache_hours') * 60 * 60) {
            return false;
        }

        header('Content-Type: text/html; charset=utf-8');

        echo $contents;
        return true;
    }

    /**
     * Cache content as a local file.
     */
    public function _cacheContent(string $contents): string
    {
        if ($contents == '') {
            return $contents;
        } // Do not cache empties
        $filename = $this->_router->getCacheFilename($this->_requestCallbackHack);
        $fp = fopen($filename, 'w');
        if ($fp) {
            fwrite($fp, time() . ':' . $contents);
            fclose($fp);
        }
        return $contents;
    }

    /**
     * Handle a 404 error (page not found).
     */
    public static function handle404()
    {
        header('HTTP/1.0 404 Not Found');
        echo "<h1>404 Not Found</h1>\n";
        exit;
    }
}

if (!PKP_STRICT_MODE) {
    class_alias('\PKP\core\Dispatcher', '\Dispatcher');
}

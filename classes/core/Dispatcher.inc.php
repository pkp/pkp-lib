<?php

/**
 * @file classes/core/Dispatcher.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class Dispatcher
 * @ingroup core
 *
 * @brief Class dispatching HTTP requests to handlers.
 */

namespace PKP\core;

use APP\core\Services;
use APP\i18n\AppLocale;
use PKP\config\Config;
use PKP\plugins\HookRegistry;

use PKP\plugins\PluginRegistry;
use PKP\services\PKPSchemaService;

class Dispatcher
{
    /** @var PKPApplication */
    public $_application;

    /** @var array an array of Router implementation class names */
    public $_routerNames = [];

    /** @var array an array of Router instances */
    public $_routerInstances = [];

    /** @var PKPRouter */
    public $_router;

    /** @var PKPRequest Used for a callback hack - NOT GENERALLY SET. */
    public $_requestCallbackHack;

    /**
     * Get the application
     *
     * @return PKPApplication
     */
    public function &getApplication()
    {
        return $this->_application;
    }

    /**
     * Set the application
     *
     * @param PKPApplication $application
     */
    public function setApplication($application)
    {
        $this->_application = $application;
    }

    /**
     * Get the router names
     *
     * @return array an array of Router names
     */
    public function &getRouterNames()
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
    public function addRouterName($routerName, $shortcut)
    {
        assert(is_array($this->_routerNames) && is_string($routerName));
        $this->_routerNames[$shortcut] = $routerName;
    }

    /**
     * Determine the correct router for this request. Then
     * let the router dispatch the request to the appropriate
     * handler method.
     *
     * @param PKPRequest $request
     */
    public function dispatch($request)
    {
        // Make sure that we have at least one router configured
        $routerNames = $this->getRouterNames();
        assert(count($routerNames) > 0);

        // Go through all configured routers by priority
        // and find out whether one supports the incoming request
        foreach ($routerNames as $shortcut => $routerCandidateName) {
            $routerCandidate = & $this->_instantiateRouter($routerCandidateName, $shortcut);

            // Does this router support the current request?
            if ($routerCandidate->supports($request)) {
                // Inject router and dispatcher into request
                $request->setRouter($routerCandidate);
                $request->setDispatcher($this);

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
            fatalError('None of the configured routers supports this request.');
        }

        // Can we serve a cached response?
        if ($router->isCacheable($request)) {
            $this->_requestCallbackHack = & $request;
            if (Config::getVar('cache', 'web_cache')) {
                if ($this->_displayCached($router, $request)) {
                    exit;
                } // Success
                ob_start([$this, '_cacheContent']);
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

        HookRegistry::call('Dispatcher::dispatch', $request);

        // Reload the context after generic plugins have loaded so that changes to
        // the context schema can take place
        $contextSchema = Services::get('schema')->get(PKPSchemaService::SCHEMA_CONTEXT, true);
        $request->getRouter()->getContext($request, 1, true);

        $router->route($request);
    }

    /**
     * Build a handler request URL into PKPApplication.
     *
     * @param PKPRequest $request the request to be routed
     * @param string $shortcut the short name of the router that should be used to construct the URL
     * @param mixed $newContext Optional contextual paths
     * @param string $handler Optional name of the handler to invoke
     * @param string $op Optional name of operation to invoke
     * @param mixed $path Optional string or array of args to pass to handler
     * @param array $params Optional set of name => value pairs to pass as user parameters
     * @param string $anchor Optional name of anchor to add to URL
     * @param bool $escape Whether or not to escape ampersands for this URL; default false.
     *
     * @return string the URL
     */
    public function url(
        $request,
        $shortcut,
        $newContext = null,
        $handler = null,
        $op = null,
        $path = null,
        $params = null,
        $anchor = null,
        $escape = false
    ) {
        // Instantiate the requested router
        assert(isset($this->_routerNames[$shortcut]));
        $routerName = $this->_routerNames[$shortcut];
        $router = & $this->_instantiateRouter($routerName, $shortcut);

        return $router->url($request, $newContext, $handler, $op, $path, $params, $anchor, $escape);
    }

    //
    // Private helper methods
    //

    /**
     * Instantiate a router
     *
     * @param string $routerName
     * @param string $shortcut
     */
    public function &_instantiateRouter($routerName, $shortcut)
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
            $this->_routerInstances[$shortcut] = & $router;
        }

        return $this->_routerInstances[$shortcut];
    }

    /**
     * Display the request contents from cache.
     *
     * @param PKPRouter $router
     */
    public function _displayCached($router, $request)
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

        $fp = fopen($filename, 'r');
        $data = fread($fp, filesize($filename));
        fclose($fp);

        $i = strpos($data, ':');
        $time = substr($data, 0, $i);
        $contents = substr($data, $i + 1);

        if (mktime() > $time + Config::getVar('cache', 'web_cache_hours') * 60 * 60) {
            return false;
        }

        header('Content-Type: text/html; charset=' . Config::getVar('i18n', 'client_charset'));

        echo $contents;
        return true;
    }

    /**
     * Cache content as a local file.
     *
     * @param string $contents
     *
     * @return string
     */
    public function _cacheContent($contents)
    {
        assert($this->_router instanceof \PKP\core\PKPRouter);
        if ($contents == '') {
            return $contents;
        } // Do not cache empties
        $filename = $this->_router->getCacheFilename($this->_requestCallbackHack);
        $fp = fopen($filename, 'w');
        if ($fp) {
            fwrite($fp, mktime() . ':' . $contents);
            fclose($fp);
        }
        return $contents;
    }

    /**
     * Handle a 404 error (page not found).
     */
    public function handle404()
    {
        header('HTTP/1.0 404 Not Found');
        fatalError('404 Not Found');
    }
}

if (!PKP_STRICT_MODE) {
    class_alias('\PKP\core\Dispatcher', '\Dispatcher');
}

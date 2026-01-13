<?php

/**
 * @file classes/core/APIRouter.php
 *
 * Copyright (c) 2014-2024 Simon Fraser University
 * Copyright (c) 2014-2024 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class APIRouter
 *
 * @brief Map HTTP requests to a REST API using the laravel router
 *
 * Requests for [index.php]/api are intercepted for site-level API requests,
 * and requests for [index.php]/{contextPath}/api are intercepted for
 * context-level API requests.
 */

namespace PKP\core;

use APP\core\Application;
use Exception;
use Illuminate\Http\Response;
use PKP\handler\APIHandler;
use PKP\plugins\Hook;

class APIRouter extends PKPRouter
{
    /**
     * List of successfully register plugin api controllers
     */
    protected array $registeredPluginApiControllers = [];

    /**
     * Determines path info parts
     */
    protected function getPathInfoParts(): array
    {
        return explode('/', trim($_SERVER['PATH_INFO'] ?? '', '/'));
    }

    /**
     * Get the API controller file path
     */
    protected function getSourceFilePath(): string
    {
        return sprintf('api/%s/%s/index.php', $this->getVersion(), $this->getEntity());
    }

    /**
     * Determines whether this router can route the given request.
     *
     * @return bool true, if the router supports this request, otherwise false
     */
    public function supports(PKPRequest $request): bool
    {
        $pathInfoParts = $this->getPathInfoParts();

        if (is_null($pathInfoParts)) {
            return false;
        }

        if (count($pathInfoParts) < 2) {
            return false;
        }

        // Context-specific API requests: [index.php]/{contextPath}/api
        if ($pathInfoParts[1] === 'api') {
            return true;
        }

        return false;
    }

    /**
     * Get the API version
     */
    public function getVersion(): string
    {
        $pathInfoParts = $this->getPathInfoParts();

        return Core::cleanFileVar($pathInfoParts[2] ?? '');
    }

    /**
     * Get the entity being requested
     */
    public function getEntity(): string
    {
        $pathInfoParts = $this->getPathInfoParts();

        return Core::cleanFileVar($pathInfoParts[3] ?? '');
    }

    //
    // Implement template methods from PKPRouter
    //
    /**
     * @copydoc \PKP\core\PKPRouter::route()
     * 
     * @hook APIHandler::endpoints::plugin [[$this]]
     */
    public function route(PKPRequest $request): void
    {
        // Give the plugin a chance to register its API controllers
        Hook::run('APIHandler::endpoints::plugin', [$this]);

        // If any plugin API controllers were registered and request path match with it
        // that plugin api controller will be used to handle the request
        if (!empty($this->registeredPluginApiControllers)) {
            $requestPath = $request->getRequestPath();
            foreach ($this->registeredPluginApiControllers as $handlerPath => $apiController) {
                if ($this->matchesPluginHandlerPath($requestPath, $handlerPath)) {
                    $handler = new APIHandler($apiController);
                    $this->setHandler($handler);
                    $handler->runRoutes();
                    return;
                }
            }
        }
        
        $sourceFile = $this->getSourceFilePath();

        if (!file_exists($sourceFile)) {
            response()->json([
                'error' => 'api.404.endpointNotFound',
                'errorMessage' => __('api.404.endpointNotFound'),
            ], Response::HTTP_NOT_FOUND)->send();
            exit;
        }

        $handler = require('./' . $sourceFile); /** @var \PKP\handler\APIHandler|\PKP\core\PKPBaseController $handler */

        if (!$handler instanceof APIHandler && !$handler instanceof PKPBaseController) {
            throw new Exception('Invalid API handler or controller provided');
        }

        if ($handler instanceof PKPBaseController) {
            $handler = new APIHandler($handler); /** @var \PKP\handler\APIHandler $handler */
        }

        $this->setHandler($handler);
        $handler->runRoutes();
    }

    /**
     * Register API controllers from plugin
     *
     * When receiving the instance of this class from the hook, the plugin should 
     * call this method and add in any custom api controllers.
     */
    public function registerPluginApiControllers(array $apiControllers): void
    {
        foreach ($apiControllers as $apiController) {
            if (!$apiController instanceof PKPBaseController) {
                throw new Exception('Invalid API controller given');
            }

            if (array_key_exists($apiController->getHandlerPath(), $this->registeredPluginApiControllers)) {
                throw new Exception("Similar plugin API handler path {$apiController->getHandlerPath()} already registered");
            }

            $this->registeredPluginApiControllers[$apiController->getHandlerPath()] = $apiController;
        }
    }

    /**
     * Get the requested operation
     */
    public function getRequestedOp(PKPRequest $request): string
    {
        if ($routeActionName = PKPBaseController::getRouteActionName()) {
            return $routeActionName;
        }

        return '';
    }

    /**
     * @copydoc PKPRouter::handleAuthorizationFailure()
     */
    public function handleAuthorizationFailure(
        PKPRequest $request,
        string $authorizationMessage,
        array $messageParams = []
    ): void {
        response()->json([
            'error' => $authorizationMessage,
            'errorMessage' => __($authorizationMessage, $messageParams),
        ], Response::HTTP_FORBIDDEN)->send();
        exit;
    }

    /**
     * @copydoc PKPRouter::url()
     */
    public function url(
        PKPRequest $request,
        ?string $newContext = null,
        ?string $endpoint = null,
        ?string $op = null,
        ?array $path = null,
        ?array $params = null,
        ?string $anchor = null,
        bool $escape = false,
        ?string $urlLocaleForPage = null
    ): string {
        // APIHandlers do not understand $op, $path or $anchor. All routing is baked
        // into the $endpoint string. It only accepts a string as the $newContext,
        // since it relies on this when path info is disabled.
        if (!is_null($op) || !is_null($path) || !is_null($anchor) || !is_scalar($newContext)) {
            throw new Exception('APIRouter::url() should not be called with an op, path or anchor. If a new context is passed, the context path must be passed instead of the context object.');
        }

        [$baseUrl, $context] = $this->_urlGetBaseAndContext($request, $newContext);
        $additionalParameters = $this->_urlGetAdditionalParameters($request, $params, $escape);

        return $this->_urlFromParts($baseUrl, [$context, 'api', Application::API_VERSION, $endpoint], $additionalParameters, $anchor, $escape);
    }

    /**
     * Check if request path matches a plugin handler path
     *
     * Uses exact path segment matching to prevent substring collisions.
     * Example: handler path 'report' will NOT match '/api/v1/report-advanced'
     *
     * @param string $requestPath Full request path (e.g., '/index.php/testContext/api/v1/custom-plugin-path/data')
     * @param string $handlerPath Plugin handler path (e.g., 'custom-plugin-path')
     *
     * @return bool True if the request should be handled by this plugin controller
     */
    protected function matchesPluginHandlerPath(string $requestPath, string $handlerPath): bool
    {
        // Extract the path after /api/v{version}/
        // Example: '/index.php/testContext/api/v1/custom-plugin-path/data' -> 'custom-plugin-path/data'
        $pattern = '~/api/v\d+/([^?#]+)~';
        if (!preg_match($pattern, $requestPath, $matches)) {
            return false;
        }

        $actualPath = trim($matches[1], '/');
        $handlerPath = trim($handlerPath, '/');

        // Match if:
        // 1. Exact match: 'custom-plugin-path' === 'custom-plugin-path'
        // 2. Path prefix match: 'custom-plugin-path/data' starts with 'custom-plugin-path/'
        //
        // This prevents false matches:
        // - 'report' will NOT match 'report-advanced'
        // - 'custom' will NOT match 'custom-data'
        return $actualPath === $handlerPath || str_starts_with($actualPath, $handlerPath . '/');
    }
}

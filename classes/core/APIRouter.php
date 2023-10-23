<?php

/**
 * @file classes/core/APIRouter.php
 *
 * Copyright (c) 2023 Simon Fraser University
 * Copyright (c) 2023 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class APIRouter
 *
 * @ingroup core
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
use PKP\core\PKPBaseController;
use PKP\core\PKPRequest;
use PKP\handler\APIHandler;
use PKP\session\SessionManager;

class APIRouter extends PKPRouter
{
    /** 
     * Define if the api call for a plugin implemented endpoint 
     */
    protected bool $_pluginApi = false;

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
        if ($this->_pluginApi) {
            return sprintf(
                '%s/%s/%s/api/%s/%s/index.php',
                $this->getPluginApiPathPrefix(),
                $this->getPluginCategory(),
                $this->getPluginName(),
                $this->getVersion(), 
                $this->getEntity()
            );
        }

        return sprintf('api/%s/%s/index.php', $this->getVersion(), $this->getEntity());
    }

    /**
     * Get the starting api url segment for plugin implemented API endpoint
     * 
     * @example Considering an API endpoint such as 
     *          http://BASE_URL/index.php/CONTEXT_PATH/plugins/PLUGIN_CATEGORY/PLUGIN_NAME/api/VERSION/ENTITY
     *          the plugin api uri prefix is `plugins` which start right after the CONTEXT_PATH
     */
    public function getPluginApiPathPrefix(): string
    {
        return 'plugins';
    }

    /**
     * Define if the target reqeust is for plugin implemented API routes
     */
    public function isPluginApi(): bool
    {
        return $this->_pluginApi;
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
        if ($pathInfoParts[1] == 'api') {
            return true;
        }

        // plugin specific API request [index.php]/{contextPath}/plugins/{category}/{pluginName}/api
        if ($pathInfoParts[1] == $this->getPluginApiPathPrefix() && $pathInfoParts[4] == 'api') {
            $this->_pluginApi = true;
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

        if ($this->isPluginApi()) {
            return Core::cleanFileVar($pathInfoParts[5] ?? '');
        }

        return Core::cleanFileVar($pathInfoParts[2] ?? '');
    }

    /**
     * Get the entity being requested
     */
    public function getEntity(): string
    {
        $pathInfoParts = $this->getPathInfoParts();

        if ($this->isPluginApi()) {
            return Core::cleanFileVar($pathInfoParts[6] ?? '');
        }

        return Core::cleanFileVar($pathInfoParts[3] ?? '');
    }

    /** 
     * Get the plugin name if the api endpoint is implemented at plugin level
     */
    public function getPluginName(): string
    {
        if (!$this->isPluginApi()) {
            return '';
        }

        $pathInfoParts = $this->getPathInfoParts();

        return Core::cleanFileVar($pathInfoParts[3]);
    }

    /** 
     * Get the plugin category if the api endpoint is implemented at plugin level
     */
    public function getPluginCategory(): string
    {
        if (!$this->isPluginApi()) {
            return '';
        }

        $pathInfoParts = $this->getPathInfoParts();

        return Core::cleanFileVar($pathInfoParts[2]);
    }

    //
    // Implement template methods from PKPRouter
    //
    /**
     * @copydoc \PKP\core\PKPRouter::route()
     */
    public function route(PKPRequest $request): void
    {
        $sourceFile = $this->getSourceFilePath();

        if (!file_exists($sourceFile)) {
            response()->json([
                'error' => 'api.404.endpointNotFound',
                'errorMessage' => __('api.404.endpointNotFound'),
            ], Response::HTTP_NOT_FOUND)->send();
            exit;
        }

        if (!SessionManager::isDisabled()) {
            SessionManager::getManager(); // Initialize session
        }

        $handler = require('./' . $sourceFile); /** @var \PKP\handler\APIHandler|\PKP\core\PKPBaseController $handler */

        if ($handler instanceof PKPBaseController) {
            $handler = new APIHandler($handler); /** @var \PKP\handler\APIHandler $handler */
        }

        $this->setHandler($handler);
        $handler->runRoutes();
    }

    /**
     * Get the requested operation
     *
     * @return string
     */
    public function getRequestedOp(PKPRequest $request)
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
        bool $escape = false
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
}

if (!PKP_STRICT_MODE) {
    class_alias('\PKP\core\APIRouter', '\APIRouter');
}

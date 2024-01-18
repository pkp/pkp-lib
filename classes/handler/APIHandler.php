<?php

/**
 * @file lib/pkp/classes/handler/APIHandler.php
 *
 * Copyright (c) 2023 Simon Fraser University
 * Copyright (c) 2023 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class APIHandler
 *
 * @ingroup handler
 *
 * @brief Base request API handler
 */

namespace PKP\handler;

use APP\core\Application;
use Illuminate\Http\Response;
use Illuminate\Routing\Pipeline;
use PKP\core\PKPBaseController;
use PKP\core\PKPContainer;
use PKP\core\PKPRoutingProvider;
use PKP\plugins\Hook;
use PKP\plugins\PluginRegistry;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

class APIHandler extends PKPHandler
{
    /**
     * The endpoint pattern for this handler
     */
    protected ?string $_pathPattern = null;

    /**
     * The unique endpoint string for this handler
     */
    protected ?string $_handlerPath = null;

    /** 
     * Define if all the path building for admin api
     */
    protected bool $_apiForAdmin = false;

    /** 
     * The API routing controller class
     */
    protected PKPBaseController $apiController;

    /**
     * Constructor
     */
    public function __construct(PKPBaseController $controller)
    {
        parent::__construct();

        $router = $controller->getRequest()->getRouter(); /** @var \PKP\core\APIRouter $router */

        Hook::run("APIHandler::endpoints::{$router->getEntity()}", [&$controller, $this]);

        $this->apiController = $controller;

        $this->_pathPattern = $controller->getPathPattern();
        $this->_handlerPath = $controller->getHandlerPath();
        $this->_apiForAdmin = $controller->isSiteWide();

        app('router')->group([
            'prefix' => $this->getEndpointPattern(),
            'middleware' => $controller->getRouteGroupMiddleware(),
        ], $controller->getGroupRoutes(...));
    }

    /**
     * Get the API controller for current running route
     */
    public function getApiController(): PKPBaseController
    {
        return $this->apiController;
    }

    /** 
     * Run the API routes
     */
    public function runRoutes(): mixed
    {   
        if(app('router')->getRoutes()->count() === 0) {
            return response()->json([
                'error' => __('api.400.routeNotDefined')
            ], Response::HTTP_BAD_REQUEST)->send();
        }

        $router = $this->apiController->getRequest()->getRouter(); /** @var \PKP\core\APIRouter $router */
        
        if ($router->isPluginApi()) {
            $contextId = $this->apiController->getRequest()->getContext()?->getId() ?? Application::CONTEXT_SITE;
            
            // load the plugin only for current running context or site if no context available
            $plugin = PluginRegistry::loadPlugin($router->getPluginCategory(), $router->getPluginName(), $contextId);

            // Will only allow api call only from enable plugins
            if (!$plugin->getEnabled($contextId)) {
                return response()->json([
                    'error' => __('api.400.pluginNotEnabled')
                ], Response::HTTP_BAD_REQUEST)->send();
            }
        }

        try {
            $response = (new Pipeline(PKPContainer::getInstance()))
                ->send(app(\Illuminate\Http\Request::class))
                ->through(PKPRoutingProvider::getGlobalRouteMiddleware())
                ->via('handle')
                ->then(function ($request) {
                    return app('router')->dispatch($request);
                });

            if($response instanceof Throwable) {
                throw $response;
            }

            if($response === null) {
                return response()->json([
                    'error' => __('api.417.routeResponseIsNull')
                ], Response::HTTP_EXPECTATION_FAILED)->send();
            }

            if(is_object($response) && method_exists($response, 'send')) {
                return $response->send();
            }

            return response()->json([
                'error' => __('api.422.routeRequestUnableToProcess')
            ], Response::HTTP_UNPROCESSABLE_ENTITY)->send();


        } catch (Throwable $exception) {

            return response()->json(
                [
                    'error' => $exception instanceof NotFoundHttpException
                        ? __('api.404.endpointNotFound')
                        : $exception->getMessage(),
                ],
                $exception instanceof NotFoundHttpException
                ? Response::HTTP_NOT_FOUND
                : (in_array($exception->getCode(), array_keys(Response::$statusTexts))
                    ? $exception->getCode()
                    : Response::HTTP_INTERNAL_SERVER_ERROR)
            )->send();
        }
    }

    /**
     * Get the endpoint pattern for this handler
     *
     * Compiles the URI path pattern from the context, api version and the
     * unique string for the this handler.
     */
    public function getEndpointPattern(): string
    {
        if (isset($this->_pathPattern)) {
            return $this->_pathPattern;
        }
        
        $router = $this->apiController->getRequest()->getRouter(); /** @var \PKP\core\APIRouter $router */

        if ($this->_apiForAdmin) {
            $this->_pathPattern = $router->isPluginApi()
                ? "/index/{$router->getPluginApiPathPrefix()}/{$router->getPluginCategory()}/{$router->getPluginName()}/api/{version}/{$this->_handlerPath}"
                : "/index/api/{version}/{$this->_handlerPath}";

            return $this->_pathPattern;
        }

        $this->_pathPattern = $router->isPluginApi()
            ? "/{contextPath}/{$router->getPluginApiPathPrefix()}/{$router->getPluginCategory()}/{$router->getPluginName()}/api/{version}/{$this->_handlerPath}"
            : "/{contextPath}/api/{version}/{$this->_handlerPath}";

        return $this->_pathPattern;
    }
}

if (!PKP_STRICT_MODE) {
    class_alias('\PKP\handler\APIHandler', '\APIHandler');
}

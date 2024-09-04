<?php

/**
 * @file lib/pkp/classes/handler/APIHandler.php
 *
 * Copyright (c) 2014-2024 Simon Fraser University
 * Copyright (c) 2014-2024 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class APIHandler
 *
 * @brief Base request API handler
 */

namespace PKP\handler;

use Illuminate\Http\Response;
use Illuminate\Routing\Pipeline;
use PKP\core\PKPBaseController;
use PKP\core\PKPContainer;
use PKP\core\PKPRoutingProvider;
use PKP\plugins\Hook;
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
     * List of route details that has been added via hook
     */
    protected array $routesFromHook = [];

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

        $this->registerRoute();

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

        try {
            $response = (new Pipeline(app()))
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

        if ($this->_apiForAdmin) {
            $this->_pathPattern = "/index/api/{version}/{$this->_handlerPath}";
            return $this->_pathPattern;
        }

        $this->_pathPattern = "/{contextPath}/api/{version}/{$this->_handlerPath}";

        return $this->_pathPattern;
    }

    /**
     * Add a new route details pushed from the `APIHandler::endpoints::ENTITY_NAME` hook
     * for the current running API Controller
     * 
     * @param string    $method     The route HTTP request method e.g. `GET`,`POST`,...
     * @param string    $uri        The route uri segment
     * @param callable  $callback   The callback handling to execute actions when route got hit
     * @param string    $name       The name of route
     * @param array     $roles      The route accessable role from `Role::ROLE_ID_*`
     */
    public function addRoute(string $method, string $uri, callable $callback, string $name, array $roles): void
    {
        array_push($this->routesFromHook, [
            'method' => $method,
            'uri' => $uri,
            'callback' => $callback,
            'name' => $name,
            'roles' => $roles
        ]);
    }

    /**
     * Register the routes in the routes collection which was added via hook
     */
    protected function registerRoute(): void
    {
        $router = app('router'); /** @var \Illuminate\Routing\Router $router */

        foreach ($this->routesFromHook as $routeParams) {
            $router
                ->addRoute(
                    $routeParams['method'],
                    $this->getEndpointPattern() . '/' . $routeParams['uri'],
                    $routeParams['callback']
                )
                ->name($routeParams['name'])
                ->middleware($this->apiController->roleAuthorizer($routeParams['roles']));
        }
    }
}

if (!PKP_STRICT_MODE) {
    class_alias('\PKP\handler\APIHandler', '\APIHandler');
}

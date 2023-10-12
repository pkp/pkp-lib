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
    /** @var string The endpoint pattern for this handler */
    protected $_pathPattern;

    /** @var string The unique endpoint string for this handler */
    protected $_handlerPath = null;

    /** @var bool Define if all the path building for admin api */
    protected $_apiForAdmin = false;

    /**
     * Constructor
     */
    public function __construct(PKPBaseController $controller)
    {
        parent::__construct();

        $this->_pathPattern = $controller->getPathPattern();
        $this->_handlerPath = $controller->getHandlerPath();
        $this->_apiForAdmin = $controller->isSiteWide();

        app('router')->group([
            'prefix' => $this->getEndpointPattern(),
            'middleware' => $controller->getRouteGroupMiddleware(),
        ], $controller->getGroupRoutes(...));

        if(app('router')->getRoutes()->count() === 0) {
            return;
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

            return response()->json([
                'error' => $exception instanceof NotFoundHttpException 
                    ? __('api.404.endpointNotFound') 
                    : $exception->getMessage(),
            ], $exception instanceof NotFoundHttpException 
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
     *
     * @return string
     */
    public function getEndpointPattern()
    {
        if (isset($this->_pathPattern)) {
            return $this->_pathPattern;
        }

        if ($this->_apiForAdmin) {
            $this->_pathPattern = '/index/api/{version}/' . $this->_handlerPath;
            return $this->_pathPattern;
        }

        $this->_pathPattern = '/{contextPath}/api/{version}/' . $this->_handlerPath;
        return $this->_pathPattern;
    }
}

if (!PKP_STRICT_MODE) {
    class_alias('\PKP\handler\APIHandler', '\APIHandler');
}

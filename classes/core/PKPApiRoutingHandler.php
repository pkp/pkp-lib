<?php

namespace PKP\core;

use PKP\handler\APIHandler;

class PKPApiRoutingHandler extends APIHandler
{
    public function __construct(PKPBaseController $controller)
    {
        $this->_pathPattern = $controller->getPathPattern();
        $this->_handlerPath = $controller->getHandlerPath();

        app('router')->group([
            'prefix' => $this->getEndpointPattern(),
            'middleware' => $controller->getRouteGroupMiddlewares(),
        ], $controller->getGroupRoutesCallback());

        parent::__construct();
    }
}
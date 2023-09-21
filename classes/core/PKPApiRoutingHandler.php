<?php

/**
 * @file classes/core/PKPApiRoutingHandler.php
 *
 * Copyright (c) 2023 Simon Fraser University
 * Copyright (c) 2023 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PKPApiRoutingHandler
 *
 * @ingroup core
 *
 * @brief   This is only handler class that will work as a bridge between the PKP's Handler
 *          and the Laravel's Controller implementation
 *
 */

namespace PKP\core;

use PKP\handler\APIHandler;

class PKPApiRoutingHandler extends APIHandler
{
    public function __construct(PKPBaseController $controller)
    {
        $this->_pathPattern = $controller->getPathPattern();
        $this->_handlerPath = $controller->getHandlerPath();
        $this->_apiForAdmin = $controller->isSiteWide();

        app('router')->group([
            'prefix' => $this->getEndpointPattern(),
            'middleware' => $controller->getRouteGroupMiddleware(),
        ], $controller->getGroupRoutes(...));

        parent::__construct();
    }
}
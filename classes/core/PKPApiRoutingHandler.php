<?php

/**
 * @file classes/core/
 *
 * Copyright (c) 2014-2023 Simon Fraser University
 * Copyright (c) 2000-2023 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class 
 *
 * @ingroup core
 *
 * @brief 
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
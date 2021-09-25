<?php

/**
 * @file classes/security/authorization/internal/ApiAuthorizationMiddleware.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class ApiAuthorizationMiddleware
 * @ingroup security_authorization
 *
 * @brief Slim middleware which enforces authorization policies
 */

namespace PKP\security\authorization\internal;

use PKP\core\JSONMessage;
use PKP\handler\APIHandler;

class ApiAuthorizationMiddleware
{
    /** @var APIHandler $handler Reference to api handler */
    protected $_handler = null;

    /**
     * Constructor
     *
     */
    public function __construct(APIHandler $handler)
    {
        $this->_handler = $handler;
    }

    /**
     * Handles authorization
     *
     * @param SlimRequest $slimRequest
     *
     * @return bool|string
     */
    protected function _authorize($slimRequest)
    {
        // share SlimRequest with Handler
        $this->_handler->setSlimRequest($slimRequest);
        $request = $this->_handler->getRequest();
        $args = [$slimRequest];
        if (!$slimRequest->getAttribute('route')) {
            return $request->getRouter()->handleAuthorizationFailure($request, 'api.404.endpointNotFound');
        } elseif ($this->_handler->authorize($request, $args, $this->_handler->getRoleAssignments())) {
            $this->_handler->validate($request, $args);
            $this->_handler->initialize($request, $args);
            return true;
        } else {
            AppLocale::requireComponents(LOCALE_COMPONENT_PKP_API, LOCALE_COMPONENT_APP_API);
            $authorizationMessage = $this->_handler->getLastAuthorizationMessage();
            if ($authorizationMessage == '') {
                $authorizationMessage = 'api.403.unauthorized';
            }
            $router = $request->getRouter();
            $result = $router->handleAuthorizationFailure($request, $authorizationMessage);
            switch (1) {
                case is_string($result): return $result;
                case $result instanceof JSONMessage: return $result->getString();
                default:
                    assert(false);
                    return null;
            }
        }
    }

    /**
     * Middleware invokable function
     *
     * @param SlimRequest $request request
     * @param SlimResponse $response response
     * @param callable $next Next middleware
     *
     * @return bool|string|unknown
     */
    public function __invoke($request, $response, $next)
    {
        $result = $this->_authorize($request);
        if ($result !== true) {
            return $result;
        }

        $response = $next($request, $response);
        return $response;
    }
}

if (!PKP_STRICT_MODE) {
    class_alias('\PKP\security\authorization\internal\ApiAuthorizationMiddleware', '\ApiAuthorizationMiddleware');
}

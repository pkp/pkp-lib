<?php

/**
 * @file classes/core/APIRouter.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class APIRouter
 * @ingroup core
 *
 * @brief Map HTTP requests to a REST API using the Slim microframework.
 *
 * Requests for [index.php]/api are intercepted for site-level API requests,
 * and requests for [index.php]/{contextPath}/api are intercepted for
 * context-level API requests.
 */

namespace PKP\core;

use Exception;
use PKP\config\Config;

use PKP\session\SessionManager;

class APIRouter extends PKPRouter
{
    /**
     * Determines path info parts depending of disable_path_info config value
     *
     * @return array|NULL
     */
    protected function getPathInfoParts()
    {
        $pathInfoEnabled = Config::getVar('general', 'disable_path_info') ? false : true;
        if ($pathInfoEnabled && isset($_SERVER['PATH_INFO'])) {
            return explode('/', trim($_SERVER['PATH_INFO'], '/'));
        }

        $request = $this->getApplication()->getRequest();
        $queryString = $request->getQueryString();
        $queryArray = [];
        if (isset($queryString)) {
            parse_str($queryString, $queryArray);
        }

        if (in_array('endpoint', array_keys($queryArray)) && isset($queryArray['journal'])) {
            $endpoint = $queryArray['endpoint'];
            return explode('/', trim($endpoint, '/'));
        }

        return null;
    }

    /**
     * Determines whether this router can route the given request.
     *
     * @param PKPRequest $request
     *
     * @return bool true, if the router supports this request, otherwise false
     */
    public function supports($request)
    {
        $pathInfoParts = $this->getPathInfoParts();

        if (!is_null($pathInfoParts) && count($pathInfoParts) >= 2 && $pathInfoParts[1] == 'api') {
            // Context-specific API requests: [index.php]/{contextPath}/api
            return true;
        }

        return false;
    }

    /**
     * Get the API version
     *
     * @return string
     */
    public function getVersion()
    {
        $pathInfoParts = $this->getPathInfoParts();
        return Core::cleanFileVar($pathInfoParts[2] ?? '');
    }

    /**
     * Get the entity being requested
     *
     * @return string
     */
    public function getEntity()
    {
        $pathInfoParts = $this->getPathInfoParts();
        return Core::cleanFileVar($pathInfoParts[3] ?? '');
    }

    //
    // Implement template methods from PKPRouter
    //
    /**
     * @copydoc PKPRouter::route()
     */
    public function route($request)
    {
        // Ensure slim library is available
        require_once('lib/pkp/lib/vendor/autoload.php');

        $sourceFile = sprintf('api/%s/%s/index.php', $this->getVersion(), $this->getEntity());

        if (!file_exists($sourceFile)) {
            http_response_code('404');
            header('Content-Type: application/json');
            echo json_encode([
                'error' => 'api.404.endpointNotFound',
                'errorMessage' => __('api.404.endpointNotFound'),
            ]);
            exit;
        }

        if (!SessionManager::isDisabled()) {
            // Initialize session
            SessionManager::getManager();
        }

        $handler = require('./' . $sourceFile);
        $this->setHandler($handler);
        $handler->getApp()->run();
    }

    /**
     * Get the requested operation
     *
     * @param PKPRequest $request
     *
     * @return string
     */
    public function getRequestedOp($request)
    {
        $handler = $this->getHandler();
        $container = $handler->getApp()->getContainer();
        $router = $container->get('router');
        $slimRequest = $handler->getSlimRequest();
        $routeInfo = $router->dispatch($slimRequest);
        if (isset($routeInfo[1])) {
            $route = $router->lookupRoute($routeInfo[1]);
            $callable = $route->getCallable();
            if (is_array($callable) && count($callable) == 2) {
                return $callable[1];
            }
        }
        return '';
    }

    /**
     * @copydoc PKPRouter::handleAuthorizationFailure()
     */
    public function handleAuthorizationFailure(
        $request,
        $authorizationMessage,
        array $messageParams = []
    ) {
        http_response_code('403');
        header('Content-Type: application/json');
        echo json_encode([
            'error' => $authorizationMessage,
            'errorMessage' => __($authorizationMessage, $messageParams),
        ]);
        exit;
    }

    /**
     * @copydoc PKPRouter::url()
     *
     * @param null|mixed $newContext
     * @param null|mixed $endpoint
     * @param null|mixed $op
     * @param null|mixed $path
     * @param null|mixed $params
     * @param null|mixed $anchor
     */
    public function url(
        $request,
        $newContext = null,
        $endpoint = null,
        $op = null,
        $path = null,
        $params = null,
        $anchor = null,
        $escape = false
    ) {

        // APIHandlers do not understand $op, $path or $anchor. All routing is baked
        // into the $endpoint string. It only accepts a string as the $newContext,
        // since it relies on this when path info is disabled.
        if (!is_null($op) || !is_null($path) || !is_null($anchor) || !is_scalar($newContext)) {
            throw new Exception('APIRouter::url() should not be called with an op, path or anchor. If a new context is passed, the context path must be passed instead of the context object.');
        }

        //
        // Base URL and Context
        //
        $baseUrlAndContext = $this->_urlGetBaseAndContext($request, $this->_urlCanonicalizeNewContext($newContext));
        $baseUrl = array_shift($baseUrlAndContext);
        $context = $baseUrlAndContext;

        //
        // Additional query parameters
        //
        $additionalParameters = $this->_urlGetAdditionalParameters($request, $params, $escape);

        //
        // Assemble URL
        //
        if ($request->isPathInfoEnabled()) {
            // If path info is enabled, everything but params goes into the path
            $pathInfoArray = array_merge(
                $context,
                ['api',	API_VERSION, $endpoint]
            );
            $queryParametersArray = $additionalParameters;
        } else {
            // If path info is disabled, the context and endpoint must be passed as
            // query params, and the context must be concatenated into the endpoint
            $pathInfoArray = [];
            $queryParametersArray = array_merge(
                $context,
                [sprintf('endpoint=/%s/api/%s/%s', $newContext, API_VERSION, $endpoint)],
                $additionalParameters
            );
        }

        return $this->_urlFromParts($baseUrl, $pathInfoArray, $queryParametersArray, $anchor, $escape);
    }
}

if (!PKP_STRICT_MODE) {
    class_alias('\PKP\core\APIRouter', '\APIRouter');
}

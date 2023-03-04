<?php

/**
 * @file classes/core/PKPRouter.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PKPRouter
 *
 * @see PKPPageRouter
 * @see PKPComponentRouter
 * @ingroup core
 *
 * @brief Basic router class that has functionality common to all routers.
 *
 * NB: All handlers provide the common basic workflow. The router
 * calls the following methods in the given order.
 * 1) constructor:
 *       Handlers should establish a mapping of remote
 *       operations to roles that may access them. They do
 *       so by calling PKPHandler::addRoleAssignment().
 * 2) authorize():
 *       Authorizes the request, among other things based
 *       on the result of the role assignment created
 *       during object instantiation. If authorization fails
 *       then die with a fatal error or execute the "call-
 *       on-deny" advice if one has been defined in the
 *       authorization policy that denied access.
 * 3) validate():
 *       Let the handler execute non-fatal data integrity
 *       checks (FIXME: currently only for component handlers).
 *       Please make sure that data integrity checks that can
 *       lead to denial of access are being executed in the
 *       authorize() step via authorization policies and not
 *       here.
 * 4) initialize():
 *       Let the handler initialize its internal state based
 *       on authorized and valid data. Authorization and integrity
 *       checks should be kept out of here to get a clear separation
 *       of concerns.
 * 5) execution:
 *       Executes the requested handler operation. The mapping
 *       of requests to operations depends on the router
 *       implementation (see the class doc of specific router
 *       implementations for more details).
 * 6) client response:
 *       Handlers should return a string value that will then be
 *       returned to the client as a response. Handler operations
 *       should not output the response directly to the client so
 *       that we can run filter operations on the output if required.
 *       Outputting text from handler operations to the client
 *       is possible but deprecated.
 */

namespace PKP\core;

use APP\core\Application;
use PKP\config\Config;
use PKP\context\Context;
use PKP\db\DAORegistry;
use PKP\handler\PKPHandler;
use PKP\plugins\Hook;

abstract class PKPRouter
{
    //
    // Internal state cache variables
    // NB: Please do not access directly but
    // only via their respective getters/setters
    //
    protected Application $_application;
    protected Dispatcher $_dispatcher;
    protected ?string $_contextPath = null;
    public ?Context $_context = null;
    public ?PKPHandler $_handler = null;

    /**
     * Constructor
     */
    public function __construct()
    {
    }

    /**
     * get the application
     */
    public function getApplication(): Application
    {
        return $this->_application;
    }

    /**
     * set the application
     */
    public function setApplication(Application $application)
    {
        $this->_application = $application;
    }

    /**
     * get the dispatcher
     */
    public function getDispatcher(): \PKP\core\Dispatcher
    {
        return $this->_dispatcher;
    }

    /**
     * set the dispatcher
     */
    public function setDispatcher(\PKP\core\Dispatcher $dispatcher)
    {
        $this->_dispatcher = $dispatcher;
    }

    /**
     * Set the handler object for later retrieval.
     */
    public function setHandler(PKPHandler $handler)
    {
        $this->_handler = $handler;
    }

    /**
     * Get the handler object.
     */
    public function getHandler(): ?PKPHandler
    {
        return $this->_handler;
    }

    /**
     * Determines whether this router can route the given request.
     */
    public function supports(PKPRequest $request): bool
    {
        // Default implementation returns always true
        return true;
    }

    /**
     * Determine whether or not this request is cacheable
     */
    public function isCacheable(PKPRequest $request): bool
    {
        // Default implementation returns always false
        return false;
    }

    /**
     * A generic method to return a context path (e.g. a Press or a Journal path)
     */
    public function getRequestedContextPath(PKPRequest $request): string
    {
        // Determine the context path
        if ($this->_contextPath === null) {
            $this->_contextPath = Core::getContextPath($_SERVER['PATH_INFO'] ?? '');

            Hook::call('Router::getRequestedContextPath', [&$this->_contextPath]);
        }
        return $this->_contextPath;
    }

    /**
     * A Generic call to a context defining object (e.g. a Journal, Press, or Server)
     *
     * @param PKPRequest $request the request to be routed
     * @param int $requestedContextLevel (optional) the desired context level. DEPRECATED: Must be 1.
     * @param bool $forceReload (optional) Reset a context even if it's already been loaded
     *
     * @return object
     */
    public function &getContext($request, $requestedContextLevel = 1, $forceReload = false)
    {
        if ($requestedContextLevel !== 1) {
            throw new Exception('Only context level 1 is supported.');
        }

        if ($forceReload || !isset($this->_context)) {
            // Retrieve the requested context path (this validates the path)
            $path = $this->getRequestedContextPath($request);

            // Resolve the path to the context
            if ($path == 'index') {
                $this->_context = null;
            } else {
                // Get the context name (this validates the context name)
                $requestedContextName = $this->_contextLevelToContextName(1);

                // Get the DAO for the requested context.
                $contextClass = ucfirst($requestedContextName);
                $daoName = $contextClass . 'DAO';
                $daoInstance = DAORegistry::getDAO($daoName);

                // Retrieve the context from the DAO (by path)
                $daoMethod = 'getByPath';
                assert(method_exists($daoInstance, $daoMethod));
                $this->_context = $daoInstance->$daoMethod($path);
            }
        }

        return $this->_context;
    }

    /**
     * Get the object that represents the desired context (e.g. Conference or Press)
     *
     * @param PKPRequest $request the request to be routed
     * @param string $requestedContextName page context
     *
     * @return object
     */
    public function getContextByName($request, $requestedContextName)
    {
        // Retrieve the requested context by level
        return $this->getContext($request);
    }

    /**
     * Get the URL to the index script.
     *
     * @param PKPRequest $request the request to be routed
     *
     * @return string
     */
    public function getIndexUrl($request)
    {
        if (!isset($this->_indexUrl)) {
            if ($request->isRestfulUrlsEnabled()) {
                $this->_indexUrl = $request->getBaseUrl();
            } else {
                $this->_indexUrl = $request->getBaseUrl() . '/index.php';
            }
            Hook::call('Router::getIndexUrl', [&$this->_indexUrl]);
        }

        return $this->_indexUrl;
    }


    //
    // Protected template methods to be implemented by sub-classes.
    //
    /**
     * Determine the filename to use for a local cache file.
     *
     * @param PKPRequest $request
     *
     * @return string
     */
    public function getCacheFilename($request)
    {
        throw new Exception('Unimplemented');
    }

    /**
     * Routes a given request to a handler operation
     *
     * @param PKPRequest $request
     */
    abstract public function route($request);

    /**
     * Build a handler request URL into PKPApplication.
     *
     * @param PKPRequest $request the request to be routed
     * @param mixed $newContext Optional contextual paths
     * @param string $handler Optional name of the handler to invoke
     * @param string $op Optional name of operation to invoke
     * @param mixed $path Optional string or array of args to pass to handler
     * @param array $params Optional set of name => value pairs to pass as user parameters
     * @param string $anchor Optional name of anchor to add to URL
     * @param bool $escape Whether or not to escape ampersands, square brackets, etc. for this URL; default false.
     *
     * @return string the URL
     */
    abstract public function url(
        PKPRequest $request,
        ?string $newContext = null,
        $handler = null,
        $op = null,
        $path = null,
        $params = null,
        $anchor = null,
        $escape = false
    );

    /**
     * Handle an authorization failure.
     *
     * @param Request $request
     * @param string $authorizationMessage a translation key with the authorization
     *  failure message.
     */
    abstract public function handleAuthorizationFailure(
        $request,
        $authorizationMessage,
        array $messageParams = []
    );


    //
    // Private helper methods
    //
    /**
     * This is the method that implements the basic
     * life-cycle of a handler request:
     * 1) authorization
     * 2) validation
     * 3) initialization
     * 4) execution
     * 5) client response
     *
     * @param callable $serviceEndpoint the handler operation
     * @param PKPRequest $request
     * @param array $args
     * @param bool $validate whether or not to execute the
     *  validation step.
     */
    public function _authorizeInitializeAndCallRequest(&$serviceEndpoint, $request, &$args, $validate = true)
    {
        $dispatcher = $this->getDispatcher();

        // It's conceivable that a call has gotten this far without
        // actually being callable, e.g. a component has been named
        // that does not exist and that no plugin has registered.
        if (!is_callable($serviceEndpoint)) {
            $dispatcher->handle404();
        }

        // Pass the dispatcher to the handler.
        $serviceEndpoint[0]->setDispatcher($dispatcher);

        // Authorize the request.
        $roleAssignments = $serviceEndpoint[0]->getRoleAssignments();
        assert(is_array($roleAssignments));
        if ($serviceEndpoint[0]->authorize($request, $args, $roleAssignments)) {
            // Execute class-wide data integrity checks.
            if ($validate) {
                $serviceEndpoint[0]->validate($request, $args);
            }

            // Let the handler initialize itself.
            $serviceEndpoint[0]->initialize($request, $args);

            // Call the service endpoint.
            $result = call_user_func($serviceEndpoint, $args, $request);
        } else {
            // Authorization failed - try to retrieve a user
            // message.
            $authorizationMessage = $serviceEndpoint[0]->getLastAuthorizationMessage();

            // Set a generic authorization message if no
            // specific authorization message was set.
            if ($authorizationMessage == '') {
                $authorizationMessage = 'user.authorization.accessDenied';
            }

            // Handle the authorization failure.
            $result = $this->handleAuthorizationFailure($request, $authorizationMessage);
        }

        // Return the result of the operation to the client.
        if (is_string($result)) {
            echo $result;
        } elseif ($result instanceof \PKP\core\JSONMessage) {
            header('Content-Type: application/json');
            echo $result->getString();
        }
    }

    /**
     * Build the base URL and add the context part of the URL.
     *
     * The new URL will be based on the current request's context
     * if no new context is given.
     *
     * The base URL for a given primary context can be overridden
     * in the config file using the 'base_url[context]' syntax in the
     * config file's 'general' section.
     *
     * @param PKPRequest $request the request to be routed
     * @param mixed $newContext (optional) context that differs from
     *  the current request's context
     *
     * @return array An array consisting of the base url as the first
     *  entry and the context as the remaining entries.
     */
    public function _urlGetBaseAndContext($request, ?string $newContext = null)
    {
        // Determine URL context
        $contextName = Application::get()->getContextName();

        if (isset($newContext)) {
            // A new context has been set so use it.
            $contextValue = rawurlencode($newContext);
        } else {
            // No new context has been set so determine
            // the current request's context
            $contextObject = $this->getContextByName($request, $contextName);
            $contextValue = $contextObject?->getPath() ?? 'index';
        }

        // Check whether the base URL is overridden.
        if ($overriddenBaseUrl = Config::getVar('general', "base_url[{$contextValue}]")) {
            return [$overriddenBaseUrl, []];
        }
        return [$this->getIndexUrl($request), [$contextValue]];
    }

    /**
     * Build the additional parameters part of the URL.
     *
     * @param PKPRequest $request the request to be routed
     * @param array $params (optional) the parameter list to be
     *  transformed to a url part.
     * @param bool $escape (optional) Whether or not to escape structural elements
     *
     * @return array the encoded parameters or an empty array
     *  if no parameters were given.
     */
    public function _urlGetAdditionalParameters($request, $params = null, $escape = true)
    {
        $additionalParameters = [];
        if (!empty($params)) {
            assert(is_array($params));
            foreach ($params as $key => $value) {
                if (is_array($value)) {
                    foreach ($value as $element) {
                        $additionalParameters[] = $key . ($escape ? '%5B%5D=' : '[]=') . rawurlencode($element);
                    }
                } else {
                    $additionalParameters[] = $key . '=' . rawurlencode($value ?? '');
                }
            }
        }

        return $additionalParameters;
    }

    /**
     * Creates a valid URL from parts.
     *
     * @param string $baseUrl the protocol, domain and initial path/parameters, no anchors allowed here
     * @param array $pathInfoArray strings to be concatenated as path info
     * @param array $queryParametersArray strings to be concatenated as query string
     * @param ?string $anchor an additional anchor
     * @param bool $escape whether to escape ampersands
     *
     * @return string the URL
     */
    public function _urlFromParts(string $baseUrl, array $pathInfoArray = [], array $queryParametersArray = [], ?string $anchor = '', bool $escape = false)
    {
        // Parse the base url
        $baseUrlParts = parse_url($baseUrl);
        assert(isset($baseUrlParts['host']) && !isset($baseUrlParts['fragment']));

        // Reconstruct the base url without path and query
        $baseUrl = (isset($baseUrlParts['scheme']) ? $baseUrlParts['scheme'] . ':' : '') . '//';
        if (isset($baseUrlParts['user'])) {
            $baseUrl .= $baseUrlParts['user'];
            if (isset($baseUrlParts['pass'])) {
                $baseUrl .= ':' . $baseUrlParts['pass'];
            }
            $baseUrl .= '@';
        }
        $baseUrl .= $baseUrlParts['host'];
        if (isset($baseUrlParts['port'])) {
            $baseUrl .= ':' . $baseUrlParts['port'];
        }
        $baseUrl .= '/';

        // Add path info from the base URL to the path info array (if any).
        if (isset($baseUrlParts['path'])) {
            $pathInfoArray = array_merge(explode('/', trim($baseUrlParts['path'], '/')), $pathInfoArray);
        }

        // Add query parameters from the base URL to the query parameter array (if any).
        if (isset($baseUrlParts['query'])) {
            $queryParametersArray = array_merge(explode('&', $baseUrlParts['query']), $queryParametersArray);
        }

        // Expand path info
        $pathInfo = implode('/', $pathInfoArray);

        // Expand query parameters
        $amp = $escape ? '&amp;' : '&';
        $queryParameters = implode($amp, $queryParametersArray);
        $queryParameters = empty($queryParameters) ? '' : '?' . $queryParameters;

        // Assemble and return the final URL
        return $baseUrl . $pathInfo . $queryParameters . $anchor;
    }

    /**
     * Convert a context level to its corresponding context name.
     *
     * @param int $contextLevel DEPRECATED: Must be 1.
     *
     * @return string context name
     */
    public function _contextLevelToContextName($contextLevel = 1)
    {
        if ($contextLevel !== 1) {
            throw new Exception('Only context level 1 is supported.');
        }
        return Application::get()->getContextName();
    }
}

if (!PKP_STRICT_MODE) {
    class_alias('\PKP\core\PKPRouter', '\PKPRouter');
}

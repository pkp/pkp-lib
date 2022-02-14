<?php

/**
 * @file classes/core/PKPRouter.inc.php
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

use PKP\config\Config;
use PKP\db\DAORegistry;
use PKP\plugins\HookRegistry;

class PKPRouter
{
    //
    // Internal state cache variables
    // NB: Please do not access directly but
    // only via their respective getters/setters
    //
    /** @var PKPApplication */
    public $_application;
    /** @var Dispatcher */
    public $_dispatcher;
    /** @var int context depth */
    public $_contextDepth;
    /** @var int context list */
    public $_contextList;
    /** @var int context list with keys and values flipped */
    public $_flippedContextList;
    /** @var int context paths */
    public $_contextPaths = [];
    /** @var int contexts */
    public $_contexts = [];
    /** @var PKPHandler Handler class */
    public $_handler;

    /**
     * Constructor
     */
    public function __construct()
    {
    }

    /**
     * get the application
     *
     * @return PKPApplication
     */
    public function &getApplication()
    {
        assert($this->_application instanceof \PKP\core\PKPApplication);
        return $this->_application;
    }

    /**
     * set the application
     *
     * @param PKPApplication $application
     */
    public function setApplication($application)
    {
        $this->_application = $application;

        // Retrieve context depth and list
        $this->_contextDepth = $application->getContextDepth();
        $this->_contextList = $application->getContextList();
        $this->_flippedContextList = array_flip($this->_contextList);
    }

    /**
     * get the dispatcher
     *
     * @return Dispatcher
     */
    public function &getDispatcher()
    {
        assert($this->_dispatcher instanceof \PKP\core\Dispatcher);
        return $this->_dispatcher;
    }

    /**
     * set the dispatcher
     *
     * @param PKPDispatcher $dispatcher
     */
    public function setDispatcher($dispatcher)
    {
        $this->_dispatcher = $dispatcher;
    }

    /**
     * Set the handler object for later retrieval.
     *
     * @param PKPHandler $handler
     */
    public function setHandler($handler)
    {
        $this->_handler = $handler;
    }

    /**
     * Get the handler object.
     *
     * @return PKPHandler
     */
    public function getHandler()
    {
        return $this->_handler;
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
        // Default implementation returns always true
        return true;
    }

    /**
     * Determine whether or not this request is cacheable
     *
     * @param PKPRequest $request
     *
     * @return bool
     */
    public function isCacheable($request)
    {
        // Default implementation returns always false
        return false;
    }

    /**
     * A generic method to return an array of context paths (e.g. a Press or a Conference/SchedConf paths)
     *
     * @param PKPRequest $request the request to be routed
     *
     * @return array of string (each element the path to one context element)
     */
    public function getRequestedContextPaths($request)
    {
        // Handle context depth 0
        if (!$this->_contextDepth) {
            return [];
        }

        // Validate context parameters
        assert(isset($this->_contextDepth) && isset($this->_contextList));

        $isPathInfoEnabled = $request->isPathInfoEnabled();
        $userVars = [];
        $url = null;

        // Determine the context path
        if (empty($this->_contextPaths)) {
            if ($isPathInfoEnabled) {
                // Retrieve url from the path info
                if (isset($_SERVER['PATH_INFO'])) {
                    $url = $_SERVER['PATH_INFO'];
                }
            } else {
                $url = $request->getCompleteUrl();
                $userVars = $request->getUserVars();
            }

            $this->_contextPaths = Core::getContextPaths(
                $url,
                $isPathInfoEnabled,
                $this->_contextList,
                $this->_contextDepth,
                $userVars
            );

            HookRegistry::call('Router::getRequestedContextPaths', [&$this->_contextPaths]);
        }

        return $this->_contextPaths;
    }

    /**
     * A generic method to return a single context path (e.g. a Press or a SchedConf path)
     *
     * @param PKPRequest $request the request to be routed
     * @param int $requestedContextLevel (optional) the context level to return
     *
     * @return string
     */
    public function getRequestedContextPath($request, $requestedContextLevel = 1)
    {
        // Handle context depth 0
        if (!$this->_contextDepth) {
            return null;
        }

        // Validate the context level
        assert(isset($this->_contextDepth) && isset($this->_contextList));
        assert($requestedContextLevel > 0 && $requestedContextLevel <= $this->_contextDepth);

        // Return the full context, then retrieve the requested context path
        $contextPaths = $this->getRequestedContextPaths($request);
        assert(isset($this->_contextPaths[$requestedContextLevel - 1]));
        return $this->_contextPaths[$requestedContextLevel - 1];
    }

    /**
     * A Generic call to a context defining object (e.g. a Press, a Conference, or a SchedConf)
     *
     * @param PKPRequest $request the request to be routed
     * @param int $requestedContextLevel (optional) the desired context level
     * @param bool $forceReload (optional) Reset a context even if it's already been loaded
     *
     * @return object
     */
    public function &getContext($request, $requestedContextLevel = 1, $forceReload = false)
    {
        // Handle context depth 0
        if (!$this->_contextDepth) {
            $nullVar = null;
            return $nullVar;
        }

        if ($forceReload || !isset($this->_contexts[$requestedContextLevel])) {
            // Retrieve the requested context path (this validates the context level and the path)
            $path = $this->getRequestedContextPath($request, $requestedContextLevel);

            // Resolve the path to the context
            if ($path == 'index') {
                $this->_contexts[$requestedContextLevel] = null;
            } else {
                // Get the context name (this validates the context name)
                $requestedContextName = $this->_contextLevelToContextName($requestedContextLevel);

                // Get the DAO for the requested context.
                $contextClass = ucfirst($requestedContextName);
                $daoName = $contextClass . 'DAO';
                $daoInstance = DAORegistry::getDAO($daoName);

                // Retrieve the context from the DAO (by path)
                $daoMethod = 'getByPath';
                assert(method_exists($daoInstance, $daoMethod));
                $this->_contexts[$requestedContextLevel] = $daoInstance->$daoMethod($path);
            }
        }

        return $this->_contexts[$requestedContextLevel];
    }

    /**
     * Get the object that represents the desired context (e.g. Conference or Press)
     *
     * @param PKPRequest $request the request to be routed
     * @param string $requestedContextName page context
     *
     * @return object
     */
    public function &getContextByName($request, $requestedContextName)
    {
        // Handle context depth 0
        if (!$this->_contextDepth) {
            $nullVar = null;
            return $nullVar;
        }

        // Convert the context name to a context level (this validates the context name)
        $requestedContextLevel = $this->_contextNameToContextLevel($requestedContextName);

        // Retrieve the requested context by level
        $returner = $this->getContext($request, $requestedContextLevel);
        return $returner;
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
            HookRegistry::call('Router::getIndexUrl', [&$this->_indexUrl]);
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
        // must be implemented by sub-classes
        assert(false);
    }

    /**
     * Routes a given request to a handler operation
     *
     * @param PKPRequest $request
     */
    public function route($request)
    {
        // Must be implemented by sub-classes.
        assert(false);
    }

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
    public function url(
        $request,
        $newContext = null,
        $handler = null,
        $op = null,
        $path = null,
        $params = null,
        $anchor = null,
        $escape = false
    ) {
        // Must be implemented by sub-classes.
        assert(false);
    }

    /**
     * Handle an authorization failure.
     *
     * @param Request $request
     * @param string $authorizationMessage a translation key with the authorization
     *  failure message.
     */
    public function handleAuthorizationFailure(
        $request,
        $authorizationMessage,
        array $messageParams = []
    ) {
        // Must be implemented by sub-classes.
        assert(false);
    }


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
     * Canonicalizes the new context.
     *
     * A new context can be given as a scalar. In this case only the
     * first context will be replaced. If the context depth of the
     * current application is higher than one than the context can also
     * be given as an array if more than the first context should
     * be replaced. We therefore canonicalize the new context to an array.
     *
     * When all entries are of the form 'contextName' => null or if
     * $newContext == null then we'll return an empty array.
     *
     * @param array $newContext the raw context array
     *
     * @return array the canonicalized context array
     */
    public function _urlCanonicalizeNewContext($newContext)
    {
        // Create an empty array in case no new context was given.
        if (is_null($newContext)) {
            $newContext = [];
        }

        // If we got the new context as a scalar then transform
        // it into an array.
        if (is_scalar($newContext)) {
            $newContext = [$newContext];
        }

        // Check whether any new context has been provided.
        // If not then return an empty array.
        $newContextProvided = false;
        foreach ($newContext as $contextElement) {
            if (isset($contextElement)) {
                $newContextProvided = true;
            }
        }
        if (!$newContextProvided) {
            $newContext = [];
        }

        return $newContext;
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
    public function _urlGetBaseAndContext($request, $newContext = [])
    {
        $pathInfoEnabled = $request->isPathInfoEnabled();

        // Retrieve the context list.
        $contextList = $this->_contextList;

        $baseUrlConfigSuffix = '';
        $overriddenContextCount = 0;

        // Determine URL context
        $context = [];
        foreach ($contextList as $contextKey => $contextName) {
            if ($pathInfoEnabled) {
                $contextParameter = '';
            } else {
                $contextParameter = $contextName . '=';
            }

            $newContextValue = array_shift($newContext);
            if (isset($newContextValue)) {
                // A new context has been set so use it.
                $contextValue = rawurlencode($newContextValue);
            } else {
                // No new context has been set so determine
                // the current request's context
                $contextObject = $this->getContextByName($request, $contextName);
                if ($contextObject) {
                    $contextValue = $contextObject->getPath();
                } else {
                    $contextValue = 'index';
                }
            }

            // Check whether the base URL is overridden.
            $baseUrlConfigSuffix .= "[${contextValue}]";
            $newOverriddenBaseUrl = Config::getVar('general', 'base_url' . $baseUrlConfigSuffix);
            if (!empty($newOverriddenBaseUrl)) {
                $overriddenContextCount = $contextKey + 1;
                $overriddenBaseUrl = $newOverriddenBaseUrl;
            }

            $context[] = $contextParameter . $contextValue;
        }

        // Generate the base url
        if (!empty($overriddenBaseUrl)) {
            $baseUrl = $overriddenBaseUrl;

            // Throw the overridden context(s) away
            while ($overriddenContextCount > 0) {
                array_shift($context);
                $overriddenContextCount--;
            }
        } else {
            $baseUrl = $this->getIndexUrl($request);
        }

        // Join base URL and context and return the result
        $baseUrlAndContext = array_merge([$baseUrl], $context);
        return $baseUrlAndContext;
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
     * @param string $anchor an additional anchor
     * @param bool $escape whether to escape ampersands
     *
     * @return string the URL
     */
    public function _urlFromParts($baseUrl, $pathInfoArray = [], $queryParametersArray = [], $anchor = '', $escape = false)
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
     * @param int $contextLevel
     *
     * @return string context name
     */
    public function _contextLevelToContextName($contextLevel)
    {
        assert(isset($this->_contextList[$contextLevel - 1]));
        return $this->_contextList[$contextLevel - 1];
    }

    /**
     * Convert a context name to its corresponding context level.
     *
     * @param string $contextName
     *
     * @return int context level
     */
    public function _contextNameToContextLevel($contextName)
    {
        assert(isset($this->_flippedContextList[$contextName]));
        return $this->_flippedContextList[$contextName] + 1;
    }
}

if (!PKP_STRICT_MODE) {
    class_alias('\PKP\core\PKPRouter', '\PKPRouter');
}

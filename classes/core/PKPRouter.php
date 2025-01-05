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
 *
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
use Exception;
use PKP\config\Config;
use PKP\context\Context;
use PKP\context\ContextDAO;
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
    protected ?Dispatcher $_dispatcher = null;
    protected ?string $_contextPath = null;
    public ?Context $_context = null;
    public ?PKPHandler $_handler = null;
    public string $_indexUrl;

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
     * get the current context
     */
    public function getCurrentContext(): ?Context
    {
        return $this->_context;
    }

    /**
     * get the dispatcher
     */
    public function getDispatcher(): ?Dispatcher
    {
        return $this->_dispatcher;
    }

    /**
     * set the dispatcher
     */
    public function setDispatcher(Dispatcher $dispatcher)
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
     *
     * @hook Router::getRequestedContextPath [[&$this->_contextPath]]
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
     * @param $forceReload (optional) Reset a context even if it's already been loaded
     *
     * @return Context
     */
    public function getContext(PKPRequest $request, $forceReload = false)
    {
        if ($forceReload || !isset($this->_context)) {
            // Retrieve the requested context path (this validates the path)
            $path = $this->getRequestedContextPath($request);

            // Resolve the path to the context
            /** @deprecated 3.5 The usage of "_" as a site context has been deprecated */
            if (in_array($path, [Application::SITE_CONTEXT_PATH, '', '_'])) {
                $this->_context = null;
            } else {
                // FIXME: Can't just use Application::get()->getContextDAO() without test breakage
                /** @var ContextDAO */
                $contextDao = DAORegistry::getDAO(ucfirst(Application::get()->getContextName()) . 'DAO');

                // Retrieve the context from the DAO (by path)
                $this->_context = $contextDao->getByPath($path);

                // If the context couldn't be retrieved, assume site context so that a 404 error can be provided by the site.
                if (!$this->_context) {
                    $this->_context = null;
                }
            }
        }

        return $this->_context;
    }

    /**
     * Get the URL to the index script.
     *
     * @hook Router::getIndexUrl [[&$this->_indexUrl]]
     */
    public function getIndexUrl(PKPRequest $request): string
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
     */
    public function getCacheFilename(PKPRequest $request): string
    {
        throw new Exception('Unimplemented');
    }

    /**
     * Routes a given request to a handler operation
     */
    abstract public function route(PKPRequest $request);

    /**
     * Build a handler request URL into PKPApplication.
     *
     * @param PKPRequest $request the request to be routed
     * @param $newContext Optional contextual paths
     * @param $handler Optional name of the handler to invoke
     * @param $op Optional name of operation to invoke
     * @param $path Optional array of args to pass to handler
     * @param $params Optional set of name => value pairs to pass as user parameters
     * @param $anchor Optional name of anchor to add to URL
     * @param $escape Whether or not to escape ampersands, square brackets, etc. for this URL; default false.
     *
     * @return string the URL
     */
    abstract public function url(
        PKPRequest $request,
        ?string $newContext = null,
        ?string $handler = null,
        ?string $op = null,
        ?array $path = null,
        ?array $params = null,
        ?string $anchor = null,
        bool $escape = false
    ): string;

    /**
     * Handle an authorization failure.
     *
     * @param $authorizationMessage a translation key with the authorization failure message.
     */
    abstract public function handleAuthorizationFailure(
        PKPRequest $request,
        string $authorizationMessage,
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
     * @param $validate whether or not to execute the validation step.
     */
    public function _authorizeInitializeAndCallRequest(callable $serviceEndpoint, PKPRequest $request, array $args, bool $validate = true): void
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

        // sent out the cookie as header
        Application::get()->getRequest()->getSessionGuard()->sendCookies();

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
     * @param $newContext Context path (defaulting to the current request's context)
     *
     * @return array An array consisting of the base url and context.
     */
    public function _urlGetBaseAndContext(PKPRequest $request, ?string $newContext = null): array
    {
        if (isset($newContext)) {
            // A new context has been set so use it.
            $contextValue = rawurlencode($newContext);
        } else {
            // No new context has been set so determine
            // the current request's context
            $contextObject = $this->getContext($request);
            $contextValue = $contextObject?->getPath() ?? Application::SITE_CONTEXT_PATH;
        }

        // Check whether the base URL is overridden.
        if ($overriddenBaseUrl = Config::getVar('general', "base_url[{$contextValue}]")) {
            return [$overriddenBaseUrl, null];
        }
        return [$this->getIndexUrl($request), $contextValue];
    }

    /**
     * Build the additional parameters part of the URL.
     *
     * @param $params The parameter list to be transformed to a url part.
     * @param $escape Whether or not to escape structural elements
     *
     * @return array The encoded parameters or an empty array if no parameters were given.
     */
    public function _urlGetAdditionalParameters(PKPRequest $request, ?array $params = null, bool $escape = true): array
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
     * @param $baseUrl the protocol, domain and initial path/parameters, no anchors allowed here
     * @param $pathInfoArray strings to be concatenated as path info
     * @param $queryParametersArray strings to be concatenated as query string
     * @param $anchor an additional anchor
     * @param $escape whether to escape ampersands
     */
    public function _urlFromParts(string $baseUrl, array $pathInfoArray = [], array $queryParametersArray = [], ?string $anchor = '', bool $escape = false): string
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
}

if (!PKP_STRICT_MODE) {
    class_alias('\PKP\core\PKPRouter', '\PKPRouter');
}

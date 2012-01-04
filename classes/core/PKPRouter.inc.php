<?php

/**
 * @file classes/core/PKPRouter.inc.php
 *
 * Copyright (c) 2000-2012 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class PKPRouter
 * @see PKPPageRouter
 * @see PKPComponentRouter
 * @ingroup core
 *
 * @brief Basic router class that has functionality common to all routers.
 */

// $Id$

class PKPRouter {
	//
	// Internal state cache variables
	// NB: Please do not access directly but
	// only via their respective getters/setters
	//
	/** @var PKPApplication */
	var $_application;
	/** @var Dispatcher */
	var $_dispatcher;
	/** @var integer context depth */
	var $_contextDepth;
	/** @var integer context list */
	var $_contextList;
	/** @var integer context list with keys and values flipped */
	var $_flippedContextList;
	/** @var integer context paths */
	var $_contextPaths = array();
	/** @var integer contexts */
	var $_contexts = array();


	/**
	 * get the application
	 * @return PKPApplication
	 */
	function &getApplication() {
		assert(is_a($this->_application, 'PKPApplication'));
		return $this->_application;
	}

	/**
	 * set the application
	 * @param $application PKPApplication
	 */
	function setApplication(&$application) {
		$this->_application =& $application;

		// Retrieve context depth and list
		$this->_contextDepth = $application->getContextDepth();
		$this->_contextList = $application->getContextList();
		$this->_flippedContextList = array_flip($this->_contextList);
	}

	/**
	 * get the dispatcher
	 * @return PKPDispatcher
	 */
	function &getDispatcher() {
		assert(is_a($this->_dispatcher, 'Dispatcher'));
		return $this->_dispatcher;
	}

	/**
	 * set the dispatcher
	 * @param $dispatcher PKPDispatcher
	 */
	function setDispatcher(&$dispatcher) {
		$this->_dispatcher =& $dispatcher;
	}

	/**
	 * Determines whether this router can route the given request.
	 * @param $request PKPRequest
	 * @return boolean true, if the router supports this request, otherwise false
	 */
	function supports(&$request) {
		// Default implementation returns always true
		return true;
	}

	/**
	 * Determine whether or not this request is cacheable
	 * @param $request PKPRequest
	 * @return boolean
	 */
	function isCacheable(&$request) {
		// Default implementation returns always false
		return false;
	}

	/**
	 * Determine the filename to use for a local cache file.
	 * @param $request PKPRequest
	 * @return string
	 */
	function getCacheFilename(&$request) {
		// must be implemented by sub-classes
		assert(false);
	}

	/**
	 * Routes a given request to a handler operation
	 * @param $request PKPRequest
	 */
	function route(&$request) {
		// must be implemented by sub-classes
		assert(false);
	}

	/**
	 * Build a handler request URL into PKPApplication.
	 * @param $request PKPRequest the request to be routed
	 * @param $newContext mixed Optional contextual paths
	 * @param $handler string Optional name of the handler to invoke
	 * @param $op string Optional name of operation to invoke
	 * @param $path mixed Optional string or array of args to pass to handler
	 * @param $params array Optional set of name => value pairs to pass as user parameters
	 * @param $anchor string Optional name of anchor to add to URL
	 * @param $escape boolean Whether or not to escape ampersands for this URL; default false.
	 * @return string the URL
	 */
	function url(&$request, $newContext = null, $handler = null, $op = null, $path = null,
				$params = null, $anchor = null, $escape = false) {
		// must be implemented by sub-classes
		assert(false);
	}

	/**
	 * A generic method to return an array of context paths (e.g. a Press or a Conference/SchedConf paths)
	 * @param $request PKPRequest the request to be routed
	 * @param $requestedContextLevel int (optional) the context level to return in the path
	 * @return array of string (each element the path to one context element)
	 */
	function getRequestedContextPaths(&$request) {
		// Handle context depth 0
		if (!$this->_contextDepth) return array();

		// Validate context parameters
		assert(isset($this->_contextDepth) && isset($this->_contextList));

		// Determine the context path
		if (empty($this->_contextPaths)) {
			if ($request->isPathInfoEnabled()) {
				// Retrieve context from the path info
				if (isset($_SERVER['PATH_INFO'])) {
					// Split the path info into its constituents. Save all non-context
					// path info in $this->_contextPaths[$this->_contextDepth]
					// by limiting the explode statement.
					$this->_contextPaths = explode('/', trim($_SERVER['PATH_INFO'], '/'), $this->_contextDepth + 1);
					// Remove the part of the path info that is not relevant for context (if present)
					unset($this->_contextPaths[$this->_contextDepth]);
				}
			} else {
				// Retrieve context from url query string
				foreach($this->_contextList as $key => $contextName) {
					$this->_contextPaths[$key] = $request->getUserVar($contextName);
				}
			}

			// Canonicalize and clean context paths
			for($key = 0; $key < $this->_contextDepth; $key++) {
				$this->_contextPaths[$key] = (
					isset($this->_contextPaths[$key]) && !empty($this->_contextPaths[$key]) ?
					$this->_contextPaths[$key] : 'index'
				);
				$this->_contextPaths[$key] = Core::cleanFileVar($this->_contextPaths[$key]);
			}

			HookRegistry::call('Router::getRequestedContextPaths', array(&$this->_contextPaths));
		}

		return $this->_contextPaths;
	}

	/**
	 * A generic method to return a single context path (e.g. a Press or a SchedConf path)
	 * @param $request PKPRequest the request to be routed
	 * @param $requestedContextLevel int (optional) the context level to return
	 * @return string
	 */
	function getRequestedContextPath(&$request, $requestedContextLevel = 1) {
		// Handle context depth 0
		if (!$this->_contextDepth) return null;

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
	 * @param $request PKPRequest the request to be routed
	 * @param $requestedContextLevel int (optional) the desired context level
	 * @return object
	 */
	function &getContext(&$request, $requestedContextLevel = 1) {
		// Handle context depth 0
		if (!$this->_contextDepth) {
			$nullVar = null;
			return $nullVar;
		}

		if (!isset($this->_contexts[$requestedContextLevel])) {
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
				$daoName = $contextClass.'DAO';
				$daoInstance =& DAORegistry::getDAO($daoName);

				// Retrieve the context from the DAO (by path)
				$daoMethod = 'get'.$contextClass.'ByPath';
				assert(method_exists($daoInstance, $daoMethod));
				$this->_contexts[$requestedContextLevel] = $daoInstance->$daoMethod($path);
			}
		}

		return $this->_contexts[$requestedContextLevel];
	}

	/**
	 * Get the object that represents the desired context (e.g. Conference or Press)
	 * @param $request PKPRequest the request to be routed
	 * @param $requestedContextName string page context
	 * @return object
	 */
	function &getContextByName(&$request, $requestedContextName) {
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
	 * @param $request PKPRequest the request to be routed
	 * @return string
	 */
	function getIndexUrl(&$request) {
		if (!isset($this->_indexUrl)) {
			if ($request->isRestfulUrlsEnabled()) {
				$this->_indexUrl = $request->getBaseUrl();
			} else {
				$this->_indexUrl = $request->getBaseUrl() . '/' . basename($_SERVER['SCRIPT_NAME']);
			}
			HookRegistry::call('Router::getIndexUrl', array(&$this->_indexUrl));
		}

		return $this->_indexUrl;
	}

	//
	// Private class helper methods
	//

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
	 * @param $newContext the raw context array
	 * @return array the canonicalized context array
	 */
	function _urlCanonicalizeNewContext($newContext) {
		// Create an empty array in case no new context was given.
		if (is_null($newContext)) $newContext = array();

		// If we got the new context as a scalar then transform
		// it into an array.
		if (is_scalar($newContext)) $newContext = array($newContext);

		// Check whether any new context has been provided.
		// If not then return an empty array.
		$newContextProvided = false;
		foreach($newContext as $contextElement) {
			if(isset($contextElement)) $newContextProvided = true;
		}
		if (!$newContextProvided) $newContext = array();

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
	 * @param $request PKPRequest the request to be routed
	 * @param $newContext mixed (optional) context that differs from
	 *  the current request's context
	 * @return array An array consisting of the base url as the first
	 *  entry and the context as the remaining entries.
	 */
	function _urlGetBaseAndContext(&$request, $newContext = array()) {
		$pathInfoEnabled = $request->isPathInfoEnabled();

		// Retrieve the context list.
		$contextList = $this->_contextList;

		// Determine URL context
		$context = array();
		foreach ($contextList as $contextKey => $contextName) {
			if ($pathInfoEnabled) {
				$contextParameter = '';
			} else {
				$contextParameter = $contextName.'=';
			}

			$newContextValue = array_shift($newContext);
			if (isset($newContextValue)) {
				// A new context has been set so use it.
				$contextValue = rawurlencode($newContextValue);
			} else {
				// No new context has been set so determine
				// the current request's context
				$contextObject =& $this->getContextByName($request, $contextName);
				if ($contextObject) $contextValue = $contextObject->getPath();
				else $contextValue = 'index';

			}

			// Check whether the base URL is overridden.
			if ($contextKey == 0) {
				$overriddenBaseUrl = Config::getVar('general', "base_url[$contextValue]");
			}

			$context[] = $contextParameter.$contextValue;;
		}

		// Generate the base url
		if (!empty($overriddenBaseUrl)) {
			$baseUrl = $overriddenBaseUrl;

			// Throw the overridden context away
			array_shift($context);
			array_shift($contextList);
		} else {
			$baseUrl = $this->getIndexUrl($request);
		}

		// Join base URL and context and return the result
		$baseUrlAndContext = array_merge(array($baseUrl), $context);
		return $baseUrlAndContext;
	}

	/**
	 * Build the additional parameters part of the URL.
	 * @param $request PKPRequest the request to be routed
	 * @param $params array (optional) the parameter list to be
	 *  transformed to a url part.
	 * @return array the encoded parameters or an empty array
	 *  if no parameters were given.
	 */
	function _urlGetAdditionalParameters(&$request, $params = null) {
		$additionalParameters = array();
		if (!empty($params)) {
			assert(is_array($params));
			foreach ($params as $key => $value) {
				if (is_array($value)) {
					foreach($value as $element) {
						$additionalParameters[] = $key.'%5B%5D='.rawurlencode($element);
					}
				} else {
					$additionalParameters[] = $key.'='.rawurlencode($value);
				}
			}
		}

		return $additionalParameters;
	}

	/**
	 * Creates a valid URL from parts.
	 * @param $baseUrl string the protocol, domain and initial path/parameters, no anchors allowed here
	 * @param $pathInfoArray array strings to be concatenated as path info
	 * @param $queryParametersArray array strings to be concatenated as query string
	 * @param $anchor string an additional anchor
	 * @param $escape boolean whether to escape ampersands
	 * @return string the URL
	 */
	function _urlFromParts($baseUrl, $pathInfoArray = array(), $queryParametersArray = array(), $anchor = '', $escape = false) {
		// Parse the base url
		$baseUrlParts = parse_url($baseUrl);
		assert(isset($baseUrlParts['scheme']) && isset($baseUrlParts['host']) && !isset($baseUrlParts['fragment']));

		// Reconstruct the base url without path and query
		$baseUrl = $baseUrlParts['scheme'].'://';
		if (isset($baseUrlParts['user'])) {
			$baseUrl .= $baseUrlParts['user'];
			if (isset($baseUrlParts['pass'])) {
				$baseUrl .= ':'.$baseUrlParts['pass'];
			}
			$baseUrl .= '@';
		}
		$baseUrl .= $baseUrlParts['host'];
		if (isset($baseUrlParts['port'])) $baseUrl .= ':'.$baseUrlParts['port'];
		$baseUrl .= '/';

		// Add path info from the base URL
		// to the path info array (if any).
		if (isset($baseUrlParts['path'])) {
			$pathInfoArray = array_merge(explode('/', trim($baseUrlParts['path'], '/')), $pathInfoArray);
		}

		// Add query parameters from the base URL
		// to the query parameter array (if any).
		if (isset($baseUrlParts['query'])) {
			$queryParametersArray = array_merge(explode('&', $baseUrlParts['query']), $queryParametersArray);
		}

		// Expand path info
		$pathInfo = implode('/', $pathInfoArray);

		// Expand query parameters
		$amp = ($escape ? '&amp;' : '&');
		$queryParameters = implode($amp, $queryParametersArray);
		$queryParameters = (empty($queryParameters) ? '' : '?'.$queryParameters);

		// Assemble and return the final URL
		return $baseUrl.$pathInfo.$queryParameters.$anchor;
	}

	/**
	 * Convert a context level to its corresponding context name.
	 * @param $contextLevel integer
	 * @return string context name
	 */
	function _contextLevelToContextName($contextLevel) {
		assert(isset($this->_contextList[$contextLevel - 1]));
		return $this->_contextList[$contextLevel - 1];
	}

	/**
	 * Convert a context name to its corresponding context level.
	 * @param $contextName string
	 * @return integer context level
	 */
	function _contextNameToContextLevel($contextName) {
		assert(isset($this->_flippedContextList[$contextName]));
		return $this->_flippedContextList[$contextName] + 1;
	}
}

?>

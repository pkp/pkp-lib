<?php

/**
 * @file classes/core/PKPComponentRouter.inc.php
 *
 * Copyright (c) 2000-2012 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class PKPComponentRouter
 * @ingroup core
 *
 * @brief Class mapping an HTTP request to a component handler operation.
 *
 *  We are using an RPC style URL-to-endpoint mapping. Our approach follows
 *  a simple "convention-over-configuration" paradigm. If necessary the
 *  router can be subclassed to implement more complex URL-to-endpoint mappings.
 *
 *  For servers with path info enabled the component URL has the following elements:
 *
 *       .../index.php/context1/context2/$$$call$$$/path/to/handler-class/operation-name?arg1=...&arg2=...
 *
 *  where "$$$call$$$" is a non-mutable literal string and "path/to" is
 *  by convention the directory path below the components folder leading to the
 *  component. The next element ("handler-class" in this example) will be mapped to a
 *  component class file by "camelizing" the string to "HandlerClassHandler" and adding
 *  ".inc.php" to the end. The "operation-name" is transformed to "operationName"
 *  and represents the name of the handler method to be called. Finally "arg1", "arg2",
 *  etc. are parameters to be passed along to the handler method.
 *
 *  For servers with path info disabled the component URL looks like this:
 *
 *       .../index.php?component=path.to.handler-class&op=operation-name&arg1=...&arg2=...
 *
 *  The router will sanitize the request URL to a certain amount to make sure that
 *  random code inclusions are prevented. User authorization and parameter validation
 *  are however not the router's concern. These must be implemented on handler level.
 *
 *  NB: Component and operation names may only contain a-z, 0-9 and hyphens. Numbers
 *  are not allowed at the beginning of a name or after a hyphen.
 *
 *  NB: Component handlers must implement an initialize() method that will be called
 *  before the request is routed. The initialization method must enforce authorization
 *  and request validation.
 */

// $Id$


// The string to be found in the URL to mark this request as a component request
define('COMPONENT_ROUTER_PATHINFO_MARKER', '$$$call$$$');

// The parameter to be found in the query string for servers with path info disabled
define('COMPONENT_ROUTER_PARAMETER_MARKER', 'component');

// This is the maximum directory depth allowed within the component directory. Set
// it to something reasonable to avoid DoS or overflow attacks
define ('COMPONENT_ROUTER_PARTS_MAXDEPTH', 5);

// This is the maximum/minimum length of the name of a sub-directory or
// handler class name.
define ('COMPONENT_ROUTER_PARTS_MAXLENGTH', 50);
define ('COMPONENT_ROUTER_PARTS_MINLENGTH', 2);

import('core.PKPRouter');
import('core.Request');

class PKPComponentRouter extends PKPRouter {
	//
	// Internal state cache variables
	// NB: Please do not access directly but
	// only via their respective getters/setters
	//
	/** @var string the requested component handler */
	var $_component;
	/** @var string the requested operation */
	var $_op;
	/** @var array the rpc service endpoint parts from the request */
	var $_rpcServiceEndpointParts = false;
	/** @var callable the rpc service endpoint the request was routed to */
	var $_rpcServiceEndpoint = false;


	/**
	 * Determines whether this router can route the given request.
	 * @param $request PKPRequest
	 * @return boolean true, if the router supports this request, otherwise false
	 */
	function supports(&$request) {
		// See whether we can resolve the request to
		// a valid service endpoint.
		return is_callable($this->getRpcServiceEndpoint($request));
	}

	/**
	 * Routes the given request to a page handler
	 * @param $request PKPRequest
	 */
	function route(&$request) {
		// Determine the requested service endpoint.
		$rpcServiceEndpoint =& $this->getRpcServiceEndpoint($request);
		assert(is_callable($rpcServiceEndpoint));

		// Retrieve RPC arguments from the request.
		$args =& $request->getUserVars();
		assert(is_array($args));

		// Remove the caller-parameter (if present)
		if (isset($args[COMPONENT_ROUTER_PARAMETER_MARKER])) unset($args[COMPONENT_ROUTER_PARAMETER_MARKER]);

		// Authorize and validate the request
		if (!$rpcServiceEndpoint[0]->validate(null, $request)) {
			// Components must always validate otherwise this is
			// either a programming error or somebody trying to
			// directly call a component. In both cases a fatal
			// error is the approprate response.
			fatalError('Permission denied!');
		}

		// Initialize the handler
		$rpcServiceEndpoint[0]->initialize($request);

		// Call the service endpoint.
		$result = call_user_func($rpcServiceEndpoint, $args, $request);
		echo $result;
	}

	/**
	 * Retrieve the requested component from the request.
	 *
	 * NB: This can be a component that not actually exists
	 * in the code base.
	 *
	 * @param $request PKPRequest
	 * @return string the requested component or an empty string
	 *  if none can be found.
	 */
	function getRequestedComponent(&$request) {
		if (is_null($this->_component)) {
			$this->_component = '';

			// Retrieve the service endpoint parts from the request.
			if (is_null($rpcServiceEndpointParts = $this->_getValidatedServiceEndpointParts($request))) {
				// Endpoint parts cannot be found in the request
				return '';
			}

			// Pop off the operation part
			array_pop($rpcServiceEndpointParts);

			// Construct the fully qualified component class name from the rest of it.
			$handlerClassName = String::camelize(array_pop($rpcServiceEndpointParts), CAMEL_CASE_HEAD_UP).'Handler';

			// camelize remaining endpoint parts
			$camelizedRpcServiceEndpointParts = array();
			foreach ( $rpcServiceEndpointParts as $part) {
				$camelizedRpcServiceEndpointParts[] = String::camelize($part, CAMEL_CASE_HEAD_DOWN);
			}
			$handlerPackage = implode('.', $camelizedRpcServiceEndpointParts);

			$this->_component = $handlerPackage.'.'.$handlerClassName;
		}

		return $this->_component;
	}

	/**
	 * Retrieve the requested operation from the request
	 *
	 * NB: This can be an operation that not actually
	 * exists in the requested component.
	 *
	 * @param $request PKPRequest
	 * @return string the requested operation or an empty string
	 *  if none can be found.
	 */
	function getRequestedOp(&$request) {
		if (is_null($this->_op)) {
			$this->_op = '';

			// Retrieve the service endpoint parts from the request.
			if (is_null($rpcServiceEndpointParts = $this->_getValidatedServiceEndpointParts($request))) {
				// Endpoint parts cannot be found in the request
				return '';
			}

			// Pop off the operation part
			$this->_op = String::camelize(array_pop($rpcServiceEndpointParts), CAMEL_CASE_HEAD_DOWN);
		}

		return $this->_op;
	}

	/**
	 * Get the (validated) RPC service endpoint from the request.
	 * If no such RPC service endpoint can be constructed then the method
	 * returns null.
	 * @param $request PKPRequest the request to be routed
	 * @return callable an array with the handler instance
	 *  and the handler operation to be called by call_user_func().
	 */
	function &getRpcServiceEndpoint(&$request) {
		if ($this->_rpcServiceEndpoint === false) {
			// We have not yet resolved this request. Mark the
			// state variable so that we don't try again next
			// time.
			$this->_rpcServiceEndpoint = $nullVar = null;

			//
			// Component Handler
			//
			// Retrieve requested component handler
			$component = $this->getRequestedComponent($request);
			if (empty($component)) return $nullVar;

			// Construct the component handler file name and test its existence.
			$component = 'controllers.'.$component;
			$componentFileName = str_replace('.', '/', $component).'.inc.php';
			if (!file_exists($componentFileName) && !file_exists('lib/pkp/'.$componentFileName)) {
				// Request to non-existent handler
				return $nullVar;
			}

			// Declare the component handler class.
			import($component);

			// Check that the component class has really been declared
			$componentClassName = substr($component, strrpos($component, '.') + 1);
			assert(class_exists($componentClassName));

			//
			// Operation
			//
			// Retrieve requested component operation
			$op = $this->getRequestedOp($request);
			assert(!empty($op));

			// Check that the requested operation exists for the handler:
			// Lowercase comparison for PHP4 compatibility. Also check the required
			// validate() and initialize() methods.
			$methods = array_map('strtolower', get_class_methods($componentClassName));
			foreach(array(strtolower($op), 'validate', 'initialize') as $requiredMethod) {
				if (!in_array($requiredMethod, $methods)) return $nullVar;
			}

			//
			// Callable service endpoint
			//
			// Instantiate the handler
			$componentInstance = new $componentClassName();

			// Check that the component instance really is a handler
			if (!is_a($componentInstance, 'PKPHandler')) return $nullVar;

			// Check that the requested operation is on the
			// remote operation whitelist.
			if (!in_array($op, $componentInstance->getRemoteOperations())) return $nullVar;

			// Construct the callable array
			$this->_rpcServiceEndpoint = array($componentInstance, $op);
		}

		return $this->_rpcServiceEndpoint;
	}

	/**
	 * Build a component request URL into PKPApplication.
	 * @param $request PKPRequest the request to be routed
	 * @param $context mixed Optional contextual paths
	 * @param $component string Optional name of page to invoke
	 * @param $op string Optional name of operation to invoke
	 * @param $path for compatibility only, not supported for the component router.
	 * @param $params array Optional set of name => value pairs to pass as user parameters
	 * @param $anchor string Optional name of anchor to add to URL
	 * @param $escape boolean Whether or not to escape ampersands for this URL; default false.
	 * @return string the URL
	 */
	function url(&$request, $newContext = null, $component = null, $op = null, $path = null,
			$params = null, $anchor = null, $escape = false) {
		assert(is_null($path));
		$pathInfoEnabled = $request->isPathInfoEnabled();

		//
		// Base URL and Context
		//
		$baseUrlAndContext = $this->_urlGetBaseAndContext(
				$request, $this->_urlCanonicalizeNewContext($newContext));
		$baseUrl = array_shift($baseUrlAndContext);
		$context = $baseUrlAndContext;

		//
		// Component and Operation
		//
		// We only support component/op retrieval from the request
		// if this request is a component request.
		$currentRequestIsAComponentRequest = is_a($request->getRouter(), 'PKPComponentRouter');
		if($currentRequestIsAComponentRequest) {
			if (empty($component)) $component = $this->getRequestedComponent($request);
			if (empty($op)) $op = $this->getRequestedOp($request);
		}
		assert(!empty($component) && !empty($op));

		// Encode the component and operation
		$componentParts = explode('.', $component);
		$componentName = array_pop($componentParts);
		assert(substr($componentName, -7) == 'Handler');
		$componentName = String::uncamelize(substr($componentName, 0, -7));

		// uncamelize the component parts
		$uncamelizedComponentParts = array();
		foreach ($componentParts as $part) {
			$uncamelizedComponentParts[] = String::uncamelize($part);
		}
		array_push($uncamelizedComponentParts, $componentName);
		$opName = String::uncamelize($op);

		//
		// Additional query parameters
		//
		$additionalParameters = $this->_urlGetAdditionalParameters($request, $params);

		//
		// Anchor
		//
		$anchor = (empty($anchor) ? '' : '#'.rawurlencode($anchor));

		//
		// Assemble URL
		//
		if ($pathInfoEnabled) {
			// If path info is enabled then context, page,
			// operation and additional path go into the
			// path info.
			$pathInfoArray = array_merge(
				$context,
				array(COMPONENT_ROUTER_PATHINFO_MARKER),
				$uncamelizedComponentParts,
				array($opName)
			);

			// Query parameters
			$queryParametersArray = $additionalParameters;
		} else {
			// If path info is disabled then context, page,
			// operation and additional path are encoded as
			// query parameters.
			$pathInfoArray = array();

			// Query parameters
			$queryParametersArray = array_merge(
				$context,
				array(
					COMPONENT_ROUTER_PARAMETER_MARKER.'='.implode('.', $uncamelizedComponentParts),
					"op=$opName"
				),
				$additionalParameters
			);
		}

		return $this->_urlFromParts($baseUrl, $pathInfoArray, $queryParametersArray, $anchor, $escape);
	}


	//
	// Private helper methods
	//
	/**
	 * Get the (validated) RPC service endpoint parts from the request.
	 * If no such RPC service endpoint parts can be retrieved
	 * then the method returns null.
	 * @param $request PKPRequest the request to be routed
	 * @return array a string array with the RPC service endpoint
	 *  parts as values.
	 */
	function _getValidatedServiceEndpointParts(&$request) {
		if ($this->_rpcServiceEndpointParts === false) {
			// Mark the internal state variable so this
			// will not be called again.
			$this->_rpcServiceEndpointParts = null;

			// Retrieve service endpoint parts from the request.
			if (is_null($rpcServiceEndpointParts = $this->_retrieveServiceEndpointParts($request))) {
				// This is not an RPC request
				return null;
			}

			// Validate the service endpoint parts.
			if (is_null($rpcServiceEndpointParts = $this->_validateServiceEndpointParts($rpcServiceEndpointParts))) {
				// Invalid request
				return null;
			}

			// Assign the validated service endpoint parts
			$this->_rpcServiceEndpointParts = $rpcServiceEndpointParts;
		}

		return $this->_rpcServiceEndpointParts;
	}

	/**
	 * Try to retrieve a (non-validated) array with the service
	 * endpoint parts from the request. See the classdoc for the
	 * URL patterns supported here.
	 * @param $request PKPRequest the request to be routed
	 * @return array an array of (non-validated) service endpoint
	 *  parts or null if the request is not an RPC request.
	 */
	function _retrieveServiceEndpointParts(&$request) {
		// URL pattern depends on whether the server has path info
		// enabled or not. See classdoc for details.
		if ($request->isPathInfoEnabled()) {
			if (!isset($_SERVER['PATH_INFO'])) return null;

			$pathInfoParts = explode('/', trim($_SERVER['PATH_INFO'], '/'));

			// We expect at least the context + the component
			// router marker + 3 component parts (path, handler, operation)
			$application = $this->getApplication();
			$contextDepth = $application->getContextDepth();
			if (count($pathInfoParts) < $contextDepth + 4) {
				// This path info is too short to be an RPC request
				return null;
			}

			// Check the component router marker
			if ($pathInfoParts[$contextDepth] != COMPONENT_ROUTER_PATHINFO_MARKER) {
				// This is not an RPC request
				return null;
			}

			// Remove context and component marker from the array
			$rpcServiceEndpointParts = array_slice($pathInfoParts, $contextDepth + 1);
		} else {
			$componentParameter = $request->getUserVar(COMPONENT_ROUTER_PARAMETER_MARKER);
			$operationParameter = $request->getUserVar('op');
			if (is_null($componentParameter) || is_null($operationParameter)) {
				// This is not an RPC request
				return null;
			}

			// Expand the router parameter
			$rpcServiceEndpointParts = explode('.', $componentParameter);

			// Add the operation
			array_push($rpcServiceEndpointParts, $operationParameter);
		}

		return $rpcServiceEndpointParts;
	}

	/**
	 * This method pre-validates the service endpoint parts before
	 * we try to convert them to a file/method name. This also
	 * converts all parts to lower case.
	 * @param $rpcServiceEndpointParts array
	 * @return array the validated service endpoint parts or null if validation
	 *  does not succeed.
	 */
	function _validateServiceEndpointParts($rpcServiceEndpointParts) {
		// Do we have data at all?
		if (is_null($rpcServiceEndpointParts) || empty($rpcServiceEndpointParts)
				|| !is_array($rpcServiceEndpointParts)) return null;

		// We require at least three parts: component directory, handler
		// and method name.
		if (count($rpcServiceEndpointParts) < 3) return null;

		// Check that the array dimensions remain within sane limits.
		if (count($rpcServiceEndpointParts) > COMPONENT_ROUTER_PARTS_MAXDEPTH) return null;

		// Validate the individual endpoint parts.
		foreach($rpcServiceEndpointParts as $key => $rpcServiceEndpointPart) {
			// Make sure that none of the elements exceeds the length limit.
			$partLen = strlen($rpcServiceEndpointPart);
			if ($partLen > COMPONENT_ROUTER_PARTS_MAXLENGTH
					|| $partLen < COMPONENT_ROUTER_PARTS_MINLENGTH) return null;

			// Service endpoint URLs are case insensitive.
			$rpcServiceEndpointParts[$key] = strtolower($rpcServiceEndpointPart);

			// We only allow letters, numbers and the hyphen.
			if (!String::regexp_match('/^[a-z0-9-]*$/', $rpcServiceEndpointPart)) return null;
		}

		return $rpcServiceEndpointParts;
	}
}
?>

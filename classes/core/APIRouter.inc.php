<?php

/**
 * @file classes/core/APIRouter.inc.php
 *
 * Copyright (c) 2014-2017 Simon Fraser University
 * Copyright (c) 2000-2016 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
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

import('lib.pkp.classes.core.PKPRouter');
import('classes.core.Request');
import('classes.handler.Handler');

class APIRouter extends PKPRouter {
	/** @var APIHandler */
	var $_handler;

	/**
	 * Constructor
	 */
	function APIRouter() {
		parent::__construct();
	}

	/**
	 * Determines whether this router can route the given request.
	 * @param $request PKPRequest
	 * @return boolean true, if the router supports this request, otherwise false
	 */
	function supports($request) {
		if (!isset($_SERVER['PATH_INFO'])) return false;
		$pathInfoParts = explode('/', trim($_SERVER['PATH_INFO'], '/'));

		if (count($pathInfoParts)>=2 && $pathInfoParts[1] == 'api') {
			// Context-specific API requests: [index.php]/{contextPath}/api
			return true;
		}

		return false;
	}

	/**
	 * Get the API version
	 * @return string
	 */
	function getVersion() {
		$pathInfoParts = explode('/', trim($_SERVER['PATH_INFO'], '/'));
		return Core::cleanFileVar(isset($pathInfoParts[2]) ? $pathInfoParts[2] : '');
	}

	/**
	 * Get the entity being requested
	 * @return string|array
	 */
	function getEntity() {
		$pathInfoParts = explode('/', trim($_SERVER['PATH_INFO'], '/'));
		if (isset($pathInfoParts[4])) {
			return array(
				Core::cleanFileVar(isset($pathInfoParts[3]) ? $pathInfoParts[3] : ''),
				Core::cleanFileVar(isset($pathInfoParts[4]) ? $pathInfoParts[4] : '')
			);
		}
		return Core::cleanFileVar(isset($pathInfoParts[3]) ? $pathInfoParts[3] : '');
	}

	//
	// Implement template methods from PKPRouter
	//
	/**
	 * @copydoc PKPRouter::route()
	 */
	function route($request) {
		// Ensure slim library is available
		require_once('lib/pkp/lib/vendor/autoload.php');

		$sourceFile = null;
		$entity = $this->getEntity();
		if (is_array($entity)) {
			$sourceFile = sprintf('api/%s/%s/%s/index.php', $this->getVersion(), $entity[0], $entity[1]);
		}
		else {
			$sourceFile = sprintf('api/%s/%s/index.php', $this->getVersion(), $this->getEntity());
		}

		if (!file_exists($sourceFile)) {
			$dispatcher = $this->getDispatcher();
			$dispatcher->handle404();
		}

		if (!defined('SESSION_DISABLE_INIT')) {
			// Initialize session
			SessionManager::getManager();
		}

		$this->_handler = require ('./'.$sourceFile);
		$this->_handler->getApp()->run();
	}

	/**
	 * Get the API handler.
	 * @return APIHandler
	 */
	function getHandler() {
		return $this->_handler;
	}

	/**
	 * Generate a URL into the API.
	 * FIXME: Unimplemented.
	 * @param $request PKPRequest
	 * @param $endpoing string API endpoint
	 * @param $params array
	 */
	function url($request, $endpoint, $params) {
		fatalError('unimplemented.');
	}

	function getRequestedOp($request) {
		$handler = $this->getHandler();
		$container = $handler->getApp()->getContainer();
		$router = $container->get('router');
		$request = $container->get('request');
		$routeInfo = $router->dispatch($request);
		if (isset($routeInfo[1])) {
			$route = $router->lookupRoute($routeInfo[1]);
			$callable = $route->getCallable();
			if (is_array($callable) && count($callable) == 2)
				return $callable[1];
		}
		return '';
	}

	/**
	 * @copydoc PKPRouter::handleAuthorizationFailure()
	 */
	function handleAuthorizationFailure($request, $authorizationMessage) {
		$dispatcher = $this->getDispatcher();
		$dispatcher->handle404();
	}

}

?>

<?php

/**
 * @file classes/core/APIRouter.inc.php
 *
 * Copyright (c) 2014-2018 Simon Fraser University
 * Copyright (c) 2000-2018 John Willinsky
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
	 * Determines path info parts depending of disable_path_info config value
	 * @return array|NULL
	 */
	protected function getPathInfoParts() {
		$pathInfoEnabled = Config::getVar('general', 'disable_path_info') ? false : true;
		if ($pathInfoEnabled && isset($_SERVER['PATH_INFO'])) {
			return explode('/', trim($_SERVER['PATH_INFO'], '/'));
		}

		$request = $this->getApplication()->getRequest();
		$queryString = $request->getQueryString();
		$queryArray = array();
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
	 * @param $request PKPRequest
	 * @return boolean true, if the router supports this request, otherwise false
	 */
	function supports($request) {
		$pathInfoParts = $this->getPathInfoParts();

		if (!is_null($pathInfoParts) && count($pathInfoParts)>=2 && $pathInfoParts[1] == 'api') {
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
		$pathInfoParts = $this->getPathInfoParts();
		return Core::cleanFileVar(isset($pathInfoParts[2]) ? $pathInfoParts[2] : '');
	}

	/**
	 * Get the entity being requested
	 * @return string
	 */
	function getEntity() {
		$pathInfoParts = $this->getPathInfoParts();
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

		$sourceFile = sprintf('api/%s/%s/index.php', $this->getVersion(), $this->getEntity());

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
	 * Get the requested operation
	 *
	 * @param $request PKPRequest
	 * @return string
	 */
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



<?php

/**
 * @file classes/core/APIRouter.inc.php
 *
 * Copyright (c) 2014-2016 Simon Fraser University Library
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
import('lib.pkp.classes.handler.IAPIHandler');
import('classes.core.Request');
import('classes.handler.Handler');

class APIRouter extends PKPRouter {
	/**
	 * Constructor
	 */
	function APIRouter() {
		parent::PKPRouter();
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

	//
	// Implement template methods from PKPRouter
	//
	/**
	 * @copydoc PKPRouter::route()
	 */
	function route($request) {
		// Ensure slim library is available
		require_once('lib/pkp/lib/vendor/autoload.php');

		$pathInfoParts = explode('/', trim($_SERVER['PATH_INFO'], '/'));
		
		$version = isset($pathInfoParts[2]) ? $pathInfoParts[2] : '';
		$entity = isset($pathInfoParts[3]) ? $pathInfoParts[3] : '';
		
		$version = Core::cleanFileVar($version);
		$entity = Core::cleanFileVar($entity);
		
		$sourceFile = sprintf('api/%s/%s/index.php', $version, $entity);
		
		if (!file_exists($sourceFile)) {
			$dispatcher = $this->getDispatcher();
			$dispatcher->handle404();
		}
		
		$app = require ('./'.$sourceFile);
		$app->run();
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

	/**
	 * @copydoc PKPRouter::handleAuthorizationFailure()
	 */
	function handleAuthorizationFailure($request, $authorizationMessage) {
		http_response_code(401);
		exit();
	}
}

?>

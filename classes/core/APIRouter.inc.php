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
import('classes.core.Request');

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

		if (count($pathInfoParts)>=1 && $pathInfoParts[0] == 'api') {
			// Site-wide API requests: [index.php]/api/...
			return true;
		}

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

		$app = new \Slim\App;
		
		$pathInfoParts = explode('/', trim($_SERVER['PATH_INFO'], '/'));
		error_log(print_r($pathInfoParts,true));
		
		$version = $pathInfoParts[2];
		$entity = $pathInfoParts[3];
		$className = ucfirst($entity) . 'Handler';
		$sourceFile = sprintf('api/%s/%s.php', $version, "{$className}");
		
		if (file_exists($sourceFile)) require $sourceFile;
		
		
// 		$pattern = '/{contextPath}/api/v{id:[0-9]+}/{params:.*}';
		
// 		$app->group($pattern, function() use ($app, $pattern) {
			
// 			$this->map(['GET', 'DELETE', 'PATCH', 'PUT'], '', function ($request, $response, $args) use ($app, $pattern) {
				
// 				$version = $request->getAttribute('id');
// 				$params = explode('/', $request->getAttribute('params'));
				
// 				$entity = $params[0];
// 				$className = ucfirst($entity) . 'Handler';
// 				$sourceFile = sprintf('api/%s.php', "{$className}");
				
// 				if (file_exists($sourceFile)) require $sourceFile;
				
// 				$instance = new $className($app, $pattern);
				
// 				$response->getBody()->write("File Handler in action");
// 				return $response;
// 			});
			
// 		});
		
		
// 		$app->group('/{contextPath}/api/v{id:[0-9]+}/{params:.*}', function() use ($app) {
			
// 			$version = $request->getAttribute('id');
// 			$params = explode('/', $request->getAttribute('params'));
			
// 			$entity = $params[0];
// 			$className = ucfirst($entity) . 'Handler';
// 			$sourceFile = sprintf('api/%s.php', "{$className}");
			
// 			if (file_exists($sourceFile)) require $sourceFile;
			
// 			$app->any("/{contextPath}/api/v{id:[0-9]+}/{$entity}", "\{$className}");
			
// 			$this->map(['GET', 'DELETE', 'PATCH', 'PUT'], '', function ($request, $response, $args) use ($app) {
				
// 				$version = $request->getAttribute('id');
// 				$params = explode('/', $request->getAttribute('params'));
				
// 				$entity = $params[0];
// 				$className = ucfirst($entity) . 'Handler';
// 				$sourceFile = sprintf('api/%s.php', "{$className}");
				
// 				if (file_exists($sourceFile)) require $sourceFile;
				
// 				$app->any("/{contextPath}/api/v{id:[0-9]+}/{$entity}", "\{$className}");
				
// 				$instance = new $className($app);
				
// 				return $instance->handle($request, $response, $args);
// 			});
// 		});
		
		
		$app->group('/api/v{id:[0-9]+}/{params:.*}', function() {
			$this->map(['GET', 'DELETE', 'PATCH', 'PUT'], '', function ($request, $response, $args) {
				$version = $request->getAttribute('id');
				$params = explode('/', $request->getAttribute('params'));
				$response->getBody()->write(print_r(['context' => 'Site-wide API', 'version' => $version, 'params' => $params],true)); 
				return $response;
			});
		});
		
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

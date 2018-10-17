<?php

/**
 * @file classes/security/authorization/internal/ApiTokenDecodingMiddleware.inc.php
 *
 * Copyright (c) 2014-2018 Simon Fraser University
 * Copyright (c) 2000-2018 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class ApiTokenDecodingMiddleware
 * @ingroup security_authorization
 *
 * @brief Slim middleware which decodes and validates JSON Web Tokens
 */

use \Firebase\JWT\JWT;

class ApiTokenDecodingMiddleware {
	/** @var APIHandler $handler Reference to api handler */
	protected $_handler = null;

	/**
	 * Constructor
	 *
	 * @param APIHandler $handler
	 */
	public function __construct(APIHandler $handler) {
		$this->_handler = $handler;
	}

	/**
	 * Decodes the request's JSON Web Token
	 * @param SlimRequest $slimRequest
	 *
	 * @return boolean|string
	 */
	protected function _decode($slimRequest) {
		$secret = Config::getVar('security', 'api_key_secret', '');
		if ($secret !== '' && !is_null($jwt = $slimRequest->getQueryParam('apiToken'))) {
			try {
				$apiToken = json_decode(JWT::decode($jwt, $secret, array('HS256')));
				$this->_handler->setApiToken($apiToken);
				return true;
			} catch (Exception $e) {
				// If JWT decoding fails, it throws an
				// 'UnexpectedValueException'.  If JSON decoding fails
				// (of the JWT payload), it throws a 'DomainException'.
				if (is_a($e, 'UnexpectedValueException') || is_a($e, 'DomainException')) {
					$request = $this->_handler->getRequest();
					$router = $request->getRouter();
					$result = $router->handleAuthorizationFailure($request, $e->getMessage());
					switch(1) {
						case is_string($result): return $result;
						case is_a($result, 'JSONMessage'): return $result->getString();
						default:
							assert(false);
							return null;
					}
				}
				throw $e;
			}
		}
		// If we do not have a token, it's for the authentication logic
		// to decide if that's a problem.
		return true;
	}

	/**
	 * Middleware invokable function
	 *
	 * @param SlimRequest $request request
	 * @param SlimResponse $response response
	 * @param callable $next Next middleware
	 * @return boolean|string|unknown
	 */
	public function __invoke($request, $response, $next) {
		$result = $this->_decode($request);
		if ($result !== true) {
			return $result;
		}

		$response = $next($request, $response);
		return $response;
	}
}



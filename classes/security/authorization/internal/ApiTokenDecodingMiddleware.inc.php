<?php

/**
 * @file classes/security/authorization/internal/ApiTokenDecodingMiddleware.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class ApiTokenDecodingMiddleware
 * @ingroup security_authorization
 *
 * @brief Slim middleware which decodes and validates JSON Web Tokens
 */

use \Firebase\JWT\JWT;

use Firebase\JWT\SignatureInvalidException;

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
		$jwt = $slimRequest->getQueryParam('apiToken');
		if (!$jwt) {
			/**
			 * If we don't have a token, it's for the authentication logic to handle if it's a problem.
			 */

			 return true;
		}

		$secret = Config::getVar('security', 'api_key_secret', '');
		if (!$secret) {
			$request = $this->_handler->getRequest();
			return $request->getRouter()
				->handleAuthorizationFailure(
					$request,
					'api.500.apiSecretKeyMissing'
				);
		}

		try {
			$apiToken = JWT::decode($jwt, $secret, ['HS256']);
			/**
			 * Compatibility with old API keys
			 * @link https://github.com/pkp/pkp-lib/issues/6462
			 */
			if (substr($apiToken, 0, 2) === '""') {
				$apiToken = json_decode($apiToken);
			}
			$this->_handler->setApiToken($apiToken);

			return true;
		} catch (Exception $e) {
			/**
			 * If JWT decoding fails, it throws an 'UnexpectedValueException'.
			 * If JSON decoding fails (of the JWT payload), it throws a 'DomainException'.
			 * If token couldn't verified, it throws a 'SignatureInvalidException'.
			 */
			if (is_a($e, SignatureInvalidException::class)) {
				$request = $this->_handler->getRequest();
				return $request->getRouter()
					->handleAuthorizationFailure(
						$request,
						'api.400.invalidApiToken'
					);
			}

			if (is_a($e, 'UnexpectedValueException') ||
				is_a($e, 'DomainException')
			) {
				$request = $this->_handler->getRequest();
				return $request->getRouter()
					->handleAuthorizationFailure(
						$request,
						'api.400.tokenCouldNotBeDecoded',
						[
							'error' => $e->getMessage()
						]
					);
			}

			throw $e;
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



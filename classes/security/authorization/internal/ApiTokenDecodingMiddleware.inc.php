<?php

/**
 * @file classes/security/authorization/internal/ApiTokenDecodingMiddleware.inc.php
 *
 * Copyright (c) 2014-2020 Simon Fraser University
 * Copyright (c) 2000-2020 John Willinsky
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
        $secret = Config::getVar('security', 'api_key_secret', '');

        if ($secret === '') {
            $request = $this->_handler->getRequest();
            return $request->getRouter()
                ->handleAuthorizationFailure(
                    $request,
                    'api.api_key_secret.should.be.filled'
                );
        }

        if ($secret !== '' && !is_null($jwt = $slimRequest->getQueryParam('apiToken'))) {
            try {
                $apiToken = JWT::decode($jwt, $secret, ['HS256']);
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
                            'api.invalid_token_signature'
                        );
                }

                if (is_a($e, 'UnexpectedValueException') ||
                    is_a($e, 'DomainException')
                ) {
                    $request = $this->_handler->getRequest();
                    $result = $request->getRouter()
                        ->handleAuthorizationFailure(
                            $request,
                            $e->getMessage()
                        );

                    if (is_string($result)) {
                        return $result;
                    }

                    if (is_a($result, 'JSONMessage')) {
                        return $result->getString();
                    }

                    assert(false);
                    return null;
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



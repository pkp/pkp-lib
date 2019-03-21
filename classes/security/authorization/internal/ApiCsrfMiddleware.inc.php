<?php

/**
 * @file classes/security/authorization/internal/ApiCsrfMiddleware.inc.php
 *
 * Copyright (c) 2014-2018 Simon Fraser University
 * Copyright (c) 2000-2018 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class ApiCsrfMiddleware
 * @ingroup security_authorization
 *
 * @brief Slim middleware which requires a CSRF token for POST, PUT and DELETE
 *  operations whenever an API Token is not in use.
 */

class ApiCsrfMiddleware {

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
	 * Middleware invokable function
	 *
	 * @param SlimRequest $slimRequest request
	 * @param SlimResponse $response response
	 * @param callable $next Next middleware
	 * @return SlimResponse
	 */
	public function __invoke($slimRequest, $response, $next) {
		if ($this->_isCSRFRequired($slimRequest) && !$this->_isCSRFValid($slimRequest)) {
			return $response->withJson([
				'error' => 'form.csrfInvalid',
				'errorMessage' => __('form.csrfInvalid'),
			], 403);
		}
		$response = $next($slimRequest, $response);
		return $response;
	}

	/**
	 * Check if a CSRF token is required
	 *
	 * @param SlimRequest $slimRequest
	 * @return boolean
	 */
	protected function _isCSRFRequired($slimRequest) {
		if ($this->_handler->getApiToken()) {
			return false;
		}
		$server = $slimRequest->getServerParams();
		return !empty($server['REQUEST_METHOD']) && in_array($server['REQUEST_METHOD'], ['POST', 'PUT', 'DELETE']);
	}

	/**
	 * Check if the CSRF token is present and valid
	 *
	 * @param SlimRequest $slimRequest
	 * @return boolean
	 */
	protected function _isCSRFValid($slimRequest) {
		$server = $slimRequest->getServerParams();
		if (empty($server['HTTP_X_CSRF_TOKEN'])) {
			return false;
		}
		$session = Application::get()->getRequest()->getSession();
		return $session && $session->getCSRFToken() === $server['HTTP_X_CSRF_TOKEN'];
	}
}

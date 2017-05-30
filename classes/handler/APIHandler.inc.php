<?php

/**
 * @file lib/pkp/classes/handler/APIHandler.inc.php
 *
 * Copyright (c) 2014-2017 Simon Fraser University
 * Copyright (c) 2003-2016 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class APIHandler
 * @ingroup handler
 *
 * @brief Base request API handler
 */
AppLocale::requireComponents(LOCALE_COMPONENT_PKP_API);
import('lib.pkp.classes.handler.PKPHandler');

use \Slim\App;
import('lib.pkp.classes.core.APIResponse');

class APIHandler extends PKPHandler {
	protected $_app;
	protected $_request;
	protected $_endpoints = array();
	protected $_slimRequest = null;

	/**
	 * The endpoint pattern for this handler
	 *
	 * @param string
	 */
	protected $_pathPattern;

	/**
	 * The unique endpoint string for this handler
	 *
	 * @param string
	 */
	protected $_handlerPath;

	/**
	 * Constructor
	 */
	function __construct() {
		parent::__construct();
		import('lib.pkp.classes.security.authorization.internal.ApiAuthorizationMiddleware');
		$this->_app = new \Slim\App([
			// Load custom response handler
			'response' => function($c) {
				return new APIResponse();
			},
			'settings' => array(
				// we need access to route within middleware
				'determineRouteBeforeAppMiddleware' => true,
			)
		]);
		$this->_app->add(new ApiAuthorizationMiddleware($this));
		$this->_request = Application::getRequest();
		$this->setupEndpoints();
	}

	/**
	 * Return PKP request object
	 *
	 * @return PKPRequest
	 */
	public function getRequest() {
		return $this->_request;
	}

	/**
	 * Return Slim request object
	 *
	 * @return SlimRequest|null
	 */
	public function getSlimRequest() {
		return $this->_slimRequest;
	}

	/**
	 * Set Slim request object
	 *
	 */
	public function setSlimRequest($slimRequest) {
		return $this->_slimRequest = $slimRequest;
	}

	/**
	 * Get the Slim application.
	 * @return App
	 */
	public function getApp() {
		return $this->_app;
	}

	/**
	 * Get the endpoint pattern for this handler
	 *
	 * Compiles the URI path pattern from the context, api version and the
	 * unique string for the this handler.
	 *
	 * @return string
	 */
	public function getEndpointPattern() {

		if (!isset($this->_pathPattern)) {
			$this->_pathPattern = '/{contextPath}/api/{version}/' . $this->_handlerPath;
		}

		return $this->_pathPattern;
	}

	/**
	 * Get the entity ID for a specified parameter name.
	 * (Parameter names are generally defined in authorization policies
	 * @return int|string?
	 */
	public function getEntityId($parameterName) {
		assert(false);
		return null;
	}

	/**
	 * setup endpoints
	 */
	public function setupEndpoints() {
		$app = $this->getApp();
		$endpoints = $this->getEndpoints();
		foreach ($endpoints as $method => $definitions) {
			foreach ($definitions as $parameters) {
				$method = strtolower($method);
				$pattern = $parameters['pattern'];
				$handler = $parameters['handler'];
				$roles = isset($parameters['roles']) ? $parameters['roles'] : null;
				$app->$method($pattern, $handler)->setName($handler[1]);
				if (!is_null($roles) && is_array($roles)) {
					$this->addRoleAssignment($roles, $handler[1]);
				}
			}
		}
	}

	/**
	 * Returns the list of endpoints
	 *
	 * @return array
	 */
	public function getEndpoints() {
		return $this->_endpoints;
	}

	/**
	 * Fetches parameter value
	 * @param string $parameterName
	 */
	public function getParameter($parameterName) {
		$slimRequest = $this->getSlimRequest();
		if ($slimRequest == null) {
			return null;
		}

		$arguments = $slimRequest->getAttribute('route')->getArguments();
		if (isset($arguments[$parameterName])) {
			return $arguments[$parameterName];
		}

		$queryParams = $slimRequest->getQueryParams();
		if (isset($queryParams[$parameterName])) {
			return $queryParams[$parameterName];
		}

		return null;
	}
}

?>

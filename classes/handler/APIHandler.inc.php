<?php

/**
 * @file lib/pkp/classes/handler/APIHandler.inc.php
 *
 * Copyright (c) 2014-2016 Simon Fraser University Library
 * Copyright (c) 2003-2016 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class APIHandler
 * @ingroup handler
 *
 * @brief Base request API handler
 */

import('lib.pkp.classes.handler.PKPHandler');

use \Slim\App;

class APIHandler extends PKPHandler {
	protected $_app;
	protected $_request;
	protected $_endpoints = array();

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
	function APIHandler() {
		parent::__construct();
		$this->_app = new \Slim\App;
		$this->_request = Application::getRequest();
		$this->setupEndpoints();
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
				$app->$method($pattern, $handler);
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
}

?>

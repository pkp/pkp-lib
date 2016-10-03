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

	/**
	 * Constructor
	 */
	function APIHandler() {
		$this->_app = new \Slim\App;
		$this->_request = Application::getRequest();
		parent::PKPHandler();
	}

	/**
	 * Get the Slim application.
	 * @return App
	 */
	public function getApp() {
		return $this->_app;
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
}

?>

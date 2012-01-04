<?php

/**
 * @file classes/citation/WebServiceRequest.inc.php
 *
 * Copyright (c) 2000-2012 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class WebServiceRequest
 * @ingroup webservice
 *
 * @brief Represents a web service request.
 */

// $Id$

class WebServiceRequest {
	/** @var string */
	var $_url;

	/** @var array key value pairs */
	var $_params;

	/** @var string HTTP request method */
	var $_method;

	/**
	 * Constructor
	 */
	function WebServiceRequest($url, $params = array(), $method = 'GET') {
		$this->_url = $url;
		$this->_params = $params;
		$this->_method = $method;
	}

	//
	// Getters and Setters
	//
	/**
	 * Get the web service URL
	 * @return string
	 */
	function getUrl() {
		return $this->_url;
	}

	/**
	 * Set the web service URL
	 * @param $url string
	 */
	function setUrl($url) {
		$this->_url = $url;
	}

	/**
	 * Get the request parameters
	 * @return array
	 */
	function &getParams() {
		return $this->_params;
	}

	/**
	 * Set the request parameters
	 * @param $params array
	 */
	function setParams(&$params) {
		$this->_params =& $params;
	}

	/**
	 * Get the request method
	 * @return string
	 */
	function getMethod() {
		return $this->_method;
	}

	/**
	 * Set the request method
	 * @param $method string
	 */
	function setMethod($method) {
		$this->_method = $method;
	}
}
?>
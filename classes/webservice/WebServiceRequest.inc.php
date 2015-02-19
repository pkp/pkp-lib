<?php

/**
 * @file classes/webservice/WebServiceRequest.inc.php
 *
 * Copyright (c) 2013-2015 Simon Fraser University Library
 * Copyright (c) 2000-2015 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class WebServiceRequest
 * @ingroup webservice
 *
 * @brief Represents a web service request.
 */


class WebServiceRequest {
	/** @var string */
	var $_url;

	/** @var mixed array (key value pairs) or string */
	var $_params;

	/** @var string HTTP request method */
	var $_method;

	/** @var string Accept header */
	var $_accept;

	/** @var array Additional request headers */
	var $_headers = array();

	/** @var boolean Whether to make an asynchronous request */
	var $_async = false;

	/** @var boolean Whether to clean the request result */
	var $_cleanResult = true;

	/**
	 * Constructor
	 *
	 * @param $url string The request URL
	 * @param $params mixed array (key value pairs) or string request parameters
	 * @param $method string GET or POST
	 */
	function WebServiceRequest($url, $params, $method = 'GET') {
		$this->_url = $url;
		$this->_params = $params;
		$this->_method = $method;
		$this->_accept = 'text/xml, */*';
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
	 * @return mixed array (key value pairs) or string
	 */
	function &getParams() {
		return $this->_params;
	}

	/**
	 * Set the request parameters
	 * @param $params mixed array (key value pairs) or string
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

	/**
	 * Set the accept header value
	 * @param $accept string
	 */
	function setAccept($accept) {
		$this->_accept = $accept;
	}

	/**
	 * Get the accept header value
	 * @return string
	 */
	function getAccept() {
		return $this->_accept;
	}

	/**
	 * Set an additional request header.
	 * @param $header string
	 * @param $content string
	 */
	function setHeader($header, $content) {
		$this->_headers[$header] = $content;
	}

	/**
	 * Check whether the given header is
	 * present in the request.
	 *
	 * The check is case insensitive.
	 *
	 * @param $header string
	 */
	function hasHeader($header) {
		$header = strtolower($header);
		foreach($this->_headers as $h => $dummy) {
			if ($header == strtolower($h)) return true;
		}
		return false;
	}

	/**
	 * Get additional request headers.
	 */
	function getHeaders() {
		return $this->_headers;
	}

	/**
	 * Set whether to make an async request.
	 * (POST requests only)
	 * @param $async boolean
	 */
	function setAsync($async) {
		$this->_async = (boolean)$async;
	}

	/**
	 * Whether to make an async request.
	 * @return boolean
	 */
	function getAsync() {
		return $this->_async;
	}

	/**
	 * Whether to clean the request result.
	 * @param $cleanResult
	 */
	function setCleanResult($cleanResult) {
		$this->_cleanResult = $cleanResult;
	}

	/**
	 * Get whether to clean the request result.
	 * @return boolean
	 */
	function getCleanResult() {
		return $this->_cleanResult;
	}
}
?>

<?php

/**
 * @file classes/citation/WebService.inc.php
 *
 * Copyright (c) 2000-2012 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class WebService
 * @ingroup webservice
 *
 * @brief Abstract base class for a web service.
 */

// $Id$

define('WEBSERVICE_RETRIES', 3);
define('WEBSERVICE_MICROSECONDS_BEFORE_RETRY', 100000);

import('webservice.WebServiceRequest');

class WebService {
	/**
	 * Call a web service
	 * @param $webServiceRequest WebServiceRequest
	 * @return string the result of the web service or null in case of an error.
	 */
	function &call(&$webServiceRequest) {
		assert(is_a($webServiceRequest, 'WebServiceRequest'));

		switch($webServiceRequest->getMethod()) {
			case 'POST':
				$result = $this->_callPostWebService($webServiceRequest);
				break;

			case 'GET':
				$result = $this->_callGetWebService($webServiceRequest);
				break;

			default:
				// We currently only support GET and POST requests
				assert(false);
		}

		// Catch web service errors
		$nullVar = null;
		if (!$result) return $nullVar;

		// Clean the result
		$result = stripslashes($result);
		if ( Config::getVar('i18n', 'charset_normalization') == 'On' && !String::utf8_compliant($result) ) {
			$result = String::utf8_normalize($result);
		}

		return $result;
	}


	//
	// Private helper methods
	//
	/**
	 * Call a POST based web services
	 * @param $webServiceRequest WebServiceRequest
	 * @return string the web service result or null on failure
	 */
	function _callPostWebService($webServiceRequest) {
		$url = $webServiceRequest->getUrl();
		$postOptions = $webServiceRequest->getParams();

		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_HTTPHEADER, array('Accept: text/xml, */*'));
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_POST, 1);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $postOptions);

		// Relax timeout a little bit for slow servers
		curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);

		// POST to the web service
		for ($retries = 0; $retries < WEBSERVICE_RETRIES; $retries++) {
			if ($result = @curl_exec($ch)) break;

			// Wait for a short interval before trying again
			usleep(WEBSERVICE_MICROSECONDS_BEFORE_RETRY);
		}

		curl_close($ch);
		return $result;
	}

	/**
	 * Call a GET based web services
	 * @param $webServiceRequest WebServiceRequest
	 * @return string the web service result or null on failure
	 */
	function _callGetWebService(&$webServiceRequest) {
		// Prepare the request URL
		$url = $webServiceRequest->getUrl();
		$queryString = '';
		foreach($webServiceRequest->getParams() as $key => $value) {
			if (!empty($queryString)) {
				$queryString .= '&';
			} else {
				$queryString = '?';
			}
			$queryString .= urlencode($key).'='.urlencode($value);
		}
		$url = $url.$queryString;

		$oldSocketTimeout = ini_set('default_socket_timeout', 120);

		// GET from the web service
		for ($retries = 0; $retries < WEBSERVICE_RETRIES; $retries++) {
			if ($result = @file_get_contents($url)) break;

			// Wait for a short interval before trying again
			usleep(WEBSERVICE_MICROSECONDS_BEFORE_RETRY);
		}

		if ($oldSocketTimeout !== false) ini_set('default_socket_timeout', $oldSocketTimeout);
		return $result;
	}
}
?>
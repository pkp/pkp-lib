<?php

/**
 * @file classes/webservice/JSONWebService.inc.php
 *
 * Copyright (c) 2000-2013 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class JSONWebService
 * @ingroup webservice
 *
 * @brief A web service that returns data from JSON response.
 */


import('lib.pkp.classes.webservice.WebService');
import('lib.pkp.classes.core.JSONMessage');

class JSONWebService extends WebService {

	/**
	 * Constructor
	 */
	function JSONWebService() {
		parent::WebService();
	}

	/**
	 * @see WebService::call()
	 * @param $webServiceRequest WebServiceRequest
	 * @return array The result of the web service or null in case of an error.
	 */
	function &call(&$webServiceRequest) {
		// Call the web service
		$jsonResult = parent::call($webServiceRequest);

		// Catch web service errors
		if (is_null($jsonResult)) return $jsonResult;

		$resultArray = json_decode($jsonResult, true);

		// Catch decoding errors.
		if (!is_array($resultArray)) return null;

		return $resultArray;
	}
}
?>

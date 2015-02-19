<?php

/**
 * @file classes/core/JSONManager.inc.php
 *
 * Copyright (c) 2013-2015 Simon Fraser University Library
 * Copyright (c) 2000-2015 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class JSONManager
 * @ingroup core
 *
 * @brief Class to build and manipulate JSON (Javascript Object Notation) objects.
 *
 */


class JSONManager {
	/**
	 * Constructor.
	 */
	function JSONManager() {
	}

	/**
	 * PHP4 compatible version of json_encode()
	 *
	 * @param $value mixed The content to encode.
	 * @return string The encoded content.
	 */
	function encode($value = false) {
		// Use the native function if possible
		if (function_exists('json_encode')) return json_encode($value);

		// Otherwise fall back on the JSON services library
		$jsonServices = $this->_getJsonServices();
		return $jsonServices->encode($value);
	}

	/**
	 * Decode a JSON string.
	 * @param $json string The content to decode.
	 * @return mixed
	 */
	function decode($json) {
		// Use the native function if possible
		if (function_exists('json_decode')) return json_decode($json);

		// Otherwise fall back on the JSON services library
		$jsonServices = $this->_getJsonServices();
		return $jsonServices->decode($json);
	}

	/**
	 * Private function to get the JSON services library
	 */
	function _getJsonServices() {
		require_once('lib/pkp/lib/json/JSON.php');
		return new Services_JSON();
	}
}

?>

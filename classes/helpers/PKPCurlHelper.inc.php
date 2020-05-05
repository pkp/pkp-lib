<?php

/**
 * @file classes/helpers/PKPCurlHelper.inc.php
 *
 * Copyright (c) 2014-2020 Simon Fraser University
 * Copyright (c) 2000-2020 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PKPCurlHelper
 * @ingroup helpers
 *
 * @brief Helper for curl usage.
 */

class PKPCurlHelper {
	/**
	 * Retrieve a reference to the specified DAO.
	 * @param string $url (optional) The url that the curl instance is going to be initialised with 
	 * @param bool $useProxySettings (optional: true) True if the proxy settings should be considered
	 * @return resource
	 */
	static function getCurlObject($url = null, $useProxySettings = true) {
		// Set up common CURL request details
		$curl = curl_init($url);

		// Use proxy if configured
		if ($useProxySettings && $httpProxyHost = Config::getVar('proxy', 'http_host')) {
			curl_setopt($curl, CURLOPT_PROXY, $httpProxyHost);
			curl_setopt($curl, CURLOPT_PROXYPORT, Config::getVar('proxy', 'http_port', '80'));
			if ($username = Config::getVar('proxy', 'username')) {
				curl_setopt($curl, CURLOPT_PROXYUSERPWD, $username . ':' . Config::getVar('proxy', 'password'));
			}
		}

		// Use cainfo if configured
		if ($cainfo = Config::getVar('curl', 'cainfo')) {
			curl_setopt($curl, CURLOPT_CAINFO, $cainfo);
		}

		return $curl;
	}
}



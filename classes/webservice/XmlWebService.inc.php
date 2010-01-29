<?php

/**
 * @file classes/citation/XmlWebService.inc.php
 *
 * Copyright (c) 2000-2010 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class XmlWebService
 * @ingroup webservice
 *
 * @brief A web service that returns XML data.
 */

// $Id$

import('webservice.WebService');

class XmlWebService extends WebService {
	/**
	 * @see WebService::call()
	 * @param $webServiceRequest WebServiceRequest
	 * @return DOMDocument|string the result of the web service or null in case of an error.
	 */
	function &call(&$webServiceRequest) {
		// Call the web service
		$xmlResult = parent::call($webServiceRequest);

		// Create DOM document
		if (checkPhpVersion('5.0.0') && extension_loaded('dom')) {
			// In PHP5 we return a DOM
			$resultDOM = new DOMDocument('1.0', Config::getVar('i18n', 'client_charset'));
			// Try to handle non-well-formed responses
			$resultDOM->recover = true;
			$resultDOM->loadXML($xmlResult);
			$result =& $resultDOM;
		} elseif(checkPhpVersion('4.1.0')) {
			// In PHP4 we return the xml string itself
			$result =& $xmlResult;
		} else {
			// Unsupported PHP configuration
			assert(null);
		}

		return $result;
	}
}
?>
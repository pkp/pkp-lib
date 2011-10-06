<?php

/**
 * @file lib/pkp/tests/OaiWebServiceTestCase.inc.php
 *
 * Copyright (c) 2000-2011 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class OaiWebServiceTestCase
 * @ingroup tests
 *
 * @brief Base test class for OAI functional tests.
 */


import('lib.pkp.tests.PKPTestCase');
import('lib.pkp.classes.xslt.XSLTransformer');
import('lib.pkp.classes.webservice.WebServiceRequest');
import('lib.pkp.classes.webservice.XmlWebService');

class OaiWebServiceTestCase extends PKPTestCase {
	protected $baseUrl, $webService, $webServiceRequest;

	public function setUp() {
		$this->baseUrl = Config::getVar('debug', 'webtest_base_url') . '/index.php/test/oai';;

		// Instantiate a web service.
		$this->webService = new XmlWebService();
		$this->webService->setReturnType(XSL_TRANSFORMER_DOCTYPE_DOM);

		// Instantiate a web service request.
		$this->webServiceRequest = new WebServiceRequest($this->baseUrl);
	}

	protected function &getXPath($namespaces) {
		$namespaces['oai'] = 'http://www.openarchives.org/OAI/2.0/';

		// Call the web service
		$dom =& $this->webService->call($this->webServiceRequest);

		// Instantiate and configure XPath object.
		$xPath = new DOMXPath($dom);
		foreach ($namespaces as $prefix => $uri) {
			$xPath->registerNamespace($prefix, $uri);
		}

		return $xPath;
	}
}
?>
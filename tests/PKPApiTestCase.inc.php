<?php
/**
 * @defgroup tests Tests
 * Tests and test framework for unit and integration tests.
 */

/**
 * @file tests/PKPAPiTestCase.inc.php
 *
 * Copyright (c) 2014-2018 Simon Fraser University
 * Copyright (c) 2000-2018 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class PKPAPiTestCase
 * @ingroup tests
 *
 * @brief Class that implements functionality common to all PKP unit test cases.
 *
 * NB: PHPUnit 3.x requires PHP 5.2 or later so we can use PHP5 constructs.
 */

import('lib.pkp.tests.PKPTestHelper');

abstract class PKPAPiTestCase extends PHPUnit_Framework_TestCase {
	/** @var \GuzzleHttp\Client Guzzle client */
	protected $_client = null;
	/** @var array configuration array */
	protected $_config = null;
	/** @var int ID of an entity not in the system */
	protected $_invalidId = 99999;

	/**
	 * Helper method to collect items from endpoint
	 * @param $endpoint string api endpoint
	 * @return array
	 */
	protected function _getAvailableItemsFromEndpoint($endpoint) {
		$response = $this->_sendRequest('GET', $endpoint);
		$this->assertEquals(200, $response->getStatusCode());
		$data = $this->_getResponseData($response);
		return $data['items'];
	}

	/**
	 * Helper method returning the first entity found
	 * @throws Exception
	 * @return stdClass
	 */
	protected function _getFirstEntity($endpoint) {
		$entities = $this->_getAvailableItemsFromEndpoint($endpoint);
		if (empty($entities)) {
			throw new Exception('Unable to collect a list available entities for endpoint:' . $endpoint);
		}
		$this->assertTrue(is_array($entities));
		$entity = array_shift($entities);
		$this->assertObjectHasAttribute('id', $entity);
		return $entity;
	}

	/**
	 * @copydoc PHPUnit_Framework_TestCase::setUp()
	 */
	public function setUp()
	{
		$host = getenv('BASEURL');
		$prefix = getenv('APIPREFIX');
		$apiKey = getenv('APIKEY');
		if (!$host || !$prefix || !$apiKey) {
			throw new Exception("Could not load required configuration values. Please define BASEURL, APIPREFIX and APIKEY environment variables to continue."); 
		}
		$this->_config = array(
			'host' => $host,
			'prefix' => $prefix,
			'apiKey' => $apiKey,
		);
		$this->_client = new \GuzzleHttp\Client([
			'base_uri' => $this->_config['host'],
		]);
	}

	/**
	 * @copydoc PHPUnit_Framework_TestCase::tearDown()
	 */
	public function tearDown() {
		$this->_client = null;
	}

	/**
	 * Sends HTTP request to a specific relative URL
	 * @param $method string request method
	 * @param $endpoint API endpoint
	 * @param $params array request parameters
	 * @param $protected boolean whether the endpoint requires authentication
	 * @return ResponseInterface
	 */
	protected function _sendRequest($method, $endpoint, $params = array(), $protected = true) {
		if ($protected) {
			$params['apiToken'] = $this->_config['apiKey'];
		}
		$endpoint = trim("{$this->_config['prefix']}{$endpoint}", "/");
		return $this->_client->request(
			$method,
			$endpoint,
			array(
				'query' => $params,
			)
		);
	}

	/**
	 * Extracts data from HTTP request returning JSON
	 * @param ResponseInterface $response
	 * @return array
	 */
	protected function _getResponseData($response) {
		$body = $response->getBody();
		$this->assertJson($body->getContents());
		$data = (array) json_decode($body);
		return $data;
	}
}

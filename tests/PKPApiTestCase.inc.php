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

// Include PHPUnit
import('lib.pkp.tests.PKPTestHelper');

abstract class PKPAPiTestCase extends PHPUnit_Framework_TestCase {
	/** @var \GuzzleHttp\Client Guzzle client */
	protected $client = null;
	/** @var array configuration array */
	protected $config = null;

	/**
	 * @copydoc PHPUnit_Framework_TestCase::setUp()
	 */
	public function setUp()
	{
		$configFilePath = dirname(dirname(dirname(dirname(__FILE__)))) . '/tests/api/config.json';
		$this->config = (array) json_decode(file_get_contents($configFilePath));
		$this->client = new \GuzzleHttp\Client([
			'base_uri' => $this->config['host'],
		]);
	}

	/**
	 * @copydoc PHPUnit_Framework_TestCase::tearDown()
	 */
	public function tearDown() {
		$this->client = null;
	}

	/**
	 * Sends HTTP request to a specific relative URL
	 * @param $method string request method
	 * @param $endpoint API endpoint
	 * @param $params array request parameters
	 * @param $protected boolean whether the endpoint requires authentication
	 */
	protected function sendRequest($method, $endpoint, $params = array(), $protected = true) {
		if ($protected) {
			$params['apiToken'] = $this->config['apiKey'];
		}
		$endpoint = trim("{$this->config['prefix']}{$endpoint}", "/");
		return $this->client->request(
			$method,
			$endpoint,
			array(
				'query' => $params,
			)
		);
	}
}
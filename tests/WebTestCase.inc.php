<?php

/**
 * @file lib/pkp/tests/WebTestCase.inc.php
 *
 * Copyright (c) 2000-2011 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class WebTestCase
 * @ingroup tests
 *
 * @brief Base test class for Selenium functional tests.
 */

require_once 'PHPUnit/Extensions/SeleniumTestCase.php';
import('lib.pkp.tests.PKPTestHelper');

class WebTestCase extends PHPUnit_Extensions_SeleniumTestCase {
	protected $baseUrl;


	/**
	 * Override this method if you want to backup/restore
	 * tables before/after the test.
	 * @return array A list of tables to backup and restore.
	 */
	protected function getAffectedTables() {
		return array();
	}

	/**
	 * @see PHPUnit_Framework_TestCase::setUp()
	 */
	protected function setUp() {
		// See PKPTestCase::setUp() for an explanation
		// of this code.
		if(function_exists('_array_change_key_case')) {
			global $ADODB_INCLUDED_LIB;
			$ADODB_INCLUDED_LIB = 1;
		}

		$this->setBrowser('*firefox');
		$this->baseUrl = Config::getVar('debug', 'webtest_base_url');
		$this->setBrowserUrl($this->baseUrl . '/');

		PKPTestHelper::backupTables($this->getAffectedTables(), $this);
		parent::setUp();
	}

	/**
	 * @see PHPUnit_Framework_TestCase::tearDown()
	 */
	protected function tearDown() {
		parent::tearDown();
		PKPTestHelper::restoreTables($this->getAffectedTables(), $this);
	}

	/**
	 * Log in as admin user.
	 */
	protected function logIn() {
		$this->open($this->baseUrl.'/index.php/test/login/signIn?username=admin&password='
			.Config::getVar('debug', 'webtest_admin_pw'));
	}

	/**
	 * Check for verification errors and
	 * clean the verification error list.
	 */
	protected function verified() {
		if (!$verified = empty($this->verificationErrors)) {
			$this->verificationErrors = array();
		}
		return $verified;
	}

	/**
	 * Make the exception message more informative.
	 * @param $e Exception
	 * @param $testObject string
	 * @return Exception
	 */
	protected function improveException($e, $testObject) {
		$improvedMessage = "Error while testing $testObject: ".$e->getMessage();
		if (is_a($e, 'PHPUnit_Framework_ExpectationFailedException')) {
			$e = new PHPUnit_Framework_ExpectationFailedException($improvedMessage, $e->getComparisonFailure());
		} elseif (is_a($e, 'PHPUnit_Framework_Exception')) {
			$e = new PHPUnit_Framework_Exception($improvedMessage, $e->getCode());
		}
		return $e;
	}
}
?>
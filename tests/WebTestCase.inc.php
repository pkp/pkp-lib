<?php

/**
 * @file lib/pkp/tests/WebTestCase.inc.php
 *
 * Copyright (c) 2000-2013 John Willinsky
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
	protected $baseUrl, $password;


	/**
	 * Override this method if you want to backup/restore
	 * tables before/after the test.
	 * @return array A list of tables to backup and restore.
	 */
	protected function getAffectedTables() {
		return array();
	}

	/**
	 * @copydoc PHPUnit_Framework_TestCase::setUp()
	 */
	protected function setUp() {
		// See PKPTestCase::setUp() for an explanation
		// of this code.
		if(function_exists('_array_change_key_case')) {
			global $ADODB_INCLUDED_LIB;
			$ADODB_INCLUDED_LIB = 1;
		}

		// Retrieve and check configuration.
		$this->baseUrl = Config::getVar('debug', 'webtest_base_url');
		$this->password = Config::getVar('debug', 'webtest_admin_pw');
		if (empty($this->baseUrl) || empty($this->password)) {
			$this->markTestSkipped(
				'Please set webtest_base_url and webtest_admin_pw in your ' .
				'config.php\'s [debug] section to the base url and admin ' .
				'password of your test server.'
			);
		}

		$this->setBrowser('*chrome'); // This is not Google Chrome but the
		                              // Firefox Heightened Privilege mode
		                              // required e.g. for file upload.
		$this->setBrowserUrl($this->baseUrl . '/');

		PKPTestHelper::backupTables($this->getAffectedTables(), $this);

		$cacheManager = CacheManager::getManager();
		$cacheManager->flush(null, CACHE_TYPE_FILE);
		$cacheManager->flush(null, CACHE_TYPE_OBJECT);

		// Clear ADODB's cache
		$userDao = DAORegistry::getDAO('UserDAO'); // As good as any
		$userDao->flushCache();

		parent::setUp();
	}

	/**
	 * @copydoc PHPUnit_Framework_TestCase::tearDown()
	 */
	protected function tearDown() {
		parent::tearDown();
		PKPTestHelper::restoreTables($this->getAffectedTables(), $this);
	}

	/**
	 * Log in as passed user, with the passed password. If none is passed,
	 * log in as admin user.
	 */
	protected function logIn($username = 'admin', $password = null) {
		if (is_null($password)) {
			$password = $this->password;
		}

		$this->open($this->baseUrl.'/index.php/test/login/signIn?username='. $username .'&password='
			.$password);
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
	 * Open a URL but only if it's not already
	 * the current location.
	 * @param $url string
	 */
	protected function verifyAndOpen($url) {
		$this->verifyLocation('exact:' . $url);
		if (!$this->verified()) {
			$this->open($url);
		}
		$this->waitForLocation($url);
	}

	/**
	 * Types a text into an input field.
	 *
	 * This is done using low-level methods in a way
	 * to simulate actual key-press events that can
	 * trigger autocomplete events or similar.
	 *
	 * @param $box string the locator of the box
	 * @param $letters string the text to type
	 */
	protected function typeText($box, $letters) {
		$this->focus($box);
		$currentContent = '';
		foreach(str_split($letters) as $letter) {
			// The following hack makes jQueryUI behave as
			// if typing in letters manually.
			$currentContent .= $letter;
			$this->type($box, $currentContent);
			$this->typeKeys($box, $letter);
			usleep(300000);
		}
		// Fix one more timing problem on the test server:
		sleep(1);
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

	/**
	 * Save an Ajax form, waiting for the loading sprite
	 * to be hidden to continue the test execution.
	 * @param $formLocator String
	 */
	protected function submitAjaxForm($formId) {
		$this->assertElementPresent($formId, 'The passed form locator do not point to any form element at the current page.');
		$this->click('css=#' . $formId . ' #submitFormButton');

		$progressIndicatorSelector = '#' . $formId . ' .formButtons .pkp_helpers_progressIndicator';

		// First make sure that the progress indicator is visible.
		$this->waitForCondition("selenium.browserbot.getUserWindow().jQuery('$progressIndicatorSelector:visible').length == 1", 2000);

		// Wait until it disappears (the form submit process is finished).
		$this->waitForCondition("selenium.browserbot.getUserWindow().jQuery('$progressIndicatorSelector:visible').length == 0");
	}
}
?>

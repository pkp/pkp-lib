<?php

/**
 * @file lib/pkp/tests/WebTestCase.inc.php
 *
 * Copyright (c) 2013-2014 Simon Fraser University Library
 * Copyright (c) 2000-2014 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class WebTestCase
 * @ingroup tests
 *
 * @brief Base test class for Selenium functional tests.
 */

import('lib.pkp.tests.PKPTestHelper');

class WebTestCase extends PHPUnit_Extensions_SeleniumTestCase {
	/** @var string Base URL provided from environment */
	static protected $baseUrl;

	/** @var int Timeout limit for tests in seconds */
	static protected $timeout;

	protected $captureScreenshotOnFailure = true;
	protected $screenshotPath, $screenshotUrl;


	/**
	 * Override this method if you want to backup/restore
	 * tables before/after the test.
	 * @return array A list of tables to backup and restore.
	 */
	protected function getAffectedTables() {
		return array();
	}

	/**
	 * @copydoc PHPUnit_Framework_TestCase::setUpBeforeClass()
	 */
	public static function setUpBeforeClass() {
		// Retrieve and check configuration.
		self::$baseUrl = getenv('BASEURL');
		self::$timeout = (int) getenv('TIMEOUT');
		if (!self::$timeout) self::$timeout = 30; // Default 30 seconds
		parent::setUpBeforeClass();
	}

	/**
	 * @copydoc PHPUnit_Framework_TestCase::setUp()
	 */
	function setUp() {
		$screenshotsFolder = 'lib/pkp/tests/results';
		$this->screenshotPath = BASE_SYS_DIR . DIRECTORY_SEPARATOR . $screenshotsFolder;
		$this->screenshotUrl = Config::getVar('general', 'base_url') . '/' . $screenshotsFolder;

		if (empty(self::$baseUrl)) {
			$this->markTestSkipped(
				'Please set BASEURL as an environment variable.'
			);
		}

		$this->setTimeout(self::$timeout);

		// See PKPTestCase::setUp() for an explanation
		// of this code.
		if(function_exists('_array_change_key_case')) {
			global $ADODB_INCLUDED_LIB;
			$ADODB_INCLUDED_LIB = 1;
		}

		// This is not Google Chrome but the Firefox Heightened
		// Privilege mode required e.g. for file upload.
		$this->setBrowser('*chrome');

		$this->setBrowserUrl(self::$baseUrl . '/');
		if (Config::getVar('general', 'installed') && !defined('SESSION_DISABLE_INIT')) {
			PKPTestHelper::backupTables($this->getAffectedTables(), $this);
		}

		$cacheManager = CacheManager::getManager();
		$cacheManager->flush(null, CACHE_TYPE_FILE);
		$cacheManager->flush(null, CACHE_TYPE_OBJECT);

		// Clear ADODB's cache
		if (Config::getVar('general', 'installed') && !defined('SESSION_DISABLE_INIT')) {
			$userDao = DAORegistry::getDAO('UserDAO'); // As good as any
			$userDao->flushCache();
		}

		parent::setUp();
	}

	/**
	 * @copydoc PHPUnit_Framework_TestCase::tearDown()
	 */
	protected function tearDown() {
		parent::tearDown();
		if (Config::getVar('general', 'installed') && !defined('SESSION_DISABLE_INIT')) {
			PKPTestHelper::restoreTables($this->getAffectedTables(), $this);
		}
	}

	/**
	 * Log in.
	 * @param $username string
	 * @param $password string Optional -- defaults to usernameusername
	 */
	protected function logIn($username, $password = null) {
		// Default to twice username (convention for test data)
		if ($password === null) $password = $username . $username;

		$this->open(self::$baseUrl);
		$this->waitForElementPresent('link=Login');
		$this->clickAndWait('link=Login');
		$this->waitForElementPresent('id=loginUsername');
		$this->type('id=loginUsername', $username);
		$this->type('id=loginPassword', $password);
		$this->clickAndWait('xpath=(//table[@id=\'signinTable\']//input[@value=\'Login\'])');
		$this->waitForTextPresent('You are logged in as...');
	}

	/**
	 * Self-register a new user account.
	 * @param $data array
	 */
	protected function register($data) {
		// Check that the required parameters are provided
		foreach (array(
			'username', 'firstName', 'lastName'
		) as $paramName) {
			$this->assertTrue(isset($data[$paramName]));
		}

		$username = $data['username'];
		$data = array_merge(array(
			'email' => $username . '@mailinator.com',
			'password' => $username . $username,
			'password2' => $username . $username,
			'roles' => array()
		), $data);

		// Find registration page
		$this->open(self::$baseUrl);
		$this->clickAndWait('link=Register');

		// Fill in user data
		$this->type('id=firstName', $data['firstName']);
		$this->type('id=lastName', $data['lastName']);
		$this->type('id=username', $username);
		$this->type('id=email', $data['email']);
		$this->type('id=confirmEmail', $data['email']);
		$this->type('id=password', $data['password']);
		$this->type('id=password2', $data['password2']);
		if (isset($data['affiliation'])) $this->type('id=affiliation', $data['affiliation']);
		if (isset($data['country'])) $this->select('id=country', $data['country']);

		// Select the specified roles
		foreach (array('Author', 'Reader', 'Reviewer') as $role) {
			if (!in_array($role, $data['roles'])) continue;

			$this->click('id=registerAs' . $role);
		}

		// Save the new user
		$this->clickAndWait('css=input.button.defaultButton');
	}

	/**
	 * Log out.
	 */
	protected function logOut() {
		$this->open(self::$baseUrl);
		$this->click('link=Log Out');
		$this->waitForElementPresent('link=Login');
		$this->waitJQuery();
	}

	/**
	 * Log in as an Editor and find the specified submission.
	 * @param $username string
	 * @param $password string (null to presume twice-username)
	 * @param $title string
	 */
	protected function findSubmissionAsEditor($username, $password = null, $title) {
		if ($password === null) $password = $username . $username;
		$this->logIn($username, $password);

		// Use the search to find the submission
		$this->type('//div[@id=\'main\']//input[@name=\'search\']', $title);
		$this->select('//div[@id=\'main\']//select[@name=\'searchMatch\']', 'label=is');
		$this->clickAndWait('//div[@id=\'main\']//input[@value=\'Search\']');
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

	/**
	 * Upload a file using an input type=file.
	 * @param $file string Path to the file
	 * @param $inputSelector string Selenium selector for file input control
	 * @param $buttonSelector string Selenium selector for upload button
	 */
	protected function uploadFile($file, $inputSelector = '//input[@type="file"]', $buttonSelector = '//input[@value=\'Upload\']') {
		$this->assertTrue(file_exists($file), 'Test file does not exist.');
		$testFile = realpath($file);
		$fileName = basename($testFile);

		$this->waitForElementPresent($inputSelector);
		$this->attachFile($inputSelector, "file://$testFile");
		$this->clickAndWait($buttonSelector);
	}

	/**
	 * Log in as author user.
	 */
	protected function logAuthorIn() {
		$authorUser = 'author';
		$authorPw = 'author';
		$this->logIn($authorUser, $authorPw);
	}

	/**
	 * Type a value into a TinyMCE control.
	 * @param $controlPrefix string Prefix of control name
	 * @param $value string Value to enter into control
	 */
	protected function typeTinyMCE($controlPrefix, $value) {
		sleep(2); // Give TinyMCE a chance to load/init
		$this->runScript("tinyMCE.get($('textarea[id^=\\'" . htmlspecialchars($controlPrefix) . "\\']').attr('id')).setContent('" . htmlspecialchars($value) . "');");
	}

	/**
	 * Add a tag to a TagIt-enabled control
	 * @param $controlPrefix string Prefix of control name
	 * @param $value string Value of new tag
	 */
	protected function addTag($controlPrefix, $value) {
		$this->runScript('$(\'[id^=\\\'' . htmlspecialchars($controlPrefix) . '\\\']\').tagit(\'createTag\', \'' . htmlspecialchars($value) . '\');');
	}

	/**
	 * Wait for active JQuery requests to complete.
	 */
	protected function waitJQuery() {
		$this->waitForCondition('window.jQuery.active == 0');
	}
}
?>

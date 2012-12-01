<?php
/**
 * @defgroup tests
 */

/**
 * @file tests/PKPTestCase.inc.php
 *
 * Copyright (c) 2013 Simon Fraser University Library
 * Copyright (c) 2000-2013 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class PKPTestCase
 * @ingroup tests
 *
 * @brief Class that implements functionality common to all PKP unit test cases.
 *
 * NB: PHPUnit 3.x requires PHP 5.2 or later so we can use PHP5 constructs.
 */

// Include PHPUnit
import('lib.pkp.tests.PKPTestHelper');

abstract class PKPTestCase extends PHPUnit_Framework_TestCase {
	private $daoBackup = array(), $registryBackup = array();

	/**
	 * Override this method if you want to backup/restore
	 * DAOs before/after the test.
	 * @return array A list of DAO names to backup and restore.
	 */
	protected function getMockedDAOs() {
		return array();
	}

	/**
	 * Override this method if you want to backup/restore
	 * registry entries before/after the test.
	 * @return array A list of registry keys to backup and restore.
	 */
	protected function getMockedRegistryKeys() {
		return array();
	}

	/**
	 * @see PHPUnit_Framework_TestCase::setUp()
	 */
	protected function setUp() {
		$this->setBackupGlobals(true);

		// Rather than using "include_once()", ADOdb uses
		// a global variable to maintain the information
		// whether its library has been included before (wtf!).
		// This causes problems with PHPUnit as PHPUnit will
		// delete all global state between two consecutive
		// tests to isolate tests from each other.
		if(function_exists('_array_change_key_case')) {
			global $ADODB_INCLUDED_LIB;
			$ADODB_INCLUDED_LIB = 1;
		}
		Config::setConfigFileName(dirname(INDEX_FILE_LOCATION). DIRECTORY_SEPARATOR. 'config.inc.php');

		// Backup DAOs.
		foreach($this->getMockedDAOs() as $mockedDao) {
			$this->daoBackup[$mockedDao] = DAORegistry::getDAO($mockedDao);
		}

		// Backup registry keys.
		foreach($this->getMockedRegistryKeys() as $mockedRegistryKey) {
			$this->registryBackup[$mockedRegistryKey] = Registry::get($mockedRegistryKey);
		}
	}

	/**
	 * @see PHPUnit_Framework_TestCase::tearDown()
	 */
	protected function tearDown() {
		// Restore registry keys.
		foreach($this->getMockedRegistryKeys() as $mockedRegistryKey) {
			Registry::set($mockedRegistryKey, $this->registryBackup[$mockedRegistryKey]);
		}

		// Restore DAOs.
		foreach($this->getMockedDAOs() as $mockedDao) {
			DAORegistry::registerDAO($mockedDao, $this->daoBackup[$mockedDao]);
		}
	}

	/**
	 * @see PHPUnit_Framework_TestCase::getActualOutput()
	 */
	public function getActualOutput() {
		// We do not want to see output.
		return '';
	}


	//
	// Protected helper methods
	//
	/**
	 * Set a non-default test configuration
	 * @param $config string the id of the configuration to use
	 * @param $configPath string (optional) where to find the config file, default: 'config'
	 * @param $dbConnect (optional) whether to try to re-connect the data base, default: true
	 */
	protected function setTestConfiguration($config, $configPath = 'config') {
		// Get the configuration file belonging to
		// this test configuration.
		$configFile = $this->getConfigFile($config, $configPath);

		// Avoid unnecessary configuration switches.
		if (Config::getConfigFileName() != $configFile) {
			// Switch the configuration file
			Config::setConfigFileName($configFile);
		}
	}


	//
	// Private helper methods
	//
	/**
	 * Resolves the configuration id to a configuration
	 * file
	 * @param $config string
	 * @return string the resolved configuration file name
	 */
	private function getConfigFile($config, $configPath = 'config') {
		// Build the config file name.
		return './lib/pkp/tests/'.$configPath.'/config.'.$config.'.inc.php';
	}
}
?>

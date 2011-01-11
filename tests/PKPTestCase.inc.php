<?php
/**
 * @defgroup tests
 */

/**
 * @file tests/PKPTestCase.inc.php
 *
 * Copyright (c) 2000-2011 John Willinsky
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
require_once('PHPUnit/Extensions/OutputTestCase.php');

abstract class PKPTestCase extends PHPUnit_Extensions_OutputTestCase {
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

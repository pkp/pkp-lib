<?php

/**
 * @defgroup tests_plugins
 */

/**
 * @file tests/plugins/PluginTestCase.inc.php
 *
 * Copyright (c) 2000-2010 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class PluginTestCase
 * @ingroup tests_plugins
 * @see Plugin
 *
 * @brief Abstract base class for Plugin tests.
 */

import('lib.pkp.tests.PKPTestCase');
import('lib.pkp.classes.plugins.PKPPlugin');

class PluginTestCase extends PKPTestCase {
	/**
	 * Executes the plug-in test.
	 * @param $pluginCategory string
	 * @param $pluginDir string
	 * @param $pluginName string
	 * @param $filterGroups array
	 */
	protected function executePluginTest($pluginCategory, $pluginDir, $pluginName, $filterGroups) {
		// Make sure that the xml configuration is valid.
		$filterConfigFile = 'plugins/'.$pluginCategory.'/'.$pluginDir.'/filter/'.PLUGIN_FILTER_DATAFILE;
		$this->validateXmlConfig(array('./'.$filterConfigFile, './lib/pkp/'.$filterConfigFile));

		// Make sure that data from earlier tests is being deleted first.
		$filterDao =& DAORegistry::getDAO('FilterDAO'); /* @var $filterDao FilterDAO */
		$filterGroupDao =& DAORegistry::getDAO('FilterGroupDAO'); /* @var $filterGroupDao FilterGroupDAO */
		foreach($filterGroups as $filterGroupSymbolic) {
			foreach($filterDao->getObjectsByGroup($filterGroupSymbolic) as $filter) {
				$filterDao->deleteObject($filter);
			}
			foreach($filterDao->getObjectsByGroup($filterGroupSymbolic, 0, true) as $filter) {
				$filterDao->deleteObject($filter);
			}
			$filterGroupDao->deleteObjectBySymbolic($filterGroupSymbolic);
		}

		// Mock request and router.
		import('lib.pkp.classes.core.PKPRouter');
		$mockRequest = $this->getMock('Request', array('getRouter', 'getUser'));
		$router = new PKPRouter();
		$mockRequest->expects($this->any())
		            ->method('getRouter')
		            ->will($this->returnValue($router));
		$mockRequest->expects($this->any())
		            ->method('getUser')
		            ->will($this->returnValue(null));
		Registry::set('request', $mockRequest);

		// Instantiate the installer.
		import('classes.install.Install');
		$installFile = './lib/pkp/tests/plugins/testPluginInstall.xml';
		$params = $this->getConnectionParams();
		$installer = new Install($params, $installFile, true);

		// Parse the plug-ins version.xml.
		import('lib.pkp.classes.site.VersionCheck');
		self::assertFileExists($versionFile = './plugins/'.$pluginCategory.'/'.$pluginDir.'/version.xml');
		self::assertArrayHasKey('version', $versionInfo =& VersionCheck::parseVersionXML($versionFile));
		self::assertType('Version', $pluginVersion =& $versionInfo['version']);
		$installer->setCurrentVersion($pluginVersion);

		// Install the plug-in.
		self::assertTrue($installer->execute());

		// Reset the hook registry.
		Registry::set('hooks', $nullVar = null);

		// Test whether the installation is idempotent.
		self::assertTrue($installer->execute());

		// Test whether the filter groups have been installed.
		foreach($filterGroups as $filterGroupSymbolic) {
			// Check the group.
			self::assertType('FilterGroup', $filterGroupDao->getObjectBySymbolic($filterGroupSymbolic));
		}
	}


	//
	// Protected helper function
	//
	protected function validateXmlConfig($configFiles) {
		foreach($configFiles as $configFile) {
			if(file_exists($configFile)) {
				$xmlDom = new DOMDocument();
				$xmlDom->load($configFile);
				self::assertTrue($xmlDom->validate());
				unset($xmlDom);
			}
		}
	}


	//
	// Private helper function
	//
	/**
	 * Load database connection parameters into an array (needed for upgrade).
	 * @return array
	 */
	private function getConnectionParams() {
		return array(
			'clientCharset' => Config::getVar('i18n', 'client_charset'),
			'connectionCharset' => Config::getVar('i18n', 'connection_charset'),
			'databaseCharset' => Config::getVar('i18n', 'database_charset'),
			'databaseDriver' => Config::getVar('database', 'driver'),
			'databaseHost' => Config::getVar('database', 'host'),
			'databaseUsername' => Config::getVar('database', 'username'),
			'databasePassword' => Config::getVar('database', 'password'),
			'databaseName' => Config::getVar('database', 'name')
		);
	}
}
?>
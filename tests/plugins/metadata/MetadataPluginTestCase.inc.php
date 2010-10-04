<?php

/**
 * @defgroup tests_plugins_metadata
 */

/**
 * @file tests/plugins/metadata/MetadataPluginTestCase.inc.php
 *
 * Copyright (c) 2000-2010 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class MetadataPluginTestCase
 * @ingroup tests_plugins_metadata
 * @see MetadataPlugin
 *
 * @brief Abstract base class for MetadataPlugin.
 */

import('lib.pkp.tests.PKPTestCase');

class MetadataPluginTestCase extends PKPTestCase {
	/**
	 * Executes the metadata plug-in test.
	 */
	protected function executeMetadataPluginTest($pluginDir, $pluginName, $filterGroups, $controlledVocabs) {
		// Make sure that the xml configuration is valid.
		$filterConfigFile = 'plugins/metadata/'.$pluginDir.'/filter/'.PLUGIN_FILTER_DATAFILE;
		$controlledVocabFile = 'plugins/metadata/'.$pluginDir.'/schema/'.METADATA_PLUGIN_VOCAB_DATAFILE;
		$configFiles = array(
			'./'.$filterConfigFile,
			'./lib/pkp/'.$filterConfigFile,
			'./'.$controlledVocabFile,
			'./lib/pkp/'.$controlledVocabFile
		);
		foreach($configFiles as $configFile) {
			if(file_exists($configFile)) {
				$xmlDom = new DOMDocument();
				$xmlDom->load($configFile);
				self::assertTrue($xmlDom->validate());
				unset($xmlDom);
			}
		}

		// Make sure that data from earlier tests is being deleted first.
		$filterDao =& DAORegistry::getDAO('FilterDAO'); /* @var $filterDao FilterDAO */
		$filterGroupDao =& DAORegistry::getDAO('FilterGroupDAO'); /* @var $filterGroupDao FilterGroupDAO */
		foreach($filterGroups as $filterGroupSymbolic) {
			foreach($filterDao->getObjectsByGroup($filterGroupSymbolic) as $filter) {
				$filterDao->deleteObject($filter);
			}
			$filterGroupDao->deleteObjectBySymbolic($filterGroupSymbolic);
		}

		$controlledVocabDao =& DAORegistry::getDAO('ControlledVocabDAO'); /* @var $controlledVocabDao ControlledVocabDAO */
		foreach($controlledVocabs as $controlledVocabSymbolic) {
			$controlledVocab =& $controlledVocabDao->getBySymbolic($controlledVocabSymbolic, 0, 0);
			if ($controlledVocab) $controlledVocabDao->deleteObject($controlledVocab);
		}

		$pluginSettingsDao =& DAORegistry::getDAO('PluginSettingsDAO'); /* @var $pluginSettingsDao PluginSettingsDAO */
		$pluginSettingsDao->deleteSetting(0, $pluginName, METADATA_PLUGIN_VOCAB_INSTALLED_SETTING);

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
		self::assertFileExists($versionFile = './plugins/metadata/'.$pluginDir.'/version.xml');
		self::assertArrayHasKey('version', $versionInfo =& VersionCheck::parseVersionXML($versionFile));
		self::assertType('Version', $pluginVersion =& $versionInfo['version']);
		$installer->setCurrentVersion($pluginVersion);

		// Install the plug-in.
		self::assertTrue($installer->execute());

		// Reset the hook registry.
		Registry::set('hooks', $nullVar = null);

		// Test whether the installation is idempotent.
		self::assertTrue($installer->execute());

		// Test whether the controlled vocabs have been installed.
		foreach($controlledVocabs as $controlledVocab) {
			self::assertType('ControlledVocab', $controlledVocabDao->getBySymbolic($controlledVocab, 0, 0));
		}

		// Test whether the filter groups have been installed.
		foreach($filterGroups as $filterGroupSymbolic) {
			// Check the group.
			self::assertType('FilterGroup', $filterGroupDao->getObjectBySymbolic($filterGroupSymbolic));

			// Check the filters in the group.
			$filters = array_merge(
					// Filters
					$filterDao->getObjectsByGroup($filterGroupSymbolic, 0, false),
					// Templates
					$filterDao->getObjectsByGroup($filterGroupSymbolic, 0, true));
			self::assertTrue(count($filters)>0);
			unset($filters);
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
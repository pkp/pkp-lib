<?php

/**
 * @defgroup tests_plugins Plugin test suite
 */

/**
 * @file tests/plugins/PluginTestCase.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PluginTestCase
 * @ingroup tests_plugins
 *
 * @see Plugin
 *
 * @brief Abstract base class for Plugin tests.
 */

require_mock_env('env2');

import('lib.pkp.tests.DatabaseTestCase');

use APP\core\Request;
use APP\install\Install;
use PKP\config\Config;
use PKP\core\PKPRouter;
use PKP\core\Registry;
use PKP\db\DAORegistry;
use PKP\site\VersionCheck;

class PluginTestCase extends DatabaseTestCase
{
    /**
     * @copydoc DatabaseTestCase::getAffectedTables()
     */
    protected function getAffectedTables()
    {
        return [
            'filters', 'filter_settings', 'filter_groups',
            'versions', 'plugin_settings'
        ];
    }

    /**
     * @copydoc PKPTestCase::getMockedRegistryKeys()
     */
    protected function getMockedRegistryKeys()
    {
        return ['request', 'hooks'];
    }

    /**
     * Executes the plug-in test.
     *
     * @param string $pluginCategory
     * @param string $pluginDir
     * @param string $pluginName
     * @param array $filterGroups
     */
    protected function executePluginTest($pluginCategory, $pluginDir, $pluginName, $filterGroups)
    {
        // Make sure that the xml configuration is valid.
        $filterConfigFile = 'plugins/' . $pluginCategory . '/' . $pluginDir . '/filter/' . PLUGIN_FILTER_DATAFILE;
        $this->validateXmlConfig(['./' . $filterConfigFile, './lib/pkp/' . $filterConfigFile]);

        // Mock request and router.
        $mockRequest = $this->getMockBuilder(Request::class)
            ->setMethods(['getRouter', 'getUser'])
            ->getMock();
        $router = new PKPRouter();
        $mockRequest->expects($this->any())
            ->method('getRouter')
            ->will($this->returnValue($router));
        $mockRequest->expects($this->any())
            ->method('getUser')
            ->will($this->returnValue(null));
        Registry::set('request', $mockRequest);

        // Instantiate the installer.
        $installFile = './lib/pkp/tests/plugins/testPluginInstall.xml';
        $params = $this->getConnectionParams();
        $installer = new Install($params, $installFile, true);

        // Parse the plug-ins version.xml.
        self::assertFileExists($versionFile = './plugins/' . $pluginCategory . '/' . $pluginDir . '/version.xml');
        self::assertArrayHasKey('version', $versionInfo = VersionCheck::parseVersionXML($versionFile));
        self::assertInstanceOf('Version', $pluginVersion = $versionInfo['version']);
        $installer->setCurrentVersion($pluginVersion);

        // Install the plug-in.
        self::assertTrue($installer->execute());

        // Reset the hook registry.
        $nullVar = null;
        Registry::set('hooks', $nullVar);

        // Test whether the installation is idempotent.
        $this->markTestIncomplete('Idempotence test disabled temporarily.');
        // self::assertTrue($installer->execute());

        // Test whether the filter groups have been installed.
        $filterGroupDao = DAORegistry::getDAO('FilterGroupDAO'); /** @var FilterGroupDAO $filterGroupDao */
        foreach ($filterGroups as $filterGroupSymbolic) {
            // Check the group.
            self::assertInstanceOf('FilterGroup', $filterGroupDao->getObjectBySymbolic($filterGroupSymbolic), $filterGroupSymbolic);
        }
    }


    //
    // Protected helper function
    //
    protected function validateXmlConfig($configFiles)
    {
        foreach ($configFiles as $configFile) {
            if (file_exists($configFile)) {
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
     *
     * @return array
     */
    private function getConnectionParams()
    {
        return [
            'connectionCharset' => Config::getVar('i18n', 'connection_charset'),
            'databaseDriver' => Config::getVar('database', 'driver'),
            'databaseHost' => Config::getVar('database', 'host'),
            'databaseUsername' => Config::getVar('database', 'username'),
            'databasePassword' => Config::getVar('database', 'password'),
            'databaseName' => Config::getVar('database', 'name')
        ];
    }
}

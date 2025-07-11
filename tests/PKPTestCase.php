<?php

/**
 * @defgroup tests Tests
 * Tests and test framework for unit and integration tests.
 */

/**
 * @file tests/PKPTestCase.php
 *
 * Copyright (c) 2014-2024 Simon Fraser University
 * Copyright (c) 2000-2024 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PKPTestCase
 *
 * @brief Class that implements functionality common to all PKP unit test cases.
 */

namespace PKP\tests;

use APP\core\Application;
use APP\core\PageRouter;
use APP\core\Request;
use Illuminate\Support\Facades\Mail;
use Mockery;
use Mockery\LegacyMockInterface;
use Mockery\MockInterface;
use PHPUnit\Framework\TestCase;
use PKP\config\Config;
use PKP\core\Core;
use PKP\core\Dispatcher;
use PKP\core\PKPContainer;
use PKP\core\PKPRequest;
use PKP\core\Registry;
use PKP\db\DAORegistry;

abstract class PKPTestCase extends TestCase
{
    public const MOCKED_GUZZLE_CLIENT_NAME = 'GuzzleClient';

    private array $daoBackup = [];
    private array $registryBackup = [];
    private array $containerBackup = [];
    private array $mockedRegistryKeys = [];

    /**
     * Override this method if you want to backup/restore
     * DAOs before/after the test.
     *
     * @return array A list of DAO names to backup and restore.
     */
    protected function getMockedDAOs(): array
    {
        return [];
    }

    /**
     * Override this method if you want to backup/restore
     * registry entries before/after the test.
     *
     * @return array A list of registry keys to backup and restore.
     */
    protected function getMockedRegistryKeys(): array
    {
        return $this->mockedRegistryKeys;
    }

    /**
     * Override this method if you want to backup/restore
     * singleton entries from the container before/after the test.
     *
     * @return string[] A list of container classes/identifiers to backup and restore.
     */
    protected function getMockedContainerKeys(): array
    {
        return [];
    }

    /**
     * @copydoc TestCase::setUp()
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->setBackupGlobals(true);

        // Set application running unit test
        PKPContainer::getInstance()->setRunningUnitTests();

        // Rather than using "include_once()", ADOdb uses
        // a global variable to maintain the information
        // whether its library has been included before (wtf!).
        // This causes problems with PHPUnit as PHPUnit will
        // delete all global state between two consecutive
        // tests to isolate tests from each other.
        if (function_exists('_array_change_key_case')) {
            global $ADODB_INCLUDED_LIB;
            $ADODB_INCLUDED_LIB = 1;
        }
        Config::setConfigFileName(Core::getBaseDir() . '/config.inc.php');

        // Backup DAOs.
        foreach ($this->getMockedDAOs() as $mockedDao) {
            $this->daoBackup[$mockedDao] = DAORegistry::getDAO($mockedDao);
        }

        // Backup registry keys.
        foreach ($this->getMockedRegistryKeys() as $mockedRegistryKey) {
            $this->registryBackup[$mockedRegistryKey] = Registry::get($mockedRegistryKey);
        }

        // Backup container keys.
        foreach ($this->getMockedContainerKeys() as $mockedContainer) {
            $this->containerBackup[$mockedContainer] = app($mockedContainer);
        }
    }

    /**
     * @copydoc TestCase::tearDown()
     */
    protected function tearDown(): void
    {
        // Restore container keys.
        foreach ($this->getMockedContainerKeys() as $mockedContainer) {
            app()->instance($mockedContainer, $this->containerBackup[$mockedContainer]);
        }

        // Restore registry keys.
        foreach ($this->getMockedRegistryKeys() as $mockedRegistryKey) {
            Registry::set($mockedRegistryKey, $this->registryBackup[$mockedRegistryKey]);
        }

        // Restore DAOs.
        foreach ($this->getMockedDAOs() as $mockedDao) {
            DAORegistry::registerDAO($mockedDao, $this->daoBackup[$mockedDao]);
        }

        // Delete the mocked guzzle client from registry
        Registry::delete(self::MOCKED_GUZZLE_CLIENT_NAME);

        // Unset application running unit test
        PKPContainer::getInstance()->unsetRunningUnitTests();

        parent::tearDown();

        Mockery::close();
    }

    /**
     * @copydoc TestCase::getActualOutput()
     */
    public function getActualOutput(): string
    {
        // We do not want to see output.
        return '';
    }


    //
    // Protected helper methods
    //
    /**
     * Set a non-default test configuration
     *
     * @param string $config the id of the configuration to use
     * @param string $configPath (optional) where to find the config file, default: 'config'
     */
    protected function setTestConfiguration(string $config, string $configPath = 'config'): void
    {
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
     * Mock a web request.
     *
     * For correct timing you have to call this method
     * in the setUp() method of a test after calling
     * parent::setUp() or in a test method. You can also
     * call this method as many times as necessary from
     * within your test and you're guaranteed to receive
     * a fresh request whenever you call it.
     *
     * And make sure that you merge any additional mocked
     * registry keys with the ones returned from this class.
     *
     *
     * @return Request
     */
    protected function mockRequest(string $path = 'index/test-page/test-op', int $userId = 0): PKPRequest
    {
        // Back up the default request.
        if (!isset($this->registryBackup['request'])) {
            $this->mockedRegistryKeys[] = 'request';
            $this->registryBackup['request'] = Registry::get('request');
        }

        // Create a test request.
        Registry::delete('request');
        $application = Application::get();
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['PATH_INFO'] = $path;
        $request = $application->getRequest();

        // Test router.
        $router = new PageRouter();
        $router->setApplication($application);
        $dispatcher = new Dispatcher();
        $dispatcher->addRouterName('\APP\core\PageRouter', Application::ROUTE_PAGE);
        $dispatcher->setApplication($application);
        $router->setDispatcher($dispatcher);
        $request->setRouter($router);

        // Test user.
        $request->getSessionGuard()->setUserId($userId);

        return $request;
    }


    //
    // Private helper methods
    //
    /**
     * Resolves the configuration id to a configuration
     * file
     *
     *
     * @return string the resolved configuration file name
     */
    private function getConfigFile(string $config, string $configPath = 'config'): string
    {
        // Build the config file name.
        return './lib/pkp/tests/' . $configPath . '/config.' . $config . '.inc.php';
    }

    /**
     * Creates a regular expression to match the translation, and replaces params by a generic matcher
     * e.g. The following translation "start {$param} end" would end up as "/^start .*? end$/
     */
    protected function localeToRegExp(string $translation): string
    {
        $pieces = preg_split('/\{\$[^}]+\}/', $translation);
        $escapedPieces = array_map(fn ($piece) => preg_quote($piece, '/'), $pieces);
        return '/^' . implode('.*?', $escapedPieces) . '$/u';
    }

    /**
     * Mock the mail facade
     *
     * @see https://laravel.com/docs/11.x/mocking
     */
    protected function mockMail(): void
    {
        /**
         * @disregard P1013 PHP Intelephense error suppression
         *
         * @see https://github.com/bmewburn/vscode-intelephense/issues/568
         */
        Mail::shouldReceive('send')
            ->withAnyArgs()
            ->andReturn(null)
            ->shouldReceive('compileParams')
            ->withAnyArgs()
            ->andReturn('');
    }

    /**
     * Create mockable guzzle client
     *
     * @see https://docs.guzzlephp.org/en/stable/testing.html
     *
     * @param bool $setToRegistry   Should store it in app registry to be used by call
     *                              as `Application::get()->getHttpClient()`
     *
     */
    protected function mockGuzzleClient(bool $setToRegistry = true): MockInterface|LegacyMockInterface
    {
        $guzzleClientMock = Mockery::mock(\GuzzleHttp\Client::class)
            ->makePartial()
            ->shouldReceive('request')
            ->withAnyArgs()
            ->andReturn(new \GuzzleHttp\Psr7\Response())
            ->getMock();

        if ($setToRegistry) {
            Registry::set(self::MOCKED_GUZZLE_CLIENT_NAME, $guzzleClientMock);
        }

        return $guzzleClientMock;
    }
}

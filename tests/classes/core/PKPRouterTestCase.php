<?php

/**
 * @file tests/classes/core/PKPRouterTestCase.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PKPRouterTestCase
 *
 * @ingroup tests_classes_core
 *
 * @see PKPRouter
 *
 * @brief Base tests class for PKPRouter tests.
 */

namespace PKP\tests\classes\core;

use APP\core\Application;
use APP\core\Request;
use PHPUnit\Framework\Attributes\BackupGlobals;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\MockObject\MockObject;
use PKP\core\PKPComponentRouter;
use PKP\core\PKPRequest;
use PKP\core\PKPRouter;
use PKP\core\Registry;
use PKP\db\DAORegistry;
use PKP\plugins\Hook;
use PKP\tests\PKPTestCase;

#[BackupGlobals(true)]
#[CoversMethod(PKPRouter::class, 'getApplication')]
#[CoversMethod(PKPRouter::class, 'setApplication')]
#[CoversMethod(PKPRouter::class, 'getDispatcher')]
#[CoversMethod(PKPRouter::class, 'setDispatcher')]
#[CoversMethod(PKPRouter::class, 'supports')]
#[CoversMethod(PKPRouter::class, 'isCacheable')]
#[CoversMethod(PKPRouter::class, 'getRequestedContextPath')]
#[CoversMethod(PKPRouter::class, 'getContext')]
#[CoversMethod(PKPRouter::class, 'getIndexUrl')]
class PKPRouterTestCase extends PKPTestCase
{
    protected PKPRouter|MockObject $router;
    protected PKPRequest|MockObject $request;

    /**
     * @see PKPTestCase::getMockedRegistryKeys()
     */
    protected function getMockedRegistryKeys(): array
    {
        return [...parent::getMockedRegistryKeys(), 'application'];
    }

    protected function setUp(): void
    {
        parent::setUp();
        Hook::rememberCalledHooks();
        $this->router = new PKPComponentRouter();
    }

    protected function tearDown(): void
    {
        Hook::resetCalledHooks(true);
        parent::tearDown();
    }

    public function testGetSetApplication()
    {
        $application = $this->_setUpMockEnvironment();
        self::assertSame($application, $this->router->getApplication());
    }

    public function testGetSetDispatcher()
    {
        $application = $this->_setUpMockEnvironment();
        $dispatcher = $application->getDispatcher();
        self::assertSame($dispatcher, $this->router->getDispatcher());
    }

    public function testSupports()
    {
        $this->request = new Request();
        self::assertTrue($this->router->supports($this->request));
    }

    public function testIsCacheable()
    {
        $this->request = new Request();
        self::assertFalse($this->router->isCacheable($this->request));
    }

    public function testGetRequestedContextPathWithEmptyPathInfo()
    {
        $this->_setUpMockEnvironment();
        $_SERVER['PATH_INFO'] = null;
        self::assertEquals(
            Application::SITE_CONTEXT_PATH,
            $this->router->getRequestedContextPath($this->request)
        );
    }

    public function testGetRequestedContextPathWithFullPathInfo()
    {
        $this->_setUpMockEnvironment();
        Hook::resetCalledHooks(true);
        $_SERVER['PATH_INFO'] = '/context1/other/path/vars';
        self::assertEquals(
            'context1',
            $this->router->getRequestedContextPath($this->request)
        );
        self::assertEquals(
            [['Router::getRequestedContextPath', ['context1']]],
            Hook::getCalledHooks()
        );
    }

    public function testGetRequestedContextPathWithInvalidPathInfo()
    {
        $this->_setUpMockEnvironment();
        $_SERVER['PATH_INFO'] = '/context:?#/';
        self::assertEquals(
            'context',
            $this->router->getRequestedContextPath($this->request)
        );
    }

    public function testGetContext()
    {
        // We use a 1-level context
        $this->_setUpMockEnvironment('someContext');
        $_SERVER['PATH_INFO'] = '/contextPath';

        // Simulate a context DAO
        $application = Application::get();
        $contextDao = $application->getContextDAO();
        $mockDao = $this->getMockBuilder($contextDao::class)
            ->onlyMethods(['getByPath'])
            ->getMock();
        DAORegistry::registerDAO('SomeContextDAO', $mockDao);

        // Set up the mock DAO get-by-path method which
        // should be called with the context path from
        // the path info.
        $expectedResult = $this->getMockBuilder($contextDao->newDataObject()::class)->getMock();
        $mockDao->expects($this->once())
            ->method('getByPath')
            ->with('contextPath')
            ->willReturn($expectedResult);
        $result = $this->router->getContext($this->request);
        self::assertInstanceOf(\PKP\context\Context::class, $result);
        self::assertEquals($expectedResult, $result);
    }

    public function testGetContextForIndex()
    {
        // We use a 1-level context
        $this->_setUpMockEnvironment('someContext');
        $_SERVER['PATH_INFO'] = '/';

        $result = $this->router->getContext($this->request);
        self::assertNull($result);
    }

    public function testGetIndexUrl()
    {
        $this->_setUpMockEnvironment();
        $this->setTestConfiguration('request1', 'classes/core/config'); // no restful URLs
        $_SERVER = [
            'SERVER_NAME' => 'mydomain.org',
            'SCRIPT_NAME' => '/base/index.php'
        ];
        Hook::resetCalledHooks(true);

        self::assertEquals('http://mydomain.org/base/index.php', $this->router->getIndexUrl($this->request));

        // Several hooks should have been triggered.
        self::assertEquals(
            [
                ['Request::getProtocol', ['http']],
                ['Request::getBasePath', ['/base']],
                ['Request::getBaseUrl', ['http://mydomain.org/base']],
                ['Router::getIndexUrl', ['http://mydomain.org/base/index.php']],
            ],
            Hook::getCalledHooks()
        );

        // Calling getIndexUrl() twice should return the same
        // result without triggering the hooks again.
        Hook::resetCalledHooks(true);
        self::assertEquals('http://mydomain.org/base/index.php', $this->router->getIndexUrl($this->request));
        self::assertEquals(
            [],
            Hook::getCalledHooks()
        );
    }

    public function testGetIndexUrlRestful()
    {
        $this->_setUpMockEnvironment();
        $this->setTestConfiguration('request2', 'classes/core/config'); // restful URLs
        $_SERVER = [
            'SERVER_NAME' => 'mydomain.org',
            'SCRIPT_NAME' => '/base/index.php'
        ];

        self::assertEquals('http://mydomain.org/base', $this->router->getIndexUrl($this->request));
    }

    /**
     * Set's up a mock environment for router tests (PKPApplication,
     * PKPRequest) with customizable contexts and path info flag.
     *
     *
     * @return Application|MockObject
     */
    protected function _setUpMockEnvironment(string $contextName = 'firstContext')
    {
        // Mock application object without calling its constructor.
        /** @var Application|MockObject */
        $mockApplication = $this->getMockBuilder(Application::class)
            ->onlyMethods(['getContextName'])
            ->getMock();

        // Set up the getContextName() method
        $mockApplication->expects($this->any())
            ->method('getContextName')
            ->willReturn($contextName);

        $this->router->setApplication($mockApplication);
        Registry::set('application', $mockApplication);

        // Dispatcher
        $dispatcher = $mockApplication->getDispatcher();
        $this->router->setDispatcher($dispatcher);

        // Mock request
        $this->request = $this->getMockBuilder(Request::class)
            ->onlyMethods(['getServerHost'])
            ->getMock();
        $this->request->expects($this->any())
            ->method('getServerHost')
            ->willReturn('mydomain.org');
        $this->request->setRouter($this->router);

        return $mockApplication;
    }

    /**
     * Create mock DAOs "FirstContextDAO" that can be
     * used with the standard environment set up when calling self::_setUpMockEnvironment().
     * Both DAOs will be registered with the DAORegistry and thereby be made available
     * to the router.
     */
    protected function _setUpMockDAOs(string $firstContextPath = 'current-context1', bool $firstContextIsNull = false, array $supportedLocales = ['en']): void
    {
        $application = Application::get();
        $contextDao = $application->getContextDAO();
        $contextClassName = $contextDao->newDataObject()::class;
        $mockFirstContextDao = $this->getMockBuilder($contextDao::class)
            ->onlyMethods(['getByPath'])
            ->getMock();
        if (!$firstContextIsNull) {
            $firstContextInstance = $this->getMockBuilder($contextClassName)
                ->onlyMethods(['getPath', 'getSetting', 'getSupportedLocales', 'getId'])
                ->getMock();
            $firstContextInstance->expects($this->any())
                ->method('getId')
                ->willReturn(1);
            $firstContextInstance->expects($this->any())
                ->method('getPath')
                ->willReturn($firstContextPath);
            $firstContextInstance->expects($this->any())
                ->method('getSetting')
                ->willReturn(null);
            $firstContextInstance->expects($this->any())
                ->method('getSupportedLocales')
                ->willReturn($supportedLocales);

            $mockFirstContextDao->expects($this->any())
                ->method('getByPath')
                ->with($firstContextPath)
                ->willReturn($firstContextInstance);
        }

        DAORegistry::registerDAO('FirstContextDAO', $mockFirstContextDao);
    }
}

<?php

/**
 * @file tests/classes/core/PKPRouterTestCase.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PKPRouterTestCase
 * @ingroup tests_classes_core
 *
 * @see PKPRouter
 *
 * @brief Base tests class for PKPRouter tests.
 */

namespace PKP\tests\classes\core;

use APP\core\Application;
use APP\core\Request;
use PHPUnit\Framework\MockObject\MockObject;
use PKP\core\PKPRequest;
use PKP\core\PKPRouter;
use PKP\core\Registry;
use PKP\db\DAORegistry;
use PKP\plugins\Hook;
use PKP\tests\PKPTestCase;

/**
 * @backupGlobals enabled
 */
class PKPRouterTestCase extends PKPTestCase
{
    public const PATHINFO_ENABLED = true;
    public const PATHINFO_DISABLED = false;

    protected PKPRouter $router;
    protected PKPRequest $request;

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
        $this->router = new PKPRouter();
    }

    protected function tearDown(): void
    {
        Hook::resetCalledHooks(true);
        parent::tearDown();
    }

    /**
     * @covers PKPRouter::getApplication
     * @covers PKPRouter::setApplication
     */
    public function testGetSetApplication()
    {
        $application = $this->_setUpMockEnvironment();
        self::assertSame($application, $this->router->getApplication());
    }

    /**
     * @covers PKPRouter::getDispatcher
     * @covers PKPRouter::setDispatcher
     */
    public function testGetSetDispatcher()
    {
        $application = $this->_setUpMockEnvironment();
        $dispatcher = $application->getDispatcher();
        self::assertSame($dispatcher, $this->router->getDispatcher());
    }

    /**
     * @covers PKPRouter::supports
     */
    public function testSupports()
    {
        $this->request = new Request();
        self::assertTrue($this->router->supports($this->request));
    }

    /**
     * @covers PKPRouter::isCacheable
     */
    public function testIsCacheable()
    {
        $this->request = new Request();
        self::assertFalse($this->router->isCacheable($this->request));
    }

    /**
     * @covers PKPRouter::getRequestedContextPaths
     */
    public function testGetRequestedContextPathWithEmptyPathInfo()
    {
        $this->_setUpMockEnvironment(self::PATHINFO_ENABLED);
        $_SERVER['PATH_INFO'] = null;
        self::assertEquals(
            ['index'],
            $this->router->getRequestedContextPaths($this->request)
        );
    }

    /**
     * @covers PKPRouter::getRequestedContextPaths
     * @covers PKPRouter::getRequestedContextPath
     */
    public function testGetRequestedContextPathWithFullPathInfo()
    {
        $this->_setUpMockEnvironment(self::PATHINFO_ENABLED);
        Hook::resetCalledHooks(true);
        $_SERVER['PATH_INFO'] = '/context1/other/path/vars';
        self::assertEquals(
            ['context1'],
            $this->router->getRequestedContextPaths($this->request)
        );
        self::assertEquals(
            'context1',
            $this->router->getRequestedContextPath($this->request, 1)
        );
        self::assertEquals(
            [['Router::getRequestedContextPaths', [['context1']]]],
            Hook::getCalledHooks()
        );
    }

    /**
     * @covers PKPRouter::getRequestedContextPaths
     */
    public function testGetRequestedContextPathWithInvalidPathInfo()
    {
        $this->_setUpMockEnvironment(self::PATHINFO_ENABLED);
        $_SERVER['PATH_INFO'] = '/context:?#/';
        self::assertEquals(
            ['context'],
            $this->router->getRequestedContextPaths($this->request)
        );
    }

    /**
     * @covers PKPRouter::getRequestedContextPaths
     */
    public function testGetRequestedContextPathWithEmptyContextParameters()
    {
        $this->_setUpMockEnvironment(self::PATHINFO_DISABLED);
        $_GET['firstContext'] = null;
        self::assertEquals(
            ['index'],
            $this->router->getRequestedContextPaths($this->request)
        );
    }

    /**
     * @covers PKPRouter::getRequestedContextPath
     * @covers PKPRouter::getRequestedContextPaths
     */
    public function testGetRequestedContextPathWithFullContextParameters()
    {
        $this->_setUpMockEnvironment(self::PATHINFO_DISABLED);
        Hook::resetCalledHooks(true);
        $_GET['firstContext'] = 'context1';
        self::assertEquals(
            ['context1'],
            $this->router->getRequestedContextPaths($this->request)
        );
        self::assertEquals(
            'context1',
            $this->router->getRequestedContextPath($this->request, 1)
        );
        self::assertEquals(
            [['Router::getRequestedContextPaths', [['context1']]]],
            Hook::getCalledHooks()
        );
    }

    /**
     * @covers PKPRouter::getRequestedContextPaths
     */
    public function testGetRequestedContextPathWithPartialContextParameters()
    {
        $this->_setUpMockEnvironment(self::PATHINFO_DISABLED);
        $_GET['firstContext'] = 'context';
        self::assertEquals(
            ['context'],
            $this->router->getRequestedContextPaths($this->request)
        );
    }

    /**
     * @covers PKPRouter::getContext
     * @covers PKPRouter::getContextByName
     * @covers PKPRouter::_contextLevelToContextName
     * @covers PKPRouter::_contextNameToContextLevel
     */
    public function testGetContext()
    {
        // We use a 1-level context
        $this->_setUpMockEnvironment(true, 1, ['someContext']);
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
            ->will($this->returnValue($expectedResult));
        $result = $this->router->getContext($this->request, 1);
        self::assertInstanceOf('Context', $result);
        self::assertEquals($expectedResult, $result);

        $resultByName = $this->router->getContextByName($this->request, 'someContext');
        self::assertInstanceOf('Context', $resultByName);
        self::assertEquals($expectedResult, $resultByName);
    }

    /**
     * @covers PKPRouter::getContext
     * @covers PKPRouter::getContextByName
     */
    public function testGetContextForIndex()
    {
        // We use a 1-level context
        $this->_setUpMockEnvironment(true, 1, ['someContext']);
        $_SERVER['PATH_INFO'] = '/';

        $result = $this->router->getContext($this->request, 1);
        self::assertNull($result);

        $resultByName = $this->router->getContextByName($this->request, 'someContext');
        self::assertNull($resultByName);
    }

    /**
     * @covers PKPRouter::getIndexUrl
     */
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
                ['Request::getServerHost', ['mydomain.org', false, true]],
                ['Request::getProtocol', ['http']],
                ['Request::getBasePath', ['/base']],
                ['Request::getBaseUrl', ['http://mydomain.org/base']],
                ['Router::getIndexUrl', ['http://mydomain.org/base/index.php']]
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

    /**
     * @covers PKPRouter::getIndexUrl
     */
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
     * @param bool $pathInfoEnabled
     * @param int $contextDepth
     * @param array $contextList
     *
     * @return unknown
     */
    protected function _setUpMockEnvironment(
        $pathInfoEnabled = self::PATHINFO_ENABLED,
        $contextDepth = 1,
        $contextList = ['firstContext']
    ) {
        // Mock application object without calling its constructor.
        /** @var Application|MockObject */
        $mockApplication = $this->getMockBuilder(Application::class)
            ->onlyMethods(['getContextDepth', 'getContextList'])
            ->getMock();

        // Set up the getContextDepth() method
        $mockApplication->expects($this->any())
            ->method('getContextDepth')
            ->will($this->returnValue($contextDepth));

        // Set up the getContextList() method
        $mockApplication->expects($this->any())
            ->method('getContextList')
            ->will($this->returnValue($contextList));

        $this->router->setApplication($mockApplication);
        Registry::set('application', $mockApplication);

        // Dispatcher
        $dispatcher = $mockApplication->getDispatcher();
        $this->router->setDispatcher($dispatcher);

        // Mock request
        $this->request = $this->getMockBuilder(Request::class)
            ->onlyMethods(['isPathInfoEnabled'])
            ->getMock();
        $this->request->setRouter($this->router);
        $this->request->expects($this->any())
            ->method('isPathInfoEnabled')
            ->will($this->returnValue($pathInfoEnabled));

        return $mockApplication;
    }

    /**
     * Create mock DAOs "FirstContextDAO" that can be
     * used with the standard environment set up when calling self::_setUpMockEnvironment().
     * Both DAOs will be registered with the DAORegistry and thereby be made available
     * to the router.
     *
     * @param string $firstContextPath
     * @param bool $firstContextIsNull
     */
    protected function _setUpMockDAOs($firstContextPath = 'current-context1', $firstContextIsNull = false)
    {
        $application = Application::get();
        $contextDao = $application->getContextDAO();
        $contextClassName = $contextDao->newDataObject()::class;
        $mockFirstContextDao = $this->getMockBuilder($contextDao::class)
            ->onlyMethods(['getByPath'])
            ->getMock();
        if (!$firstContextIsNull) {
            $firstContextInstance = $this->getMockBuilder($contextClassName)
                ->onlyMethods(['getPath', 'getSetting'])
                ->getMock();
            $firstContextInstance->expects($this->any())
                ->method('getPath')
                ->will($this->returnValue($firstContextPath));
            $firstContextInstance->expects($this->any())
                ->method('getSetting')
                ->will($this->returnValue(null));
            $mockFirstContextDao->expects($this->any())
                ->method('getByPath')
                ->with($firstContextPath)
                ->will($this->returnValue($firstContextInstance));
        }

        DAORegistry::registerDAO('FirstContextDAO', $mockFirstContextDao);
    }
}

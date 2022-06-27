<?php

/**
 * @file tests/classes/core/DispatcherTest.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class DispatcherTest
 * @ingroup tests_classes_core
 *
 * @see Dispatcher
 *
 * @brief Tests for the Dispatcher class.
 */

namespace PKP\tests\classes\core;

use APP\core\Application;
use APP\core\Request;
use Mockery\MockInterface;
use PKP\config\Config;
use PKP\core\PKPApplication;
use PKP\tests\PKPTestCase;

class DispatcherTest extends PKPTestCase
{
    public const
        PATHINFO_ENABLED = true,
        PATHINFO_DISABLED = false;

    private $dispatcher;
    private $request;

    /**
     * @copydoc PKPTestCase::getMockedRegistryKeys()
     */
    protected function getMockedRegistryKeys()
    {
        return ['application', 'dispatcher'];
    }

    /**
     * @see PKPTestCase::setUp()
     */
    protected function setUp(): void
    {
        parent::setUp();

        // Mock application object without calling its constructor.
        /** @var Application|MockInterface */
        $mockApplication = $this->getMockBuilder(Application::class)
            ->onlyMethods(['getContextDepth', 'getContextList'])
            ->getMock();

        // Set up the getContextDepth() method
        $mockApplication->expects($this->any())
            ->method('getContextDepth')
            ->will($this->returnValue(2));

        // Set up the getContextList() method
        $mockApplication->expects($this->any())
            ->method('getContextList')
            ->will($this->returnValue(['firstContext', 'secondContext']));

        $this->dispatcher = $mockApplication->getDispatcher(); // this also adds the component router
        $this->dispatcher->addRouterName('\PKP\core\PKPPageRouter', 'page');

        $this->request = new Request();
    }

    /**
     * @covers Dispatcher::url
     */
    public function testUrl()
    {
        if (Config::getVar('general', 'disable_path_info')) {
            $this->markTestSkipped();
        }
        $baseUrl = $this->request->getBaseUrl();

        $url = $this->dispatcher->url($this->request, PKPApplication::ROUTE_PAGE, ['context1', 'context2'], 'somepage', 'someop');
        self::assertEquals($baseUrl . '/index.php/context1/context2/somepage/someop', $url);

        $url = $this->dispatcher->url($this->request, PKPApplication::ROUTE_COMPONENT, ['context1', 'context2'], 'some.ComponentHandler', 'someOp');
        self::assertEquals($baseUrl . '/index.php/context1/context2/$$$call$$$/some/component/some-op', $url);
    }
}

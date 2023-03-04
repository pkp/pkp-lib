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
use PHPUnit\Framework\MockObject\MockObject;
use PKP\core\Dispatcher;
use PKP\core\PKPApplication;
use PKP\core\PKPRequest;
use PKP\tests\PKPTestCase;

class DispatcherTest extends PKPTestCase
{
    public const PATHINFO_ENABLED = true;
    public const PATHINFO_DISABLED = false;

    private Dispatcher $dispatcher;
    private PKPRequest $request;

    /**
     * @copydoc PKPTestCase::getMockedRegistryKeys()
     */
    protected function getMockedRegistryKeys(): array
    {
        return [...parent::getMockedRegistryKeys(), 'application', 'dispatcher'];
    }

    /**
     * @see PKPTestCase::setUp()
     */
    protected function setUp(): void
    {
        parent::setUp();

        // Mock application object without calling its constructor.
        /** @var Application|MockObject */
        $mockApplication = $this->getMockBuilder(Application::class)
            ->onlyMethods(['getContextName'])
            ->getMock();

        // Set up the getContextName() method
        $mockApplication->expects($this->any())
            ->method('getContextName')
            ->will($this->returnValue('firstContext'));

        $this->dispatcher = $mockApplication->getDispatcher(); // this also adds the component router
        $this->dispatcher->addRouterName(\PKP\core\PKPPageRouter::class, 'page');

        $this->request = new Request();
    }

    /**
     * @covers Dispatcher::url
     */
    public function testUrl()
    {
        $baseUrl = $this->request->getBaseUrl();

        $url = $this->dispatcher->url($this->request, PKPApplication::ROUTE_PAGE, 'context1', 'somepage', 'someop');
        self::assertEquals($baseUrl . '/index.php/context1/somepage/someop', $url);

        $url = $this->dispatcher->url($this->request, PKPApplication::ROUTE_COMPONENT, 'context1', 'some.ComponentHandler', 'someOp');
        self::assertEquals($baseUrl . '/index.php/context1/$$$call$$$/some/component/some-op', $url);
    }
}

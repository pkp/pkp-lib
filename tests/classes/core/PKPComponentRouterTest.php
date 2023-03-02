<?php

/**
 * @file tests/classes/core/PKPComponentRouterTest.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PKPComponentRouterTest
 * @ingroup tests_classes_core
 *
 * @see PKPComponentRouter
 *
 * @brief Tests for the PKPComponentRouter class.
 */

namespace PKP\tests\classes\core;

use PKP\core\PKPComponentRouter;
use PKP\core\Registry;
use PKP\db\DAORegistry;
use PKP\security\authorization\UserRolesRequiredPolicy;

/**
 * @backupGlobals enabled
 */
class PKPComponentRouterTest extends PKPRouterTestCase
{
    /**
     * @see PKPTestCase::getMockedRegistryKeys()
     */
    protected function getMockedRegistryKeys(): array
    {
        return [...parent::getMockedRegistryKeys(), 'request', 'user'];
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->router = new PKPComponentRouter();
    }

    public function testSupports()
    {
        $this->markTestSkipped('The method PKPRouter::testSupports() is not relevant for component routers');
    }

    /**
     * @covers PKPComponentRouter::supports
     * @covers PKPComponentRouter::getRpcServiceEndpoint
     * @covers PKPComponentRouter::_getValidatedServiceEndpointParts
     * @covers PKPComponentRouter::_retrieveServiceEndpointParts
     * @covers PKPComponentRouter::_validateServiceEndpointParts
     */
    public function testSupportsWithPathinfoSuccessful()
    {
        $mockApplication = $this->_setUpMockEnvironment();

        $_SERVER = [
            'SCRIPT_NAME' => '/index.php',
            'PATH_INFO' => '/context1/$$$call$$$/grid/notifications/task-notifications-grid/fetch-grid'
        ];
        self::assertTrue($this->router->supports($this->request));
    }

    /**
     * @covers PKPComponentRouter::supports
     * @covers PKPComponentRouter::getRpcServiceEndpoint
     * @covers PKPComponentRouter::_getValidatedServiceEndpointParts
     * @covers PKPComponentRouter::_retrieveServiceEndpointParts
     * @covers PKPComponentRouter::_validateServiceEndpointParts
     */
    public function testSupportsWithPathinfoUnsuccessfulNoComponentNotEnoughPathElements()
    {
        $mockApplication = $this->_setUpMockEnvironment();

        $_SERVER = [
            'SCRIPT_NAME' => '/index.php',
            'PATH_INFO' => '/context1/page/operation'
        ];
        self::assertEquals('', $this->router->getRequestedComponent($this->request));
        self::assertFalse($this->router->supports($this->request));
    }

    /**
     * @covers PKPComponentRouter::supports
     * @covers PKPComponentRouter::getRpcServiceEndpoint
     * @covers PKPComponentRouter::_getValidatedServiceEndpointParts
     * @covers PKPComponentRouter::_retrieveServiceEndpointParts
     * @covers PKPComponentRouter::_validateServiceEndpointParts
     */
    public function testSupportsWithPathinfoUnsuccessfulNoComponentNoMarker()
    {
        $mockApplication = $this->_setUpMockEnvironment();

        $_SERVER = [
            'SCRIPT_NAME' => '/index.php',
            'PATH_INFO' => '/context1/path/to/handler/operation'
        ];
        self::assertEquals('', $this->router->getRequestedComponent($this->request));
        self::assertFalse($this->router->supports($this->request));
    }

    /**
     * @covers PKPComponentRouter::supports
     * @covers PKPComponentRouter::getRpcServiceEndpoint
     * @covers PKPComponentRouter::_getValidatedServiceEndpointParts
     * @covers PKPComponentRouter::_retrieveServiceEndpointParts
     * @covers PKPComponentRouter::_validateServiceEndpointParts
     */
    public function testSupportsWithPathinfoAndComponentFileDoesNotExist()
    {
        $mockApplication = $this->_setUpMockEnvironment();

        $_SERVER = [
            'SCRIPT_NAME' => '/index.php',
            'PATH_INFO' => '/context1/$$$call$$$/inexistent/component/fetch-grid'
        ];
        self::assertEquals('inexistent.ComponentHandler', $this->router->getRequestedComponent($this->request));
        // @see PKPComponentRouter::supports() for details
        self::assertTrue($this->router->supports($this->request));
    }

    /**
     * @covers PKPComponentRouter::getRequestedComponent
     * @covers PKPComponentRouter::_getValidatedServiceEndpointParts
     * @covers PKPComponentRouter::_retrieveServiceEndpointParts
     * @covers PKPComponentRouter::_validateServiceEndpointParts
     */
    public function testGetRequestedComponentWithPathinfo()
    {
        $mockApplication = $this->_setUpMockEnvironment();

        $_SERVER = [
            'SCRIPT_NAME' => '/index.php',
            'PATH_INFO' => '/context1/$$$call$$$/path/to/some-component/operation'
        ];
        self::assertEquals('path.to.SomeComponentHandler', $this->router->getRequestedComponent($this->request));
    }

    /**
     * @covers PKPComponentRouter::getRequestedComponent
     * @covers PKPComponentRouter::_getValidatedServiceEndpointParts
     * @covers PKPComponentRouter::_retrieveServiceEndpointParts
     * @covers PKPComponentRouter::_validateServiceEndpointParts
     */
    public function testGetRequestedComponentWithPathinfoAndMalformedComponentString()
    {
        $mockApplication = $this->_setUpMockEnvironment();

        $_SERVER = [
            'SCRIPT_NAME' => '/index.php',
            'PATH_INFO' => '/context1/$$$call$$$/path/to/some-#component/operation'
        ];
        self::assertEquals('', $this->router->getRequestedComponent($this->request));
    }

    /**
     * @covers PKPComponentRouter::getRequestedOp
     * @covers PKPComponentRouter::_getValidatedServiceEndpointParts
     * @covers PKPComponentRouter::_retrieveServiceEndpointParts
     * @covers PKPComponentRouter::_validateServiceEndpointParts
     */
    public function testGetRequestedOpWithPathinfo()
    {
        $mockApplication = $this->_setUpMockEnvironment();

        $_SERVER = [
            'SCRIPT_NAME' => '/index.php',
            'PATH_INFO' => '/context1/$$$call$$$/path/to/some-component/some-op'
        ];
        self::assertEquals('someOp', $this->router->getRequestedOp($this->request));
    }

    /**
     * @covers PKPComponentRouter::getRequestedOp
     * @covers PKPComponentRouter::_getValidatedServiceEndpointParts
     * @covers PKPComponentRouter::_retrieveServiceEndpointParts
     * @covers PKPComponentRouter::_validateServiceEndpointParts
     */
    public function testGetRequestedOpWithPathinfoAndMalformedOpString()
    {
        $mockApplication = $this->_setUpMockEnvironment();

        $_SERVER = [
            'SCRIPT_NAME' => '/index.php',
            'PATH_INFO' => '/context1/$$$call$$$/path/to/some-component/so#me-op'
        ];
        self::assertEquals('', $this->router->getRequestedOp($this->request));
    }

    /**
     * @covers PKPComponentRouter::route
     * @covers PKPComponentRouter::getRpcServiceEndpoint
     * @covers PKPComponentRouter::_getValidatedServiceEndpointParts
     * @covers PKPComponentRouter::_retrieveServiceEndpointParts
     * @covers PKPComponentRouter::_validateServiceEndpointParts
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testRoute()
    {
        $this->setTestConfiguration('mysql');
        $mockApplication = $this->_setUpMockEnvironment();

        $_SERVER = [
            'SCRIPT_NAME' => '/index.php',
            'PATH_INFO' => '/context1/$$$call$$$/grid/notifications/task-notifications-grid/fetch-grid',
        ];
        $_GET = [
            'arg1' => 'val1',
            'arg2' => 'val2'
        ];

        // Simulate context DAOs
        $this->_setUpMockDAOs('context1');

        $this->expectOutputRegex('/{"status":true,"content":".*component-grid-notifications-tasknotificationsgrid/');

        // Route the request. This should call NotificationsGridHandler::fetchGrid()
        // with a reference to the request object as the first argument.
        Registry::set('request', $this->request);
        $user = new \PKP\user\User();

        /*
         * Set the id of the user here to something other than null in order for the UserRolesRequiredPolicy
         * to be able to work as it supposed to.
         * Specifically, the UserRolesRequiredPolicy::effect calls the getByUserIdGroupedByContext function
         * which needs a userId that is not nullable.
         */
        $user->setData('id', 0);
        Registry::set('user', $user);
        $serviceEndpoint = $this->router->getRpcServiceEndpoint($this->request);
        $handler = $serviceEndpoint[0];
        $handler->addPolicy(new UserRolesRequiredPolicy($this->request), true);
        $this->router->route($this->request);

        self::assertNotNull($serviceEndpoint);
        self::assertInstanceOf(\PKP\controllers\grid\notifications\NotificationsGridHandler::class, $handler);
        $firstContextDao = DAORegistry::getDAO('FirstContextDAO');
        self::assertInstanceOf('Context', $firstContextDao->getByPath('context1'));
    }

    /**
     * @covers PKPComponentRouter::url
     * @covers PKPComponentRouter::_urlGetBaseAndContext
     * @covers PKPComponentRouter::_urlGetAdditionalParameters
     * @covers PKPComponentRouter::_urlFromParts
     */
    public function testUrlWithPathinfo()
    {
        $this->setTestConfiguration('request1', 'classes/core/config'); // restful URLs
        $mockApplication = $this->_setUpMockEnvironment();
        $_SERVER = [
            'SERVER_NAME' => 'mydomain.org',
            'SCRIPT_NAME' => '/index.php',
            'PATH_INFO' => '/current-context1/$$$call$$$/current/component-class/current-op'
        ];

        // Simulate context DAOs
        $this->_setUpMockDAOs();

        $result = $this->router->url($this->request);
        self::assertEquals('http://mydomain.org/index.php/current-context1/$$$call$$$/current/component-class/current-op', $result);

        $result = $this->router->url($this->request, 'new-context1');
        self::assertEquals('http://mydomain.org/index.php/new-context1/$$$call$$$/current/component-class/current-op', $result);

        $result = $this->router->url($this->request, null, 'new.NewComponentHandler');
        self::assertEquals('http://mydomain.org/index.php/current-context1/$$$call$$$/new/new-component/current-op', $result);

        $result = $this->router->url($this->request, null, null, 'newOp');
        self::assertEquals('http://mydomain.org/index.php/current-context1/$$$call$$$/current/component-class/new-op', $result);

        $result = $this->router->url($this->request, 'new-context1', 'new.NewComponentHandler');
        self::assertEquals('http://mydomain.org/index.php/new-context1/$$$call$$$/new/new-component/current-op', $result);

        $result = $this->router->url($this->request, 'new-context1', 'new.NewComponentHandler', 'newOp');
        self::assertEquals('http://mydomain.org/index.php/new-context1/$$$call$$$/new/new-component/new-op', $result);

        $result = $this->router->url($this->request, 'new-context1', null, 'newOp');
        self::assertEquals('http://mydomain.org/index.php/new-context1/$$$call$$$/current/component-class/new-op', $result);

        $params = [
            'key1' => 'val1?',
            'key2' => ['val2-1', 'val2?2']
        ];
        $result = $this->router->url($this->request, 'new-context1', null, null, null, $params, null, true);
        self::assertEquals('http://mydomain.org/index.php/new-context1/$$$call$$$/current/component-class/current-op?key1=val1%3F&amp;key2%5B%5D=val2-1&amp;key2%5B%5D=val2%3F2', $result);

        $result = $this->router->url($this->request, 'new-context1', null, null, null, $params, null, false);
        self::assertEquals('http://mydomain.org/index.php/new-context1/$$$call$$$/current/component-class/current-op?key1=val1%3F&key2[]=val2-1&key2[]=val2%3F2', $result);

        $result = $this->router->url($this->request, 'new-context1', null, null, null, null, 'some?anchor');
        self::assertEquals('http://mydomain.org/index.php/new-context1/$$$call$$$/current/component-class/current-op#some%3Fanchor', $result);

        $result = $this->router->url($this->request, 'new-context1', null, 'newOp', null, ['key' => 'val'], 'some-anchor');
        self::assertEquals('http://mydomain.org/index.php/new-context1/$$$call$$$/current/component-class/new-op?key=val#some-anchor', $result);

        $result = $this->router->url($this->request, 'new-context1', null, null, null, ['key1' => 'val1', 'key2' => 'val2'], null, true);
        self::assertEquals('http://mydomain.org/index.php/new-context1/$$$call$$$/current/component-class/current-op?key1=val1&amp;key2=val2', $result);
    }

    /**
     * @covers PKPComponentRouter::url
     * @covers PKPComponentRouter::_urlGetBaseAndContext
     * @covers PKPComponentRouter::_urlGetAdditionalParameters
     * @covers PKPComponentRouter::_urlFromParts
     */
    public function testUrlWithPathinfoAndOverriddenBaseUrl()
    {
        $this->setTestConfiguration('request1', 'classes/core/config'); // contains overridden context
        $mockApplication = $this->_setUpMockEnvironment();
        $_SERVER = [
            'SERVER_NAME' => 'mydomain.org',
            'SCRIPT_NAME' => '/index.php',
            'PATH_INFO' => '/overridden-context/$$$call$$$/current/component-class/current-op'
        ];

        // Simulate context DAOs
        $this->_setUpMockDAOs('overridden-context');

        $result = $this->router->url($this->request);
        self::assertEquals('http://some-domain/xyz-context/$$$call$$$/current/component-class/current-op', $result);
    }
}

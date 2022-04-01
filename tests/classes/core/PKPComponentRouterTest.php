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

require_mock_env('env1');

import('classes.core.Request'); // This will import our mock request class.
import('classes.i18n.Locale'); // This will import our mock Locale class.
import('lib.pkp.tests.classes.core.PKPRouterTestCase');

use PKP\core\PKPComponentRouter;

/**
 * @backupGlobals enabled
 */
class PKPComponentRouterTest extends PKPRouterTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->router = new PKPComponentRouter();
    }

    public function testSupports()
    {
        // This method only exists to override and neutralize the parent class'
        // testSupports() which is not relevant for component routers.
        $this->markTestSkipped();
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
        $mockApplication = $this->_setUpMockEnvironment(self::PATHINFO_ENABLED);

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
        $mockApplication = $this->_setUpMockEnvironment(self::PATHINFO_ENABLED);

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
        $mockApplication = $this->_setUpMockEnvironment(self::PATHINFO_ENABLED);

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
    public function testSupportsWithPathinfoUnsuccessfulComponentFileDoesNotExist()
    {
        $this->markTestSkipped();
        $mockApplication = $this->_setUpMockEnvironment(self::PATHINFO_ENABLED);

        $_SERVER = [
            'SCRIPT_NAME' => '/index.php',
            'PATH_INFO' => '/context1/$$$call$$$/inexistent/component/fetch-grid'
        ];
        self::assertEquals('inexistent.ComponentHandler', $this->router->getRequestedComponent($this->request));
        self::assertFalse($this->router->supports($this->request));
    }

    /**
     * @covers PKPComponentRouter::getRequestedComponent
     * @covers PKPComponentRouter::_getValidatedServiceEndpointParts
     * @covers PKPComponentRouter::_retrieveServiceEndpointParts
     * @covers PKPComponentRouter::_validateServiceEndpointParts
     */
    public function testGetRequestedComponentWithPathinfo()
    {
        $mockApplication = $this->_setUpMockEnvironment(self::PATHINFO_ENABLED);

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
        $mockApplication = $this->_setUpMockEnvironment(self::PATHINFO_ENABLED);

        $_SERVER = [
            'SCRIPT_NAME' => '/index.php',
            'PATH_INFO' => '/context1/$$$call$$$/path/to/some-#component/operation'
        ];
        self::assertEquals('', $this->router->getRequestedComponent($this->request));
    }

    /**
     * @covers PKPComponentRouter::getRequestedComponent
     * @covers PKPComponentRouter::_getValidatedServiceEndpointParts
     * @covers PKPComponentRouter::_retrieveServiceEndpointParts
     * @covers PKPComponentRouter::_validateServiceEndpointParts
     */
    public function testGetRequestedComponentWithPathinfoDisabled()
    {
        $mockApplication = $this->_setUpMockEnvironment(self::PATHINFO_DISABLED);

        $_GET = [
            'component' => 'path.to.some-component',
            'op' => 'operation'
        ];
        self::assertEquals('path.to.SomeComponentHandler', $this->router->getRequestedComponent($this->request));
    }

    /**
     * @covers PKPComponentRouter::getRequestedOp
     * @covers PKPComponentRouter::_getValidatedServiceEndpointParts
     * @covers PKPComponentRouter::_retrieveServiceEndpointParts
     * @covers PKPComponentRouter::_validateServiceEndpointParts
     */
    public function testGetRequestedOpWithPathinfo()
    {
        $mockApplication = $this->_setUpMockEnvironment(self::PATHINFO_ENABLED);

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
    public function testGetRequestedOpWithPathinfoDisabled()
    {
        $mockApplication = $this->_setUpMockEnvironment(self::PATHINFO_DISABLED);

        $_GET = [
            'component' => 'path.to.some-component',
            'op' => 'some-op'
        ];
        self::assertEquals('someOp', $this->router->getRequestedOp($this->request));
    }

    /**
     * @covers PKPComponentRouter::getRequestedOp
     * @covers PKPComponentRouter::_getValidatedServiceEndpointParts
     * @covers PKPComponentRouter::_retrieveServiceEndpointParts
     * @covers PKPComponentRouter::_validateServiceEndpointParts
     */
    public function testGetRequestedOpWithPathinfoDisabledAndMissingComponent()
    {
        $mockApplication = $this->_setUpMockEnvironment(self::PATHINFO_DISABLED);

        $_GET = [
            'op' => 'some-op'
        ];
        self::assertEquals('', $this->router->getRequestedOp($this->request));
    }

    /**
     * @covers PKPComponentRouter::getRequestedOp
     * @covers PKPComponentRouter::_getValidatedServiceEndpointParts
     * @covers PKPComponentRouter::_retrieveServiceEndpointParts
     * @covers PKPComponentRouter::_validateServiceEndpointParts
     */
    public function testGetRequestedOpWithPathinfoAndMalformedOpString()
    {
        $mockApplication = $this->_setUpMockEnvironment(self::PATHINFO_ENABLED);

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
        $mockApplication = $this->_setUpMockEnvironment(self::PATHINFO_ENABLED);

        $_SERVER = [
            'SCRIPT_NAME' => '/index.php',
            'PATH_INFO' => '/context1/$$$call$$$/grid/notifications/task-notifications-grid/fetch-grid'
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
        Registry::set('user', $user);
        $this->router->route($this->request);

        self::assertNotNull($serviceEndpoint = & $this->router->getRpcServiceEndpoint($this->request));
        self::assertInstanceOf('NotificationsGridHandler', $handler = & $serviceEndpoint[0]);
        $firstContextDao = DAORegistry::getDAO('FirstContextDAO');
        self::assertInstanceOf('Context', $firstContextDao->getByPath('context1'));
    }

    /**
     * @covers PKPComponentRouter::url
     * @covers PKPComponentRouter::_urlCanonicalizeNewContext
     * @covers PKPComponentRouter::_urlGetBaseAndContext
     * @covers PKPComponentRouter::_urlGetAdditionalParameters
     * @covers PKPComponentRouter::_urlFromParts
     */
    public function testUrlWithPathinfo()
    {
        $this->setTestConfiguration('request1', 'classes/core/config'); // restful URLs
        $mockApplication = $this->_setUpMockEnvironment(self::PATHINFO_ENABLED);
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

        $result = $this->router->url($this->request, ['new-context1']);
        self::assertEquals('http://mydomain.org/index.php/new-context1/$$$call$$$/current/component-class/current-op', $result);

        $result = $this->router->url($this->request, [], 'new.NewComponentHandler');
        self::assertEquals('http://mydomain.org/index.php/current-context1/$$$call$$$/new/new-component/current-op', $result);

        $result = $this->router->url($this->request, [], null, 'newOp');
        self::assertEquals('http://mydomain.org/index.php/current-context1/$$$call$$$/current/component-class/new-op', $result);

        $result = $this->router->url($this->request, 'new-context1', 'new.NewComponentHandler');
        self::assertEquals('http://mydomain.org/index.php/new-context1/$$$call$$$/new/new-component/current-op', $result);

        $result = $this->router->url($this->request, 'new-context1', 'new.NewComponentHandler', 'newOp');
        self::assertEquals('http://mydomain.org/index.php/new-context1/$$$call$$$/new/new-component/new-op', $result);

        $result = $this->router->url($this->request, 'new-context1', null, 'newOp');
        self::assertEquals('http://mydomain.org/index.php/new-context1/$$$call$$$/current/component-class/new-op', $result);

        $result = $this->router->url($this->request, ['firstContext' => null], null, 'newOp');
        self::assertEquals('http://mydomain.org/index.php/current-context1/$$$call$$$/current/component-class/new-op', $result);

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
     * @covers PKPComponentRouter::_urlCanonicalizeNewContext
     * @covers PKPComponentRouter::_urlGetBaseAndContext
     * @covers PKPComponentRouter::_urlGetAdditionalParameters
     * @covers PKPComponentRouter::_urlFromParts
     */
    public function testUrlWithPathinfoAndOverriddenBaseUrl()
    {
        $this->setTestConfiguration('request1', 'classes/core/config'); // contains overridden context
        $mockApplication = $this->_setUpMockEnvironment(self::PATHINFO_ENABLED);
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

    /**
     * @covers PKPComponentRouter::url
     * @covers PKPComponentRouter::_urlCanonicalizeNewContext
     * @covers PKPComponentRouter::_urlGetBaseAndContext
     * @covers PKPComponentRouter::_urlGetAdditionalParameters
     * @covers PKPComponentRouter::_urlFromParts
     */
    public function testUrlWithoutPathinfo()
    {
        $mockApplication = $this->_setUpMockEnvironment(self::PATHINFO_DISABLED);
        $_SERVER = [
            'SERVER_NAME' => 'mydomain.org',
            'SCRIPT_NAME' => '/index.php',
        ];
        $_GET = [
            'firstContext' => 'current-context1',
            'component' => 'current.component-class',
            'op' => 'current-op'
        ];

        // Simulate context DAOs
        $this->_setUpMockDAOs();

        $result = $this->router->url($this->request);
        self::assertEquals('http://mydomain.org/index.php?firstContext=current-context1&component=current.component-class&op=current-op', $result);

        $result = $this->router->url($this->request, 'new-context1');
        self::assertEquals('http://mydomain.org/index.php?firstContext=new-context1&component=current.component-class&op=current-op', $result);

        $result = $this->router->url($this->request, ['new-context1']);
        self::assertEquals('http://mydomain.org/index.php?firstContext=new-context1&component=current.component-class&op=current-op', $result);

        $result = $this->router->url($this->request, [], 'new.NewComponentHandler');
        self::assertEquals('http://mydomain.org/index.php?firstContext=current-context1&component=new.new-component&op=current-op', $result);

        $result = $this->router->url($this->request, [], null, 'newOp');
        self::assertEquals('http://mydomain.org/index.php?firstContext=current-context1&component=current.component-class&op=new-op', $result);

        $result = $this->router->url($this->request, 'new-context1', 'new.NewComponentHandler');
        self::assertEquals('http://mydomain.org/index.php?firstContext=new-context1&component=new.new-component&op=current-op', $result);

        $result = $this->router->url($this->request, 'new-context1', 'new.NewComponentHandler', 'newOp');
        self::assertEquals('http://mydomain.org/index.php?firstContext=new-context1&component=new.new-component&op=new-op', $result);

        $result = $this->router->url($this->request, 'new-context1', null, 'newOp');
        self::assertEquals('http://mydomain.org/index.php?firstContext=new-context1&component=current.component-class&op=new-op', $result);

        $params = [
            'key1' => 'val1?',
            'key2' => ['val2-1', 'val2?2']
        ];
        $result = $this->router->url($this->request, 'new-context1', null, null, null, $params, null, true);
        self::assertEquals('http://mydomain.org/index.php?firstContext=new-context1&amp;component=current.component-class&amp;op=current-op&amp;key1=val1%3F&amp;key2%5B%5D=val2-1&amp;key2%5B%5D=val2%3F2', $result);

        $result = $this->router->url($this->request, 'new-context1', null, null, null, $params);
        self::assertEquals('http://mydomain.org/index.php?firstContext=new-context1&component=current.component-class&op=current-op&key1=val1%3F&key2[]=val2-1&key2[]=val2%3F2', $result);

        $result = $this->router->url($this->request, 'new-context1', null, null, null, null, 'some?anchor');
        self::assertEquals('http://mydomain.org/index.php?firstContext=new-context1&component=current.component-class&op=current-op#some%3Fanchor', $result);

        $result = $this->router->url($this->request, 'new-context1', null, 'newOp', null, ['key' => 'val'], 'some-anchor');
        self::assertEquals('http://mydomain.org/index.php?firstContext=new-context1&component=current.component-class&op=new-op&key=val#some-anchor', $result);

        $result = $this->router->url($this->request, 'new-context1', null, null, null, ['key1' => 'val1', 'key2' => 'val2'], null, true);
        self::assertEquals('http://mydomain.org/index.php?firstContext=new-context1&amp;component=current.component-class&amp;op=current-op&amp;key1=val1&amp;key2=val2', $result);
    }

    /**
     * @covers PKPComponentRouter::url
     * @covers PKPComponentRouter::_urlCanonicalizeNewContext
     * @covers PKPComponentRouter::_urlGetBaseAndContext
     * @covers PKPComponentRouter::_urlGetAdditionalParameters
     * @covers PKPComponentRouter::_urlFromParts
     */
    public function testUrlWithoutPathinfoAndOverriddenBaseUrl()
    {
        $this->setTestConfiguration('request2', 'classes/core/config'); // contains overridden context
        $mockApplication = $this->_setUpMockEnvironment(self::PATHINFO_DISABLED);
        $_SERVER = [
            'SERVER_NAME' => 'mydomain.org',
            'SCRIPT_NAME' => '/index.php',
        ];
        $_GET = [
            'firstContext' => 'overridden-context',
            'component' => 'current.component-class',
            'op' => 'current-op'
        ];

        // Simulate context DAOs
        $this->_setUpMockDAOs('overridden-context');

        // NB: This also tests whether unusual URL elements like user, password and port
        // will be handled correctly.
        $result = $this->router->url($this->request);
        self::assertEquals('http://some-user:some-pass@some-domain:8080/?firstContext=xyz-context&component=current.component-class&op=current-op', $result);
    }
}

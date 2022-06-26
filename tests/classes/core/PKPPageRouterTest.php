<?php

/**
 * @file tests/classes/core/PKPPageRouterTest.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PKPPageRouterTest
 * @ingroup tests_classes_core
 *
 * @see PKPPageRouter
 *
 * @brief Tests for the PKPPageRouter class.
 */

use PKP\core\PKPPageRouter;

require_mock_env('env1');

import('lib.pkp.tests.classes.core.PKPRouterTestCase');
import('classes.security.Validation'); // Import our mock validation class before the autoloader grabs the right one.

use PKP\security\Validation;

/**
 * @backupGlobals enabled
 */
class PKPPageRouterTest extends PKPRouterTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->router = $this->getMockBuilder(PKPPageRouter::class)
            ->setMethods(['getCacheablePages'])
            ->getMock();
        $this->router->expects($this->any())
            ->method('getCacheablePages')
            ->will($this->returnValue(['cacheable']));
    }

    /**
     * @covers PKPPageRouter::isCacheable
     */
    public function testIsCacheableNotInstalled()
    {
        $this->setTestConfiguration('request2', 'classes/core/config'); // not installed
        $mockApplication = $this->_setUpMockEnvironment(self::PATHINFO_ENABLED);
        self::assertFalse($this->router->isCacheable($this->request));
    }

    /**
     * @covers PKPPageRouter::isCacheable
     */
    public function testIsCacheableWithPost()
    {
        $this->setTestConfiguration('request1', 'classes/core/config'); // installed
        $mockApplication = $this->_setUpMockEnvironment(self::PATHINFO_ENABLED);
        $_POST = ['somevar' => 'someval'];
        self::assertFalse($this->router->isCacheable($this->request));
    }

    /**
     * @covers PKPPageRouter::isCacheable
     */
    public function testIsCacheableWithPathinfo()
    {
        $this->setTestConfiguration('request1', 'classes/core/config'); // installed
        $mockApplication = $this->_setUpMockEnvironment(self::PATHINFO_ENABLED);
        $_GET = ['somevar' => 'someval'];
        $_SERVER = [
            'PATH_INFO' => '/context1/somepage',
            'SCRIPT_NAME' => '/index.php',
        ];
        self::assertFalse($this->router->isCacheable($this->request));

        $_GET = [];
        self::assertFalse($this->router->isCacheable($this->request));
    }

    /**
     * @covers PKPPageRouter::isCacheable
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testIsCacheableWithPathinfoSuccess()
    {
        $this->setTestConfiguration('request1', 'classes/core/config'); // installed
        $mockApplication = $this->_setUpMockEnvironment(self::PATHINFO_ENABLED);
        $_GET = [];
        $_SERVER = [
            'PATH_INFO' => '/context1/cacheable',
            'SCRIPT_NAME' => '/index.php',
        ];

        self::assertTrue($this->router->isCacheable($this->request, true));

        Validation::setIsLoggedIn(true);
        self::assertFalse($this->router->isCacheable($this->request, true));
        Validation::setIsLoggedIn(false);
    }

    /**
     * @covers PKPPageRouter::isCacheable
     */
    public function testIsCacheableWithoutPathinfo()
    {
        $this->setTestConfiguration('request1', 'classes/core/config'); // installed
        $mockApplication = $this->_setUpMockEnvironment(self::PATHINFO_DISABLED);
        $_GET = ['somevar' => 'someval'];
        self::assertFalse($this->router->isCacheable($this->request, true));

        $_GET = [
            'firstContext' => 'something',
            'page' => 'something',
            'op' => 'something',
            'path' => 'something'
        ];
        self::assertFalse($this->router->isCacheable($this->request, true));
    }

    /**
     * @covers PKPPageRouter::isCacheable
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testIsCacheableWithoutPathinfoSuccess()
    {
        $this->setTestConfiguration('request1', 'classes/core/config'); // installed
        $mockApplication = $this->_setUpMockEnvironment(self::PATHINFO_DISABLED);

        $_GET = [
            'page' => 'cacheable'
        ];
        self::assertTrue($this->router->isCacheable($this->request, true));
    }

    /**
     * @covers PKPRouter::getCacheFilename
     */
    public function testGetCacheFilename()
    {
        // Override parent test
        $this->markTestSkipped();
    }

    /**
     * @covers PKPPageRouter::getCacheFilename
     */
    public function testGetCacheFilenameWithPathinfo()
    {
        $mockApplication = $this->_setUpMockEnvironment(self::PATHINFO_ENABLED);
        $_SERVER = [
            'PATH_INFO' => '/context1/index',
            'SCRIPT_NAME' => '/index.php',
        ];
        $expectedId = '/context1/index-en_US';
        self::assertEquals(Core::getBaseDir() . '/cache/wc-' . md5($expectedId) . '.html', $this->router->getCacheFilename($this->request));
    }

    /**
     * @covers PKPPageRouter::getCacheFilename
     */
    public function testGetCacheFilenameWithoutPathinfo()
    {
        $mockApplication = $this->_setUpMockEnvironment(self::PATHINFO_DISABLED);
        $_GET = [
            'firstContext' => 'something',
            'page' => 'index'
        ];
        $expectedId = 'something-index---en_US';
        self::assertEquals(Core::getBaseDir() . '/cache/wc-' . md5($expectedId) . '.html', $this->router->getCacheFilename($this->request));
    }

    /**
     * @covers PKPPageRouter::getRequestedPage
     */
    public function testGetRequestedPageWithPathinfo()
    {
        $mockApplication = $this->_setUpMockEnvironment(self::PATHINFO_ENABLED);

        $_SERVER = [
            'PATH_INFO' => '/context1/some#page',
            'SCRIPT_NAME' => '/index.php',
        ];
        self::assertEquals('somepage', $this->router->getRequestedPage($this->request));
    }

    /**
     * @covers PKPPageRouter::getRequestedPage
     */
    public function testGetRequestedPageWithPathinfoDisabled()
    {
        $mockApplication = $this->_setUpMockEnvironment(self::PATHINFO_DISABLED);

        $_GET['page'] = 'some#page';
        self::assertEquals('somepage', $this->router->getRequestedPage($this->request));
    }

    /**
     * @covers PKPPageRouter::getRequestedPage
     */
    public function testGetRequestedPageWithEmtpyPage()
    {
        $mockApplication = $this->_setUpMockEnvironment(self::PATHINFO_ENABLED);

        $_SERVER = [
            'PATH_INFO' => '/context1',
            'SCRIPT_NAME' => '/index.php',
        ];
        self::assertEquals('', $this->router->getRequestedPage($this->request));
    }

    /**
     * @covers PKPPageRouter::getRequestedPage
     */
    public function testGetRequestedPageWithPathinfoDisabledAndEmtpyPage()
    {
        $mockApplication = $this->_setUpMockEnvironment(self::PATHINFO_DISABLED);

        unset($_GET['page']);
        self::assertEquals('', $this->router->getRequestedPage($this->request));
    }

    /**
     * @covers PKPPageRouter::getRequestedOp
     */
    public function testGetRequestedOpWithPathinfo()
    {
        $mockApplication = $this->_setUpMockEnvironment(self::PATHINFO_ENABLED);

        $_SERVER = [
            'PATH_INFO' => '/context1/somepage/some#op',
            'SCRIPT_NAME' => '/index.php',
        ];
        self::assertEquals('someop', $this->router->getRequestedOp($this->request));
    }

    /**
     * @covers PKPPageRouter::getRequestedOp
     */
    public function testGetRequestedOpWithPathinfoDisabled()
    {
        $mockApplication = $this->_setUpMockEnvironment(self::PATHINFO_DISABLED);

        $_GET['op'] = 'some#op';
        self::assertEquals('someop', $this->router->getRequestedOp($this->request));
    }

    /**
     * @covers PKPPageRouter::getRequestedOp
     */
    public function testGetRequestedOpWithEmptyOp()
    {
        $mockApplication = $this->_setUpMockEnvironment(self::PATHINFO_ENABLED);

        $_SERVER = [
            'PATH_INFO' => '/context1/somepage',
            'SCRIPT_NAME' => '/index.php',
        ];
        self::assertEquals('index', $this->router->getRequestedOp($this->request));
    }

    /**
     * @covers PKPPageRouter::getRequestedOp
     */
    public function testGetRequestedOpWithPathinfoDisabledAndEmptyOp()
    {
        $mockApplication = $this->_setUpMockEnvironment(self::PATHINFO_DISABLED);

        unset($_GET['op']);
        self::assertEquals('index', $this->router->getRequestedOp($this->request));
    }

    /**
     * @covers PKPPageRouter::url
     */
    public function testUrlWithPathinfo()
    {
        $this->setTestConfiguration('request1', 'classes/core/config'); // restful URLs
        $mockApplication = $this->_setUpMockEnvironment(self::PATHINFO_ENABLED);
        $_SERVER = [
            'SERVER_NAME' => 'mydomain.org',
            'SCRIPT_NAME' => '/index.php',
            'PATH_INFO' => '/current-context1/current-page/current-op'
        ];

        // Simulate context DAOs
        $this->_setUpMockDAOs();

        $result = $this->router->url($this->request);
        self::assertEquals('http://mydomain.org/index.php/current-context1/current-page/current-op', $result);

        $result = $this->router->url($this->request, 'new-context1');
        self::assertEquals('http://mydomain.org/index.php/new-context1', $result);

        $result = $this->router->url($this->request, ['new?context1']);
        self::assertEquals('http://mydomain.org/index.php/new%3Fcontext1', $result);

        $result = $this->router->url($this->request, [], 'new-page');
        self::assertEquals('http://mydomain.org/index.php/current-context1/new-page', $result);

        $result = $this->router->url($this->request, [], null, 'new-op');
        self::assertEquals('http://mydomain.org/index.php/current-context1/current-page/new-op', $result);

        $result = $this->router->url($this->request, 'new-context1', 'new-page');
        self::assertEquals('http://mydomain.org/index.php/new-context1/new-page', $result);

        $result = $this->router->url($this->request, 'new-context1', 'new-page', 'new-op');
        self::assertEquals('http://mydomain.org/index.php/new-context1/new-page/new-op', $result);

        $result = $this->router->url($this->request, 'new-context1', null, 'new-op');
        self::assertEquals('http://mydomain.org/index.php/new-context1/index/new-op', $result);

        $result = $this->router->url($this->request, 'new-context1', null, null, 'add?path');
        self::assertEquals('http://mydomain.org/index.php/new-context1/index/index/add%3Fpath', $result);

        $result = $this->router->url($this->request, 'new-context1', null, null, ['add-path1', 'add?path2']);
        self::assertEquals('http://mydomain.org/index.php/new-context1/index/index/add-path1/add%3Fpath2', $result);

        $result = $this->router->url($this->request, ['firstContext' => null], null, 'new-op', 'add-path');
        self::assertEquals('http://mydomain.org/index.php/current-context1/current-page/new-op/add-path', $result);

        $result = $this->router->url(
            $this->request,
            'new-context1',
            null,
            null,
            null,
            [
                'key1' => 'val1?',
                'key2' => ['val2-1', 'val2?2']
            ]
        );
        self::assertEquals('http://mydomain.org/index.php/new-context1?key1=val1%3F&key2[]=val2-1&key2[]=val2%3F2', $result);

        $result = $this->router->url($this->request, 'new-context1', null, null, null, null, 'some?anchor');
        self::assertEquals('http://mydomain.org/index.php/new-context1#someanchor', $result);

        $result = $this->router->url($this->request, 'new-context1', null, null, null, null, 'some/anchor');
        self::assertEquals('http://mydomain.org/index.php/new-context1#some/anchor', $result);

        $result = $this->router->url($this->request, 'new-context1', null, 'new-op', 'add-path', ['key' => 'val'], 'some-anchor');
        self::assertEquals('http://mydomain.org/index.php/new-context1/index/new-op/add-path?key=val#some-anchor', $result);

        $result = $this->router->url($this->request, 'new-context1', null, null, null, ['key1' => 'val1', 'key2' => 'val2'], null, true);
        self::assertEquals('http://mydomain.org/index.php/new-context1?key1=val1&amp;key2=val2', $result);
    }

    /**
     * @covers PKPPageRouter::url
     */
    public function testUrlWithPathinfoAndOverriddenBaseUrl()
    {
        $this->setTestConfiguration('request1', 'classes/core/config'); // contains overridden context

        // Set up a request with an overridden context
        $mockApplication = $this->_setUpMockEnvironment(self::PATHINFO_ENABLED);
        $_SERVER = [
            'SERVER_NAME' => 'mydomain.org',
            'SCRIPT_NAME' => '/index.php',
            'PATH_INFO' => '/overridden-context/current-page/current-op'
        ];
        $this->_setUpMockDAOs('overridden-context');
        $result = $this->router->url($this->request);
        self::assertEquals('http://some-domain/xyz-context/current-page/current-op', $result);
    }

    /**
     * @covers PKPPageRouter::url
     */
    public function testUrlWithPathinfoAndOverriddenNewContext()
    {
        $this->setTestConfiguration('request1', 'classes/core/config'); // contains overridden context

        // Same set-up as in testUrlWithPathinfoAndOverriddenBaseUrl()
        // but this time use a request with non-overridden context and
        // 'overridden-context' as new context. (Reproduces #5118)
        $mockApplication = $this->_setUpMockEnvironment(self::PATHINFO_ENABLED);
        $_SERVER = [
            'SERVER_NAME' => 'mydomain.org',
            'SCRIPT_NAME' => '/index.php',
            'PATH_INFO' => '/current-context1/current-page/current-op'
        ];
        $this->_setUpMockDAOs('current-context1', true);
        $result = $this->router->url($this->request, 'overridden-context', 'new-page');
        self::assertEquals('http://some-domain/xyz-context/new-page', $result);
    }

    /**
     * @covers PKPPageRouter::url
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
            'page' => 'current-page',
            'op' => 'current-op'
        ];

        // Simulate context DAOs
        $this->_setUpMockDAOs();

        $result = $this->router->url($this->request);
        self::assertEquals('http://mydomain.org/index.php?firstContext=current-context1&page=current-page&op=current-op', $result);

        $result = $this->router->url($this->request, 'new-context1');
        self::assertEquals('http://mydomain.org/index.php?firstContext=new-context1', $result);

        $result = $this->router->url($this->request, ['new-context1']);
        self::assertEquals('http://mydomain.org/index.php?firstContext=new-context1', $result);

        $result = $this->router->url($this->request, [], 'new-page');
        self::assertEquals('http://mydomain.org/index.php?firstContext=current-context1&page=new-page', $result);

        $result = $this->router->url($this->request, [], null, 'new-op');
        self::assertEquals('http://mydomain.org/index.php?firstContext=current-context1&page=current-page&op=new-op', $result);

        $result = $this->router->url($this->request, 'new-context1', 'new-page');
        self::assertEquals('http://mydomain.org/index.php?firstContext=new-context1&page=new-page', $result);

        $result = $this->router->url($this->request, 'new-context1', 'new-page', 'new-op');
        self::assertEquals('http://mydomain.org/index.php?firstContext=new-context1&page=new-page&op=new-op', $result);

        $result = $this->router->url($this->request, 'new-context1', null, 'new-op');
        self::assertEquals('http://mydomain.org/index.php?firstContext=new-context1&page=index&op=new-op', $result);

        $result = $this->router->url($this->request, 'new-context1', null, null, 'add?path');
        self::assertEquals('http://mydomain.org/index.php?firstContext=new-context1&page=index&op=index&path[]=add%3Fpath', $result);

        $result = $this->router->url($this->request, 'new-context1', null, null, ['add-path1', 'add?path2']);
        self::assertEquals('http://mydomain.org/index.php?firstContext=new-context1&page=index&op=index&path[]=add-path1&path[]=add%3Fpath2', $result);

        $result = $this->router->url(
            $this->request,
            'new-context1',
            null,
            null,
            null,
            [
                'key1' => 'val1?',
                'key2' => ['val2-1', 'val2?2']
            ]
        );
        self::assertEquals('http://mydomain.org/index.php?firstContext=new-context1&key1=val1%3F&key2[]=val2-1&key2[]=val2%3F2', $result);

        $result = $this->router->url($this->request, 'new-context1', null, null, null, null, 'some?anchor');
        self::assertEquals('http://mydomain.org/index.php?firstContext=new-context1#someanchor', $result);

        $result = $this->router->url($this->request, 'new-context1', null, null, null, null, 'some/anchor');
        self::assertEquals('http://mydomain.org/index.php?firstContext=new-context1#some/anchor', $result);

        $result = $this->router->url($this->request, 'new-context1', null, 'new-op', 'add-path', ['key' => 'val'], 'some-anchor');
        self::assertEquals('http://mydomain.org/index.php?firstContext=new-context1&page=index&op=new-op&path[]=add-path&key=val#some-anchor', $result);

        $result = $this->router->url($this->request, 'new-context1', null, null, null, ['key1' => 'val1', 'key2' => 'val2'], null, true);
        self::assertEquals('http://mydomain.org/index.php?firstContext=new-context1&amp;key1=val1&amp;key2=val2', $result);
    }

    /**
     * @covers PKPPageRouter::url
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
            'page' => 'current-page',
            'op' => 'current-op'
        ];

        // Simulate context DAOs
        $this->_setUpMockDAOs('overridden-context');

        // NB: This also tests whether unusual URL elements like user, password and port
        // will be handled correctly.
        $result = $this->router->url($this->request);
        self::assertEquals('http://some-user:some-pass@some-domain:8080/?firstContext=xyz-context&page=current-page&op=current-op', $result);
    }
}

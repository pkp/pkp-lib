<?php

/**
 * @file tests/classes/core/PKPRequestTest.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PKPRequestTest
 * @ingroup tests_classes_core
 *
 * @see PKPRequest
 *
 * @brief Tests for the PKPRequest class.
 */

namespace PKP\tests\classes\core;

use APP\core\Request;
use PKP\core\PKPRequest;
use PKP\core\Registry;
use PKP\plugins\Hook;
use PKP\tests\PKPTestCase;

/**
 * @backupGlobals enabled
 */
class PKPRequestTest extends PKPTestCase
{
    protected PKPRequest $request;

    /**
     * @see PKPTestCase::getMockedRegistryKeys()
     */
    protected function getMockedRegistryKeys(): array
    {
        return [...parent::getMockedRegistryKeys(), 'configData'];
    }

    protected function setUp(): void
    {
        parent::setUp();
        Hook::rememberCalledHooks();
        $this->request = new Request();
    }

    protected function tearDown(): void
    {
        Hook::resetCalledHooks();
        parent::tearDown();
    }

    /**
     * @covers PKPRequest::isPathInfoEnabled
     */
    public function testIsPathInfoEnabled1()
    {
        $this->setTestConfiguration('request1', 'classes/core/config');
        self::assertTrue($this->request->isPathInfoEnabled());
    }

    /**
     * @covers PKPRequest::isPathInfoEnabled
     */
    public function testIsPathInfoEnabled2()
    {
        $this->setTestConfiguration('request2', 'classes/core/config');
        self::assertFalse($this->request->isPathInfoEnabled());
    }

    /**
     * @covers PKPRequest::isRestfulUrlsEnabled
     */
    public function testIsRestfulUrlsEnabled1()
    {
        $this->setTestConfiguration('request1', 'classes/core/config');
        self::assertFalse($this->request->isRestfulUrlsEnabled());
    }

    /**
     * @covers PKPRequest::isRestfulUrlsEnabled
     */
    public function testIsRestfulUrlsEnabled2()
    {
        $this->setTestConfiguration('request2', 'classes/core/config');
        self::assertTrue($this->request->isRestfulUrlsEnabled());
    }

    /**
     * @covers PKPRequest::redirectUrl
     */
    public function testRedirectUrl()
    {
        Hook::add('Request::redirect', [$this, 'redirectUrlHook']);
        $this->request->redirectUrl('http://some.url/');
        self::assertEquals(
            [['Request::redirect', ['http://some.url/']]],
            Hook::getCalledHooks()
        );
        Hook::clear('Request::redirect');
    }

    /**
     * A hook for redirection testing.
     *
     * @param string $hookName
     * @param array $args
     */
    public function redirectUrlHook($hookName, $args)
    {
        // Returning true will avoid actual redirection.
        return true;
    }

    /**
     * @covers PKPRequest::getBaseUrl
     */
    public function testGetBaseUrl()
    {
        $this->setTestConfiguration('request1', 'classes/core/config'); // baseurl1
        $_SERVER = [
            'SCRIPT_NAME' => '/index.php',
        ];
        self::assertEquals('http://baseurl1/', $this->request->getBaseUrl());

        // Two hooks should have been triggered.
        self::assertEquals(
            [
                ['Request::getServerHost', [false, false, true]],
                ['Request::getBaseUrl', ['http://baseurl1/']]
            ],
            Hook::getCalledHooks()
        );

        // Calling getBaseUrl twice should return the same
        // result without triggering the hooks again.
        Hook::resetCalledHooks();
        self::assertEquals('http://baseurl1/', $this->request->getBaseUrl());
        self::assertEquals(
            [],
            Hook::getCalledHooks()
        );
    }

    /**
     * @covers PKPRequest::getBaseUrl
     */
    public function testGetBaseUrlWithHostDetection()
    {
        $this->setTestConfiguration('request1', 'classes/core/config');
        $_SERVER = [
            'SERVER_NAME' => 'hostname',
            'SCRIPT_NAME' => '/some/base/path'
        ];
        self::assertEquals('http://hostname/some/base/path', $this->request->getBaseUrl());
    }

    /**
     * @covers PKPRequest::getBasePath
     */
    public function testGetBasePath()
    {
        $_SERVER = [
            'SCRIPT_NAME' => '/some/base/path'
        ];
        self::assertEquals('/some/base/path', $this->request->getBasePath());

        // The hook should have been triggered once.
        self::assertEquals(
            [['Request::getBasePath', ['/some/base/path']]],
            Hook::getCalledHooks()
        );

        // Calling getBasePath twice should return the same
        // result without triggering the hook again.
        Hook::resetCalledHooks();
        self::assertEquals('/some/base/path', $this->request->getBasePath());
        self::assertEquals(
            [],
            Hook::getCalledHooks()
        );
    }

    /**
     * @covers PKPRequest::getBasePath
     */
    public function testGetEmptyBasePath()
    {
        $_SERVER = [
            'SCRIPT_NAME' => '/main'
        ];
        self::assertEquals('/main', $this->request->getBasePath());
    }

    /**
     * @covers PKPRequest::getRequestPath
     */
    public function testGetRequestPath()
    {
        $_SERVER = [
            'SCRIPT_NAME' => 'some/script/name'
        ];
        $this->setTestConfiguration('request1', 'classes/core/config'); // no restful URLs

        self::assertEquals('some/script/name', $this->request->getRequestPath());

        // The hook should have been triggered once.
        self::assertEquals(
            [['Request::getRequestPath', ['some/script/name']]],
            Hook::getCalledHooks()
        );

        // Calling getRequestPath() twice should return the same
        // result without triggering the hook again.
        Hook::resetCalledHooks();
        self::assertEquals('some/script/name', $this->request->getRequestPath());
        self::assertEquals(
            [],
            Hook::getCalledHooks()
        );
    }

    /**
     * @covers PKPRequest::getRequestPath
     */
    public function testGetRequestPathRestful()
    {
        $_SERVER = [
            'SCRIPT_NAME' => 'some/script/name'
        ];
        $this->setTestConfiguration('request2', 'classes/core/config'); // restful URLs

        self::assertEquals('some/script/name', $this->request->getRequestPath());
    }


    /**
     * @covers PKPRequest::getRequestPath
     */
    public function testGetRequestPathWithPathinfo()
    {
        $_SERVER = [
            'SCRIPT_NAME' => 'some/script/name',
            'PATH_INFO' => '/extra/path'
        ];
        $this->setTestConfiguration('request1', 'classes/core/config'); // path info enabled

        self::assertEquals('some/script/name/extra/path', $this->request->getRequestPath());
    }

    /**
     * @covers PKPRequest::getRequestPath
     */
    public function testGetRequestPathWithoutPathinfo()
    {
        $_SERVER = [
            'SCRIPT_NAME' => 'some/script/name',
            'PATH_INFO' => '/extra/path'
        ];
        $this->setTestConfiguration('request2', 'classes/core/config'); // path info disabled

        self::assertEquals('some/script/name', $this->request->getRequestPath());
    }

    /**
     * @covers PKPRequest::getServerHost
     */
    public function testGetServerHostLocalhost()
    {
        // if none of the server variables is set then return the default
        $_SERVER = [
            'SCRIPT_NAME' => '/index.php',
        ];
        self::assertEquals('localhost', $this->request->getServerHost());
    }

    /**
     * @covers PKPRequest::getServerHost
     * @depends testGetServerHostLocalhost
     */
    public function testGetServerHostWithHostname()
    {
        // if SERVER_NAME is set then return it
        $_SERVER = [
            'SERVER_NAME' => 'hostname',
            'SCRIPT_NAME' => ''
        ];
        self::assertEquals('hostname', $this->request->getServerHost());
    }

    /**
     * @covers PKPRequest::getServerHost
     * @depends testGetServerHostLocalhost
     */
    public function testGetServerHostWithServerName()
    {
        // if SERVER_NAME is set then return it
        $_SERVER = [
            'SERVER_NAME' => 'hostname',
            'SCRIPT_NAME' => '/index.php',
        ];
        self::assertEquals('hostname', $this->request->getServerHost());
    }

    /**
     * @covers PKPRequest::getServerHost
     * @depends testGetServerHostWithHostname
     */
    public function testGetServerHostWithHttpHost()
    {
        // if HTTP_HOST is set then return it
        $_SERVER = [
            'SERVER_NAME' => 'hostname',
            'HTTP_HOST' => 'http_host',
            'SCRIPT_NAME' => '/index.php',
        ];
        self::assertEquals('http_host', $this->request->getServerHost());
    }

    /**
     * @covers PKPRequest::getServerHost
     * @depends testGetServerHostWithHttpHost
     */
    public function testGetServerHostWithHttpXForwardedHost()
    {
        // if HTTP_X_FORWARDED_HOST is set then return it
        $_SERVER = [
            'SERVER_NAME' => 'hostname',
            'HTTP_HOST' => 'http_host',
            'HTTP_X_FORWARDED_HOST' => 'x_host',
            'SCRIPT_NAME' => '/index.php',
        ];
        self::assertEquals('x_host', $this->request->getServerHost());
    }

    /**
     * @covers PKPRequest::getProtocol
     */
    public function testGetProtocolNoHttpsVariable()
    {
        $_SERVER = [];
        self::assertEquals('http', $this->request->getProtocol());
        // The hook should have been triggered once.
        self::assertEquals(
            [['Request::getProtocol', ['http']]],
            Hook::getCalledHooks()
        );

        // Calling getProtocol() twice should return the same
        // result without triggering the hook again.
        Hook::resetCalledHooks();
        self::assertEquals('http', $this->request->getProtocol());
        self::assertEquals(
            [],
            Hook::getCalledHooks()
        );
    }

    /**
     * @covers PKPRequest::getProtocol
     */
    public function testGetProtocolHttpsVariableOff()
    {
        $_SERVER = [
            'HTTPS' => 'OFF',
            'SCRIPT_NAME' => '/index.php',
        ];
        self::assertEquals('http', $this->request->getProtocol());
    }

    /**
     * @covers PKPRequest::getProtocol
     */
    public function testGetProtocolHttpsVariableOn()
    {
        $_SERVER = [
            'HTTPS' => 'ON',
            'SCRIPT_NAME' => '/index.php',
        ];
        self::assertEquals('https', $this->request->getProtocol());
    }

    /**
     * @covers PKPRequest::getRemoteAddr
     */
    public function testTrustXForwardedForOn()
    {
        [$forwardedIp, $remoteIp] = $this->getRemoteAddrTestPrepare(
            ['trust_x_forwarded_for' => true]
        );
        self::assertEquals($forwardedIp, $this->request->getRemoteAddr());
    }

    /**
     * @covers PKPRequest::getRemoteAddr
     */
    public function testTrustXForwardedForOff()
    {
        [$forwardedIp, $remoteIp] = $this->getRemoteAddrTestPrepare(
            ['trust_x_forwarded_for' => false]
        );
        self::assertEquals($remoteIp, $this->request->getRemoteAddr());
    }

    /**
     * @covers PKPRequest::getRemoteAddr
     */
    public function testTrustXForwardedForNotSet()
    {
        [$forwardedIp, $remoteIp] = $this->getRemoteAddrTestPrepare([]);
        self::assertEquals($forwardedIp, $this->request->getRemoteAddr());
    }


    /**
     * Helper function for testTrustXForwardedFor tests that prepares the
     * environment
     *
     * @param mixed $generalConfigData Array containing overwrites for the
     * general section of the config
     */
    private function getRemoteAddrTestPrepare($generalConfigData = [])
    {
        // Remove cached IP address from registry
        Registry::delete('remoteIpAddr');

        $_SERVER['HTTP_X_FORWARDED_FOR'] = '1.1.1.1';
        $_SERVER['REMOTE_ADDR'] = '2.2.2.2';

        $configData = & Registry::get('configData', true, []);
        $configData['general'] = $generalConfigData;

        return [$_SERVER['HTTP_X_FORWARDED_FOR'], $_SERVER['REMOTE_ADDR']];
    }

    /**
     * @covers PKPRequest::getUserVar
     */
    public function testGetUserVar()
    {
        $_GET = [
            'par1' => '\'val1\'',
            'par2' => ' val2'
        ];
        $_POST = [
            'par3' => 'val3 ',
            'par4' => 'val4'
        ];
        self::assertEquals("'val1'", $this->request->getUserVar('par1'));
        self::assertEquals('val2', $this->request->getUserVar('par2'));
        self::assertEquals('val3', $this->request->getUserVar('par3'));
        self::assertEquals('val4', $this->request->getUserVar('par4'));
    }

    /**
     * @covers PKPRequest::getUserVars
     */
    public function testGetUserVars()
    {
        $_GET = [
            'par1' => '\'val1\'',
            'par2' => ' val2'
        ];
        $_POST = [
            'par3' => 'val3 ',
            'par4' => 'val4'
        ];
        $expectedResult = [
            'par1' => "'val1'",
            'par2' => 'val2',
            'par3' => 'val3',
            'par4' => 'val4'
        ];
        self::assertEquals($expectedResult, $this->request->getUserVars());
    }
}

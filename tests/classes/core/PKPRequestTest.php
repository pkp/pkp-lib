<?php

/**
 * @file tests/classes/core/PKPRequestTest.php
 *
 * Copyright (c) 2014 Simon Fraser University Library
 * Copyright (c) 2000-2014 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class PKPRequestTest
 * @ingroup tests_classes_core
 * @see PKPRequest
 *
 * @brief Tests for the PKPRequest class.
 */


require_mock_env('env1');

import('lib.pkp.tests.PKPTestCase');
import('lib.pkp.classes.core.PKPRequest');
import('lib.pkp.classes.plugins.HookRegistry'); // This imports our mock HookRegistry implementation.

class PKPRequestTest extends PKPTestCase {
	protected $request;

	public function setUp() {
		parent::setUp();
		HookRegistry::rememberCalledHooks();
		$this->request = new PKPRequest();
	}

	public function tearDown() {
		HookRegistry::resetCalledHooks();
	}

	/**
	 * @covers PKPRequest::isPathInfoEnabled
	 */
	public function testIsPathInfoEnabled1() {
		$this->setTestConfiguration('request1', 'classes/core/config');
		self::assertTrue($this->request->isPathInfoEnabled());
	}

	/**
	 * @covers PKPRequest::isPathInfoEnabled
	 */
	public function testIsPathInfoEnabled2() {
		$this->setTestConfiguration('request2', 'classes/core/config');
		self::assertFalse($this->request->isPathInfoEnabled());
	}

	/**
	 * @covers PKPRequest::isRestfulUrlsEnabled
	 */
	public function testIsRestfulUrlsEnabled1() {
		$this->setTestConfiguration('request1', 'classes/core/config');
		self::assertFalse($this->request->isRestfulUrlsEnabled());
	}

	/**
	 * @covers PKPRequest::isRestfulUrlsEnabled
	 */
	public function testIsRestfulUrlsEnabled2() {
		$this->setTestConfiguration('request2', 'classes/core/config');
		self::assertTrue($this->request->isRestfulUrlsEnabled());
	}

	/**
	 * @covers PKPRequest::redirectUrl
	 */
	public function testRedirectUrl() {
		HookRegistry::register('Request::redirect', array($this, 'redirectUrlHook'));
		$this->request->redirectUrl('http://some.url/');
		self::assertEquals(
			array(array('Request::redirect' , array('http://some.url/'))),
			HookRegistry::getCalledHooks()
		);
		HookRegistry::clear('Request::redirect');
	}

	/**
	 * A hook for redirection testing.
	 * @param $hookName string
	 * @param $args array
	 */
	public function redirectUrlHook($hookName, $args) {
		// Returning true will avoid actual redirection.
		return true;
	}

	/**
	 * @covers PKPRequest::getBaseUrl
	 */
	public function testGetBaseUrl() {
		$this->setTestConfiguration('request1', 'classes/core/config'); // baseurl1
		$_SERVER = array();
		self::assertEquals('http://baseurl1/', $this->request->getBaseUrl());

		// Two hooks should have been triggered.
		self::assertEquals(
			array(
				array('Request::getServerHost' , array(null)),
				array('Request::getBaseUrl' , array('http://baseurl1/'))
			),
			HookRegistry::getCalledHooks()
		);

		// Calling getBaseUrl twice should return the same
		// result without triggering the hooks again.
		HookRegistry::resetCalledHooks();
		self::assertEquals('http://baseurl1/', $this->request->getBaseUrl());
		self::assertEquals(
			array(),
			HookRegistry::getCalledHooks()
		);
	}

	/**
	 * @covers PKPRequest::getBaseUrl
	 */
	public function testGetBaseUrlWithHostDetection() {
		$this->setTestConfiguration('request1', 'classes/core/config');
		$_SERVER = array(
			'HOSTNAME' => 'hostname',
			'SCRIPT_NAME' => '/some/base/path'
		);
		self::assertEquals('http://hostname/some/base', $this->request->getBaseUrl());
	}

	/**
	 * @covers PKPRequest::getBasePath
	 */
	public function testGetBasePath() {
		$_SERVER = array(
			'SCRIPT_NAME' => '/some/base/path'
		);
		self::assertEquals('/some/base', $this->request->getBasePath());

		// The hook should have been triggered once.
		self::assertEquals(
			array(array('Request::getBasePath' , array('/some/base'))),
			HookRegistry::getCalledHooks()
		);

		// Calling getBasePath twice should return the same
		// result without triggering the hook again.
		HookRegistry::resetCalledHooks();
		self::assertEquals('/some/base', $this->request->getBasePath());
		self::assertEquals(
			array(),
			HookRegistry::getCalledHooks()
		);
	}

	/**
	 * @covers PKPRequest::getBasePath
	 */
	public function testGetEmptyBasePath() {
		$_SERVER = array(
			'SCRIPT_NAME' => '/main'
		);
		self::assertEquals('', $this->request->getBasePath());
	}

	/**
	 * @covers PKPRequest::getRequestPath
	 */
	public function testGetRequestPath() {
		$_SERVER = array(
			'SCRIPT_NAME' => 'some/script/name'
		);
		$this->setTestConfiguration('request1', 'classes/core/config'); // no restful URLs

		self::assertEquals('some/script/name', $this->request->getRequestPath());

		// The hook should have been triggered once.
		self::assertEquals(
			array(array('Request::getRequestPath' , array('some/script/name'))),
			HookRegistry::getCalledHooks()
		);

		// Calling getRequestPath() twice should return the same
		// result without triggering the hook again.
		HookRegistry::resetCalledHooks();
		self::assertEquals('some/script/name', $this->request->getRequestPath());
		self::assertEquals(
			array(),
			HookRegistry::getCalledHooks()
		);
	}

	/**
	 * @covers PKPRequest::getRequestPath
	 */
	public function testGetRequestPathRestful() {
		$_SERVER = array(
			'SCRIPT_NAME' => 'some/script/name'
		);
		$this->setTestConfiguration('request2', 'classes/core/config'); // restful URLs

		self::assertEquals('some/script', $this->request->getRequestPath());
	}


	/**
	 * @covers PKPRequest::getRequestPath
	 */
	public function testGetRequestPathWithPathinfo() {
		$_SERVER = array(
			'SCRIPT_NAME' => 'some/script/name',
			'PATH_INFO' => '/extra/path'
		);
		$this->setTestConfiguration('request1', 'classes/core/config'); // path info enabled

		self::assertEquals('some/script/name/extra/path', $this->request->getRequestPath());
	}

	/**
	 * @covers PKPRequest::getRequestPath
	 */
	public function testGetRequestPathWithoutPathinfo() {
		$_SERVER = array(
			'SCRIPT_NAME' => 'some/script/name',
			'PATH_INFO' => '/extra/path'
		);
		$this->setTestConfiguration('request2', 'classes/core/config'); // path info disabled

		self::assertEquals('some/script', $this->request->getRequestPath());
	}

	/**
	 * @covers PKPRequest::getServerHost
	 */
	public function testGetServerHostLocalhost() {
		// if none of the server variables is set then return the default
		$_SERVER = array();
		self::assertEquals('localhost', $this->request->getServerHost());
	}

	/**
	 * @covers PKPRequest::getServerHost
	 * @depends testGetServerHostLocalhost
	 */
	public function testGetServerHostWithHostname() {
		// if HOSTNAME is set then return it
		$_SERVER = array(
			'HOSTNAME' => 'hostname'
		);
		self::assertEquals('hostname', $this->request->getServerHost());
	}

	/**
	 * @covers PKPRequest::getServerHost
	 * @depends testGetServerHostWithHostname
	 */
	public function testGetServerHostWithHttpHost() {
		// if HTTP_HOST is set then return it
		$_SERVER = array(
			'HOSTNAME' => 'hostname',
			'HTTP_HOST' => 'http_host'
		);
		self::assertEquals('http_host', $this->request->getServerHost());
	}

	/**
	 * @covers PKPRequest::getServerHost
	 * @depends testGetServerHostWithHttpHost
	 */
	public function testGetServerHostWithHttpXForwardedHost() {
		// if HTTP_X_FORWARDED_HOST is set then return it
		$_SERVER = array(
			'HOSTNAME' => 'hostname',
			'HTTP_HOST' => 'http_host',
			'HTTP_X_FORWARDED_HOST' => 'x_host'
		);
		self::assertEquals('x_host', $this->request->getServerHost());
	}

	/**
	 * @covers PKPRequest::getProtocol
	 */
	public function testGetProtocolNoHttpsVariable() {
		$_SERVER = array();
		self::assertEquals('http', $this->request->getProtocol());
		// The hook should have been triggered once.
		self::assertEquals(
			array(array('Request::getProtocol' , array('http'))),
			HookRegistry::getCalledHooks()
		);

		// Calling getProtocol() twice should return the same
		// result without triggering the hook again.
		HookRegistry::resetCalledHooks();
		self::assertEquals('http', $this->request->getProtocol());
		self::assertEquals(
			array(),
			HookRegistry::getCalledHooks()
		);
	}

	/**
	 * @covers PKPRequest::getProtocol
	 */
	public function testGetProtocolHttpsVariableOff() {
		$_SERVER = array(
			'HTTPS' => 'OFF'
		);
		self::assertEquals('http', $this->request->getProtocol());
	}

	/**
	 * @covers PKPRequest::getProtocol
	 */
	public function testGetProtocolHttpsVariableOn() {
		$_SERVER = array(
			'HTTPS' => 'ON'
		);
		self::assertEquals('https', $this->request->getProtocol());
	}

	/**
	 * @covers PKPRequest::getUserVar
	 */
	public function testGetUserVar() {
		$_GET = array(
			'par1' => (get_magic_quotes_gpc() ? "\'val1\'" : "'val1'"),
			'par2' => ' val2'
		);
		$_POST = array(
			'par3' => 'val3 ',
			'par4' => 'val4'
		);
		self::assertEquals("'val1'", $this->request->getUserVar('par1'));
		self::assertEquals('val2', $this->request->getUserVar('par2'));
		self::assertEquals('val3', $this->request->getUserVar('par3'));
		self::assertEquals('val4', $this->request->getUserVar('par4'));
	}

	/**
	 * @covers PKPRequest::getUserVars
	 */
	public function testGetUserVars() {
		$_GET = array(
			'par1' => (get_magic_quotes_gpc() ? "\'val1\'" : "'val1'"),
			'par2' => ' val2'
		);
		$_POST = array(
			'par3' => 'val3 ',
			'par4' => 'val4'
		);
		$expectedResult = array(
			'par1' => "'val1'",
			'par2' => 'val2',
			'par3' => 'val3',
			'par4' => 'val4'
		);
		self::assertEquals($expectedResult, $this->request->getUserVars());
	}
}
?>

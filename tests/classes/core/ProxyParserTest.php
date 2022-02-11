<?php

/**
 * @file tests/classes/core/ProxyParserTest.php
 *
 * Copyright (c) 2013-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class ProxyParserTest
 * @ingroup tests_classes_core
 *
 * @see Core
 *
 * @brief Tests for the Proxy Parser class.
 */

import('lib.pkp.tests.PKPTestCase');

use PKP\Support\ProxyParser;

class ProxyParserTest extends PKPTestCase
{
    public function testParsingForHTTP()
    {
        $fqdn = 'http://username:password@192.168.1.1:8080';
        $proxy = new ProxyParser();
        $proxy->parseFQDN($fqdn);

        $this->assertEquals(
            'tcp://192.168.1.1:8080',
            $proxy->getProxy()
        );

        $this->assertEquals(
            base64_encode('username:password'),
            $proxy->getAuth()
        );
    }

    public function testParsingForHTTPS()
    {
        $fqdn = 'https://username:password@192.168.1.1:8080';
        $proxy = new ProxyParser();
        $proxy->parseFQDN($fqdn);

        $this->assertEquals(
            'tcp://192.168.1.1:8080',
            $proxy->getProxy()
        );

        $this->assertEquals(
            base64_encode('username:password'),
            $proxy->getAuth()
        );
    }

    public function testNonCommonProxyOption()
    {
        $fqdn = 'udp://username:password@176.0.0.1:8040';
        $proxy = new ProxyParser();
        $proxy->parseFQDN($fqdn);

        $this->assertEquals(
            'udp://username:password@176.0.0.1:8040',
            $proxy->getProxy()
        );
    }

    public function testEmptyProxyOption()
    {
        $proxy = new ProxyParser();
        $proxy->parseFQDN('');

        $this->assertEquals(
            '',
            $proxy->getProxy()
        );
    }
}

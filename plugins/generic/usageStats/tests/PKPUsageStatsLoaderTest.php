<?php

/**
 * @file plugins/generic/usageStats/tests/PKPUsageStatsLoaderTest.php
 *
 * Copyright (c) 2014-2018 Simon Fraser University
 * Copyright (c) 2000-2018 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class PKPUsageStatsLoaderTest
 * @ingroup plugins_generic_usagestats_tests
 * @see PKPUsageStatsLoader
 *
 * @brief Test class for the PKPUsageStatsLoader class
 */

import('lib.pkp.tests.PKPTestCase');
import('lib.pkp.plugins.generic.usageStats.PKPUsageStatsLoader');

class PKPUsageStatsLoaderTest extends PKPTestCase {

        //
        // Implementing protected template methods from PKPTestCase
        //
        /**
         * @see PKPTestCase::setUp()
         */
        protected function setUp() {
                parent::setUp();

                // Instantiate the class for testing.
		// disable the original constructor and mock the abstract getMetricType()
                $this->loader = $this->getMockBuilder('PKPUsageStatsLoader')
			->setMethods(array('__construct', 'getMetricType'))
			->setConstructorArgs(array())
			->disableOriginalConstructor()
			->getMock();
        }

	//
	// Unit tests
	//
	/**
	 * @covers PKPUsageStatsLoader::getCounterRobotListFile()
	 */
	public function testGetCounterRobotListFile() {

		// The getCounterRobotListFile() method will call $this->_plugin->getPluginPath()
		// Mock this cal
		$this->mockPlugin = $this->getMockBuilder('PKPUsageStatsPlugin')
			->setMethods(array('getPluginPath'))
			->getMock();
		$this->mockPlugin
			->expects($this->once())
			->method('getPluginPath')
			->will($this->returnValue('plugins/generic/usageStats'));
		$this->loader->_plugin = $this->mockPlugin;

		// assert bot list filename is present and readable
		$filename = $this->loader->getCounterRobotListFile();
		$foundFile = $filename && is_readable($filename);
		self::assertTrue($foundFile, 'getCounterRobotListFile() did not return a readable filename');
	}
}
?>

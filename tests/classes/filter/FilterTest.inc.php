<?php

/**
 * @file tests/classes/filter/FilterTest.inc.php
 *
 * Copyright (c) 2000-2010 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class FilterTest
 * @ingroup tests_classes_filter
 * @see Filter
 *
 * @brief Test class for Filter.
 */

import('lib.pkp.tests.PKPTestCase');
import('lib.pkp.classes.filter.Filter');

class FilterTest extends PKPTestCase {

	/**
	 * @covers Filter
	 */
	public function testExecute() {
		// Mock the abstract filter class
		$mockFilter = $this->getMock('Filter', array('supports', 'process'));
		$mockFilter->expects($this->any())
		           ->method('supports')
		           ->will($this->returnValue(true));
		$mockFilter->expects($this->any())
		           ->method('process')
		           ->will($this->returnCallback(array($this, 'processCallback')));

		// Use a standard object as input
		$testInput = new stdClass();
		$testInput->testField = 'some filter input';

		$testOutput = $mockFilter->execute($testInput);

		self::assertEquals($this->getTestOutput(), $testOutput);
		self::assertEquals($testInput, $mockFilter->getLastInput());
		self::assertEquals($this->getTestOutput(), $mockFilter->getLastOutput());
	}

	/**
	 * This method will be called to replace the abstract
	 * process() method of our test filter.
	 *
	 * @return stdClass
	 */
	public function processCallback($input) {
		return $this->getTestOutput();
	}

	/**
	 * Generate a test object.
	 *
	 * @return stdClass
	 */
	private function getTestOutput() {
		static $output;
		if (is_null($output)) {
			// Create a standard object as output
			$output = new stdClass();
			$output->testField = 'some filter result';
		}
		return $output;
	}
}
?>
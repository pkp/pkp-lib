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
import('lib.pkp.classes.filter.EmailFilterSetting');
import('lib.pkp.tests.classes.filter.TestClass1');
import('lib.pkp.tests.classes.filter.TestClass2');

class FilterTest extends PKPTestCase {

	/**
	 * @covers Filter
	 */
	public function testInstantiationAndExecute() {
		$mockFilter = $this->getFilterMock();

		// Test getters/setters that are not implicitly tested by other tests
		self::assertEquals('Mock_Filter_', substr($mockFilter->getDisplayName(), 0, 12));
		$mockFilter->setDisplayName('Some other display name');
		self::assertEquals('Some other display name', $mockFilter->getDisplayName());
		self::assertFalse($mockFilter->getIsTemplate());
		$mockFilter->setIsTemplate(1);
		self::assertTrue($mockFilter->getIsTemplate());
		self::assertEquals(0, $mockFilter->getParentFilterId());
		$mockFilter->setParentFilterId(1);
		self::assertEquals(1, $mockFilter->getParentFilterId());
		$mockFilter->setSeq(5);
		self::assertEquals(5, $mockFilter->getSeq());

		// Test settings
		self::assertFalse($mockFilter->hasSettings());
		$testSetting = new EmailFilterSetting('testEmail', 'Test Email', 'Test Email is required');
		$testSetting2 = new EmailFilterSetting('testEmail2', 'Test Email2', 'Test Email2 is required');
		$testSetting2->setIsLocalized(true);
		$mockFilter->addSetting($testSetting);
		$mockFilter->addSetting($testSetting2);
		self::assertEquals(array($testSetting, $testSetting2), $mockFilter->getSettings());
		self::assertTrue($mockFilter->hasSettings());
		self::assertEquals(array('testEmail'), $mockFilter->getSettingNames());
		self::assertEquals(array('testEmail2'), $mockFilter->getLocalizedSettingNames());

		// Test errors
		$mockFilter->addError('some error message');
		$mockFilter->addError('a second error message');
		$expectedErrors = array(
			'some error message',
			'a second error message'
		);
		self::assertEquals($expectedErrors, $mockFilter->getErrors());

		// Test supported Transformations
		$supportedTransformation = array(
			'class::lib.pkp.tests.classes.filter.TestClass1',
			'class::lib.pkp.tests.classes.filter.TestClass2'
		);
		self::assertEquals(array($supportedTransformation), $mockFilter->getSupportedTransformations());

		// Test setting supported transformations as TypeDescription
		// objects. This implicitly tests type validation.
		$typeDescriptionFactory =& TypeDescriptionFactory::getInstance();
		$inputType = $typeDescriptionFactory->instantiateTypeDescription($supportedTransformation[0]);
		$outputType = $typeDescriptionFactory->instantiateTypeDescription($supportedTransformation[1]);
		$mockFilter->setTransformationType($inputType, $outputType);
		self::assertEquals($supportedTransformation[0], $mockFilter->getInputType()->getTypeDescription());
		self::assertEquals($supportedTransformation[1], $mockFilter->getOutputType()->getTypeDescription());

		// Test identification of invalid transformation
		self::assertTrue($mockFilter->isValidTransformation($supportedTransformation[0], $supportedTransformation[1]));
		self::assertFalse($mockFilter->isValidTransformation('primitive::string', $supportedTransformation[1]));
		self::assertFalse($mockFilter->isValidTransformation($supportedTransformation[0], 'primitive::string'));

		// Test execution without runtime requirements
		$testInput = new TestClass1();
		$testInput->testField = 'some filter input';
		self::assertType('TestClass2', $testOutput = $mockFilter->execute($testInput));

		self::assertEquals($this->getTestOutput(), $testOutput);
		self::assertEquals($testInput, $mockFilter->getLastInput());
		self::assertEquals($this->getTestOutput(), $mockFilter->getLastOutput());

		// Test execution without runtime requirements
		// (We can safely use PHP 5.0.0 as a test here
		// because this is a PHPUnit requirement anyway.)
		$mockFilter = $this->getFilterMock();
		$mockFilter->setData('phpVersionMin', '5.0.0');
		$testOutput = $mockFilter->execute($testInput);
		$runtimeEnvironment = $mockFilter->getRuntimeEnvironment();
		self::assertEquals('5.0.0', $runtimeEnvironment->getPhpVersionMin());

		// Do the same again but this time set the runtime
		// environment via a RuntimeEnvironment object.
		$mockFilter = $this->getFilterMock();
		$mockFilter->setRuntimeEnvironment($runtimeEnvironment);
		$testOutput = $mockFilter->execute($testInput);
		$runtimeEnvironment = $mockFilter->getRuntimeEnvironment();
		self::assertEquals('5.0.0', $runtimeEnvironment->getPhpVersionMin());
		self::assertEquals('5.0.0', $mockFilter->getData('phpVersionMin'));

		// Test unsupported input
		$unsupportedInput = new TestClass2();
		self::assertNull($mockFilter->execute($unsupportedInput));
		self::assertNull($mockFilter->getLastInput());
		self::assertNull($mockFilter->getLastOutput());

		// Test unsupported output
		$mockFilter = $this->getFilterMock('class::lib.pkp.tests.classes.filter.TestClass1');
		self::assertNull($mockFilter->execute($testInput));
		self::assertEquals($testInput, $mockFilter->getLastInput());
		self::assertNull($mockFilter->getLastOutput());
	}

	/**
	 * @covers Filter
	 * @expectedException PHPUnit_Framework_Error
	 */
	public function testUnsupportedEnvironment() {
		$mockFilter = $this->getFilterMock();
		$mockFilter->setData('phpVersionMin', '20.0.0');
		$testOutput = $mockFilter->execute($testInput);
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
			// Create a test object as output
			$output = new TestClass2();
			$output->testField = 'some filter result';
		}
		return $output;
	}

	/**
	 * Create a mock filter for testing
	 * @return Filter
	 */
	private function getFilterMock($outputType = 'class::lib.pkp.tests.classes.filter.TestClass2') {
		// Mock the abstract filter class
		$mockFilter = $this->getMock('Filter', array('process', 'getSupportedTransformation'));

		// Set the supported transformation.
		$supportedTransformation = array('class::lib.pkp.tests.classes.filter.TestClass1', $outputType);
		$mockFilter->expects($this->any())
		           ->method('getSupportedTransformation')
		           ->will($this->returnValue($supportedTransformation));
		$mockFilter->setTransformationType($supportedTransformation[0], $supportedTransformation[1]);

		// Set the filter processor.
		$mockFilter->expects($this->any())
		           ->method('process')
		           ->will($this->returnCallback(array($this, 'processCallback')));

		return $mockFilter;
	}
}
?>
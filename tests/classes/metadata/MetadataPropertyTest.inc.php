<?php

/**
 * @file tests/metadata/MetadataPropertyTest.inc.php
 *
 * Copyright (c) 2003-2009 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class MetadataPropertyTest
 * @ingroup tests
 * @see MetadataProperty
 *
 * @brief Test class for MetadataProperty.
 */

import('tests.PKPTestCase');
import('metadata.MetadataProperty');

class MetadataPropertyTest extends PKPTestCase {
	/**
	 * @covers MetadataProperty::MetadataProperty
	 */
	public function testMetadataPropertyConstructor() {
		// test instantiation with non-default values
		$MetadataProperty = new MetadataProperty('testElement', METADATA_PROPERTY_TYPE_COMPOSITE, true, METADATA_PROPERTY_CARDINALITY_MANY);
		self::assertEquals('testElement', $MetadataProperty->getName());
		self::assertEquals(METADATA_PROPERTY_TYPE_COMPOSITE, $MetadataProperty->getType());
		self::assertTrue($MetadataProperty->getTranslated());
		self::assertEquals(METADATA_PROPERTY_CARDINALITY_MANY, $MetadataProperty->getCardinality());

		// test normal instantiation with defaults
		$MetadataProperty = new MetadataProperty('testElement');
		self::assertEquals('testElement', $MetadataProperty->getName());
		self::assertEquals(METADATA_PROPERTY_TYPE_STRING, $MetadataProperty->getType());
		self::assertFalse($MetadataProperty->getTranslated());
		self::assertEquals(METADATA_PROPERTY_CARDINALITY_ONE, $MetadataProperty->getCardinality());

		// return the MetadataProperty instance for further testing
		return $MetadataProperty;
	}

	/**
	 * @covers MetadataProperty::getName
	 * @covers MetadataProperty::setName
	 * @covers MetadataProperty::getTranslated
	 * @covers MetadataProperty::setTranslated
	 * @covers MetadataProperty::getCardinality
	 * @covers MetadataProperty::setCardinality
	 * @covers MetadataProperty::getType
	 * @covers MetadataProperty::setType
	 * @depends testMetadataPropertyConstructor
	 * @param $MetadataProperty MetadataProperty
	 */
	public function testMetadataPropertySetters(MetadataProperty $MetadataProperty) {
		$MetadataProperty = clone($MetadataProperty);
		$MetadataProperty->setName('anotherName');
		self::assertEquals('anotherName', $MetadataProperty->getName());
		$MetadataProperty->setTranslated(true);
		self::assertTrue($MetadataProperty->getTranslated());
		$MetadataProperty->setCardinality(METADATA_PROPERTY_CARDINALITY_MANY);
		self::assertEquals(METADATA_PROPERTY_CARDINALITY_MANY, $MetadataProperty->getCardinality());
		$MetadataProperty->setType(METADATA_PROPERTY_TYPE_DATE);
		self::assertEquals(METADATA_PROPERTY_TYPE_DATE, $MetadataProperty->getType());
	}

	/**
	 * Tests special error conditions while setting an unsupported type
	 * @covers MetadataProperty::setType
	 * @covers MetadataProperty::_getSupportedTypes
	 * @expectedException PHPUnit_Framework_Error
	 * @depends testMetadataPropertyConstructor
	 * @param $MetadataProperty MetadataProperty
	 */
	public function testSetUnsupportedType(MetadataProperty $MetadataProperty) {
		$MetadataProperty = clone($MetadataProperty);
		$MetadataProperty->setType(0x99999999);
	}

	/**
	 * Tests special error conditions while setting an unsupported cardinality
	 * @covers MetadataProperty::setCardinality
	 * @covers MetadataProperty::_getSupportedCardinalities
	 * @expectedException PHPUnit_Framework_Error
	 * @depends testMetadataPropertyConstructor
	 * @param $MetadataProperty MetadataProperty
	 */
	public function testSetUnsupportedCardinality(MetadataProperty $MetadataProperty) {
		$MetadataProperty = clone($MetadataProperty);
		$MetadataProperty->setCardinality(0x99999999);
	}

	/**
	 * @covers MetadataProperty::isValid
	 * @depends testMetadataPropertyConstructor
	 * @param $MetadataProperty MetadataProperty
	 */
	public function testValidateString(MetadataProperty $MetadataProperty) {
		$MetadataProperty = clone($MetadataProperty);
		self::assertTrue($MetadataProperty->isValid('any string'));
		self::assertFalse($MetadataProperty->isValid(null));
		self::assertFalse($MetadataProperty->isValid(5));
		self::assertFalse($MetadataProperty->isValid(array('string1', 'string2')));
	}

	/**
	 * @covers MetadataProperty::isValid
	 * @depends testMetadataPropertyConstructor
	 * @param $MetadataProperty MetadataProperty
	 */
	public function testValidateUri(MetadataProperty $MetadataProperty) {
		$MetadataProperty = clone($MetadataProperty);
		$MetadataProperty->setType(METADATA_PROPERTY_TYPE_URI);
		self::assertFalse($MetadataProperty->isValid('any string'));
		self::assertTrue($MetadataProperty->isValid('ftp://some.domain.org/path'));
		self::assertFalse($MetadataProperty->isValid(null));
		self::assertFalse($MetadataProperty->isValid(5));
		self::assertFalse($MetadataProperty->isValid(array('ftp://some.domain.org/path', 'http://some.domain.org/')));
	}

	/**
	 * @covers MetadataProperty::isValid
	 * @depends testMetadataPropertyConstructor
	 * @param $MetadataProperty MetadataProperty
	 */
	public function testValidateControlledVocabulary(MetadataProperty $MetadataProperty) {
		// TODO: Write tests when use case comes up.
	}

	/**
	 * @covers MetadataProperty::isValid
	 * @depends testMetadataPropertyConstructor
	 * @param $MetadataProperty MetadataProperty
	 */
	public function testValidateDate(MetadataProperty $MetadataProperty) {
		$MetadataProperty = clone($MetadataProperty);
		$MetadataProperty->setType(METADATA_PROPERTY_TYPE_DATE);
		self::assertTrue($MetadataProperty->isValid('2009-10-25'));
		self::assertTrue($MetadataProperty->isValid('2020-11'));
		self::assertTrue($MetadataProperty->isValid('1847'));
		self::assertFalse($MetadataProperty->isValid('XXXX'));
		self::assertFalse($MetadataProperty->isValid('2009-10-35'));
		self::assertFalse($MetadataProperty->isValid('2009-13-01'));
		self::assertFalse($MetadataProperty->isValid('2009-12-1'));
		self::assertFalse($MetadataProperty->isValid('2009-13'));
		self::assertFalse($MetadataProperty->isValid(5));
		self::assertFalse($MetadataProperty->isValid(array('2009-10-25', '2009-10-26')));
	}

	/**
	 * @covers MetadataProperty::isValid
	 * @depends testMetadataPropertyConstructor
	 * @param $MetadataProperty MetadataProperty
	 */
	public function testValidateInteger(MetadataProperty $MetadataProperty) {
		$MetadataProperty = clone($MetadataProperty);
		$MetadataProperty->setType(METADATA_PROPERTY_TYPE_INTEGER);
		self::assertTrue($MetadataProperty->isValid(5));
		self::assertFalse($MetadataProperty->isValid(null));
		self::assertFalse($MetadataProperty->isValid('a string'));
		self::assertFalse($MetadataProperty->isValid(array(4, 8)));
	}

	/**
	 * @covers MetadataProperty::isValid
	 * @depends testMetadataPropertyConstructor
	 * @param $MetadataProperty MetadataProperty
	 */
	public function testValidateComposite(MetadataProperty $MetadataProperty) {
		$MetadataProperty = clone($MetadataProperty);
		$MetadataProperty->setType(METADATA_PROPERTY_TYPE_COMPOSITE);

		import('metadata.MetadataSchema');
		$metadataSchema = new MetadataSchema();
		import('metadata.MetadataRecord');
		$metadataRecord = new MetadataRecord($metadataSchema);
		$anotherMetadataRecord = clone($metadataRecord);
		$stdObject = new stdClass();

		self::assertTrue($MetadataProperty->isValid($metadataRecord));
		self::assertFalse($MetadataProperty->isValid(null));
		self::assertFalse($MetadataProperty->isValid(5));
		self::assertFalse($MetadataProperty->isValid($stdObject));
		self::assertFalse($MetadataProperty->isValid(array($metadataRecord, $anotherMetadataRecord)));
	}
}
?>
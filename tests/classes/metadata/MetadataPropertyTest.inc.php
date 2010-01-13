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
		$metadataProperty = new MetadataProperty('testElement', array(0x001), METADATA_PROPERTY_TYPE_COMPOSITE, true, METADATA_PROPERTY_CARDINALITY_MANY);
		self::assertEquals('testElement', $metadataProperty->getName());
		self::assertEquals(array(0x001), $metadataProperty->getAssocTypes());
		self::assertEquals(METADATA_PROPERTY_TYPE_COMPOSITE, $metadataProperty->getType());
		self::assertTrue($metadataProperty->getTranslated());
		self::assertEquals(METADATA_PROPERTY_CARDINALITY_MANY, $metadataProperty->getCardinality());

		// test normal instantiation with defaults
		$metadataProperty = new MetadataProperty('testElement');
		self::assertEquals('testElement', $metadataProperty->getName());
		self::assertEquals(array(), $metadataProperty->getAssocTypes());
		self::assertEquals(METADATA_PROPERTY_TYPE_STRING, $metadataProperty->getType());
		self::assertFalse($metadataProperty->getTranslated());
		self::assertEquals(METADATA_PROPERTY_CARDINALITY_ONE, $metadataProperty->getCardinality());
	}

	/**
	 * Tests special error conditions while setting an unsupported type
	 * @covers MetadataProperty::setType
	 * @covers MetadataProperty::_getSupportedTypes
	 * @expectedException PHPUnit_Framework_Error
	 * @param $metadataProperty MetadataProperty
	 */
	public function testSetUnsupportedType() {
		$metadataProperty = new MetadataProperty('testElement', array(0x001), 0x99999999, true, METADATA_PROPERTY_CARDINALITY_MANY);		$metadataProperty = clone($metadataProperty);
	}

	/**
	 * Tests special error conditions while setting an unsupported cardinality
	 * @covers MetadataProperty::setCardinality
	 * @covers MetadataProperty::_getSupportedCardinalities
	 * @expectedException PHPUnit_Framework_Error
	 * @param $metadataProperty MetadataProperty
	 */
	public function testSetUnsupportedCardinality() {
		$metadataProperty = new MetadataProperty('testElement', array(0x001), METADATA_PROPERTY_TYPE_COMPOSITE, true, 0x99999999);
	}

	/**
	 * @covers MetadataProperty::isValid
	 * @param $metadataProperty MetadataProperty
	 */
	public function testValidateString() {
		$metadataProperty = new MetadataProperty('testElement');
		self::assertTrue($metadataProperty->isValid('any string'));
		self::assertFalse($metadataProperty->isValid(null));
		self::assertFalse($metadataProperty->isValid(5));
		self::assertFalse($metadataProperty->isValid(array('string1', 'string2')));
	}

	/**
	 * @covers MetadataProperty::isValid
	 * @param $metadataProperty MetadataProperty
	 */
	public function testValidateUri() {
		$metadataProperty = new MetadataProperty('testElement', array(), METADATA_PROPERTY_TYPE_URI);
		self::assertFalse($metadataProperty->isValid('any string'));
		self::assertTrue($metadataProperty->isValid('ftp://some.domain.org/path'));
		self::assertFalse($metadataProperty->isValid(null));
		self::assertFalse($metadataProperty->isValid(5));
		self::assertFalse($metadataProperty->isValid(array('ftp://some.domain.org/path', 'http://some.domain.org/')));
	}

	/**
	 * @covers MetadataProperty::isValid
	 * @param $metadataProperty MetadataProperty
	 */
	public function testValidateControlledVocabulary() {
		// TODO: Write tests when use case comes up.
	}

	/**
	 * @covers MetadataProperty::isValid
	 * @param $metadataProperty MetadataProperty
	 */
	public function testValidateDate() {
		$metadataProperty = new MetadataProperty('testElement', array(), METADATA_PROPERTY_TYPE_DATE);
		self::assertTrue($metadataProperty->isValid('2009-10-25'));
		self::assertTrue($metadataProperty->isValid('2020-11'));
		self::assertTrue($metadataProperty->isValid('1847'));
		self::assertFalse($metadataProperty->isValid('XXXX'));
		self::assertFalse($metadataProperty->isValid('2009-10-35'));
		self::assertFalse($metadataProperty->isValid('2009-13-01'));
		self::assertFalse($metadataProperty->isValid('2009-12-1'));
		self::assertFalse($metadataProperty->isValid('2009-13'));
		self::assertFalse($metadataProperty->isValid(5));
		self::assertFalse($metadataProperty->isValid(array('2009-10-25', '2009-10-26')));
	}

	/**
	 * @covers MetadataProperty::isValid
	 * @param $metadataProperty MetadataProperty
	 */
	public function testValidateInteger() {
		$metadataProperty = new MetadataProperty('testElement', array(), METADATA_PROPERTY_TYPE_INTEGER);
		self::assertTrue($metadataProperty->isValid(5));
		self::assertFalse($metadataProperty->isValid(null));
		self::assertFalse($metadataProperty->isValid('a string'));
		self::assertFalse($metadataProperty->isValid(array(4, 8)));
	}

	/**
	 * @covers MetadataProperty::isValid
	 * @param $metadataProperty MetadataProperty
	 */
	public function testValidateComposite() {
		$metadataProperty = new MetadataProperty('testElement', array(), METADATA_PROPERTY_TYPE_COMPOSITE);

		import('metadata.MetadataSchema');
		$metadataSchema = new MetadataSchema();
		import('metadata.MetadataRecord');
		$metadataRecord = new MetadataRecord($metadataSchema);
		$anotherMetadataRecord = clone($metadataRecord);
		$stdObject = new stdClass();

		self::assertTrue($metadataProperty->isValid($metadataRecord));
		self::assertFalse($metadataProperty->isValid(null));
		self::assertFalse($metadataProperty->isValid(5));
		self::assertFalse($metadataProperty->isValid($stdObject));
		self::assertFalse($metadataProperty->isValid(array($metadataRecord, $anotherMetadataRecord)));
	}
}
?>
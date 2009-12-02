<?php

/**
 * @file tests/metadata/MetadataElementTest.inc.php
 *
 * Copyright (c) 2003-2009 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class MetadataElementTest
 * @ingroup tests
 * @see MetadataElement
 *
 * @brief Test class for MetadataElement.
 */

import('tests.PKPTestCase');
import('metadata.MetadataElement');

class MetadataElementTest extends PKPTestCase {
	/**
	 * @covers MetadataElement::MetadataElement
	 */
	public function testMetadataElementConstructor() {
		// test instantiation with non-default values
		$metadataElement = new MetadataElement('testElement', METADATA_ELEMENT_TYPE_OBJECT, true, METADATA_ELEMENT_CARDINALITY_MANY, 'Author');
		self::assertEquals('testElement', $metadataElement->getName()); 
		self::assertEquals(METADATA_ELEMENT_TYPE_OBJECT, $metadataElement->getType()); 
		self::assertTrue($metadataElement->getTranslated()); 
		self::assertEquals(METADATA_ELEMENT_CARDINALITY_MANY, $metadataElement->getCardinality()); 
		self::assertEquals('Author', $metadataElement->getObjectType());
		
		// test normal instantiation with defaults
		$metadataElement = new MetadataElement('testElement');
		self::assertEquals('testElement', $metadataElement->getName()); 
		self::assertEquals(METADATA_ELEMENT_TYPE_STRING, $metadataElement->getType()); 
		self::assertFalse($metadataElement->getTranslated()); 
		self::assertEquals(METADATA_ELEMENT_CARDINALITY_ONE, $metadataElement->getCardinality()); 
		self::assertNull($metadataElement->getObjectType());

		// return the MetadataElement instance for further testing
		return $metadataElement;
	}
	
	/**
	 * @covers MetadataElement::getName
	 * @covers MetadataElement::setName
	 * @covers MetadataElement::getTranslated
	 * @covers MetadataElement::setTranslated
	 * @covers MetadataElement::getCardinality
	 * @covers MetadataElement::setCardinality
	 * @covers MetadataElement::getType
	 * @covers MetadataElement::setType
	 * @covers MetadataElement::getObjectType
	 * @covers MetadataElement::setObjectType
	 * @depends testMetadataElementConstructor
	 * @param $metadataElement MetadataElement
	 */
	public function testMetadataElementSetters(MetadataElement $metadataElement) {
		$metadataElement = clone($metadataElement);
		$metadataElement->setName('anotherName');
		self::assertEquals('anotherName', $metadataElement->getName());
		$metadataElement->setTranslated(true);
		self::assertTrue($metadataElement->getTranslated());
		$metadataElement->setCardinality(METADATA_ELEMENT_CARDINALITY_MANY);
		self::assertEquals(METADATA_ELEMENT_CARDINALITY_MANY, $metadataElement->getCardinality());
		// We've got some special rules for type and objectType:
		// 1) object type may only be set if type is METADATA_ELEMENT_TYPE_OBJECT or null,
		// otherwise it must be null.
		// 2) type may only be set to a value other than METADATA_ELEMENT_TYPE_OBJECT if
		// object type is null.
		// Let's test the allowed cases.
		
		// * set a type different from object
		$metadataElement->setType(METADATA_ELEMENT_TYPE_DATE);
		self::assertEquals(METADATA_ELEMENT_TYPE_DATE, $metadataElement->getType());
		self::assertNull($metadataElement->getObjectType());
		
		// * set type to object first, then set object-type
		$metadataElement->setType(METADATA_ELEMENT_TYPE_OBJECT);
		self::assertEquals(METADATA_ELEMENT_TYPE_OBJECT, $metadataElement->getType());
		self::assertNull($metadataElement->getObjectType());
		$metadataElement->setObjectType('Issue');
		self::assertEquals('Issue', $metadataElement->getObjectType());
		
		// * reset object-type before re-setting type, otherwise the object will
		//   be in an inconsistent state.
		$metadataElement->setObjectType(null);
		$metadataElement->setType(METADATA_ELEMENT_TYPE_DATE);
		
		// * now set the object-type first and then the type. This is only allowed when
		//   type is null, otherwise the obejct will be in an inconsistent state.
		// * it is allowed to reset type first when set to null
		$metadataElement->setType(null);
		$metadataElement->setObjectType('Citation');
		$metadataElement->setType(METADATA_ELEMENT_TYPE_OBJECT);
	}
	
	/**
	 * Tests special error conditions while setting object type
	 * @covers MetadataElement::setType
	 * @covers MetadataElement::setObjectType
	 * @depends testMetadataElementConstructor
	 * @expectedException PHPUnit_Framework_Error
	 * @param $metadataElement MetadataElement
	 */
	public function testSetObjectTypeWhileTypeIsNotObject(MetadataElement $metadataElement) {
		$metadataElement = clone($metadataElement);
		$metadataElement->setType(METADATA_ELEMENT_TYPE_INTEGER);
		$metadataElement->setObjectType('Citation'); // this will trigger an error
	}

	/**
	 * Tests special error conditions while setting type to "object"
	 * @covers MetadataElement::setType
	 * @covers MetadataElement::setObjectType
	 * @depends testMetadataElementConstructor
	 * @expectedException PHPUnit_Framework_Error
	 * @param $metadataElement MetadataElement
	 */
	public function testSetTypeToSomethingOtherThanAnObjectWhileObjectTypeIsSet(MetadataElement $metadataElement) {
		$metadataElement = clone($metadataElement);
		$metadataElement->setType(METADATA_ELEMENT_TYPE_OBJECT);
		$metadataElement->setObjectType('Citation'); // this will not trigger an error now, we've tested that already
		$metadataElement->setType(METADATA_ELEMENT_TYPE_Integer); // this will trigger an error
	}
	
	/**
	 * Tests special error conditions while instantiating en element of type "object"
	 * @covers MetadataElement::MetadataElement
	 * @expectedException PHPUnit_Framework_Error
	 * @param $metadataElement MetadataElement
	 */
	public function testInstantiateMetadataElementOfTypeObjectWithoutObjectType() {
		// the following is inconsistent and should throw an error
		$metadataElement = new MetadataElement('testElement', METADATA_ELEMENT_TYPE_OBJECT, true, METADATA_ELEMENT_CARDINALITY_MANY);
	}
	
	/**
	 * Tests special error conditions while instantiating an element of other type than "object"
	 * @covers MetadataElement::MetadataElement
	 * @expectedException PHPUnit_Framework_Error
	 */
	public function testInstantiateMetadataElementOfTypeOtherThanObjectWithObjectType() {
		// the following is inconsistent and should throw an error
		$metadataElement = new MetadataElement('testElement', METADATA_ELEMENT_TYPE_INTEGER, true, METADATA_ELEMENT_CARDINALITY_MANY, 'SomeClass');
	}
	
	/**
	 * Tests special error conditions while setting an unsupported type
	 * @covers MetadataElement::setType
	 * @expectedException PHPUnit_Framework_Error
	 * @depends testMetadataElementConstructor
	 * @param $metadataElement MetadataElement
	 */
	public function testSetUnsupportedType(MetadataElement $metadataElement) {
		$metadataElement = clone($metadataElement);
		$metadataElement->setType(0x99999999);
	}

	/**
	 * Tests special error conditions while setting an unsupported cardinality
	 * @covers MetadataElement::setCardinality
	 * @expectedException PHPUnit_Framework_Error
	 * @depends testMetadataElementConstructor
	 * @param $metadataElement MetadataElement
	 */
	public function testSetUnsupportedCardinality(MetadataElement $metadataElement) {
		$metadataElement = clone($metadataElement);
		$metadataElement->setCardinality(0x99999999);
	}

	/**
	 * @covers MetadataElement::validate
	 * @depends testMetadataElementConstructor
	 * @param $metadataElement MetadataElement
	 */
	public function testValidateString(MetadataElement $metadataElement) {
		$metadataElement = clone($metadataElement);
		self::assertTrue($metadataElement->validate('any string'));
		self::assertFalse($metadataElement->validate(null));
		self::assertFalse($metadataElement->validate(5));
		self::assertFalse($metadataElement->validate(array('string1', 'string2')));
		$metadataElement->setCardinality(METADATA_ELEMENT_CARDINALITY_MANY);
		self::assertTrue($metadataElement->validate(array('string1', 'string2')));
		self::assertFalse($metadataElement->validate(array('string1', 5)));
	}

	/**
	 * @covers MetadataElement::validate
	 * @depends testMetadataElementConstructor
	 * @param $metadataElement MetadataElement
	 */
	public function testValidateDate(MetadataElement $metadataElement) {
		$metadataElement = clone($metadataElement);
		$metadataElement->setType(METADATA_ELEMENT_TYPE_DATE);
		self::assertTrue($metadataElement->validate('2009-10-25'));
		self::assertTrue($metadataElement->validate('2020-11'));
		self::assertTrue($metadataElement->validate('1847'));
		self::assertFalse($metadataElement->validate('XXXX'));
		self::assertFalse($metadataElement->validate('2009-10-35'));
		self::assertFalse($metadataElement->validate('2009-13-01'));
		self::assertFalse($metadataElement->validate('2009-12-1'));
		self::assertFalse($metadataElement->validate('2009-13'));
		self::assertFalse($metadataElement->validate(5));
		self::assertFalse($metadataElement->validate(array('2009-10-25', '2009-10-26')));
		$metadataElement->setCardinality(METADATA_ELEMENT_CARDINALITY_MANY);
		self::assertTrue($metadataElement->validate(array('2009-10-25', '2009-10-26')));
		self::assertFalse($metadataElement->validate(array('2009-10-25', 5)));
	}
	
	/**
	 * @covers MetadataElement::validate
	 * @depends testMetadataElementConstructor
	 * @param $metadataElement MetadataElement
	 */
	public function testValidateInteger(MetadataElement $metadataElement) {
		$metadataElement = clone($metadataElement);
		$metadataElement->setType(METADATA_ELEMENT_TYPE_INTEGER);
		self::assertTrue($metadataElement->validate(5));
		self::assertFalse($metadataElement->validate(null));
		self::assertFalse($metadataElement->validate('a string'));
		self::assertFalse($metadataElement->validate(array(4, 8)));
		$metadataElement->setCardinality(METADATA_ELEMENT_CARDINALITY_MANY);
		self::assertTrue($metadataElement->validate(array(4, 8)));
		self::assertFalse($metadataElement->validate(array('string1', 5)));
	}
	
	/**
	 * @covers MetadataElement::validate
	 * @depends testMetadataElementConstructor
	 * @param $metadataElement MetadataElement
	 */
	public function testValidateObject(MetadataElement $metadataElement) {
		$metadataElement = clone($metadataElement);
		$metadataElement->setType(METADATA_ELEMENT_TYPE_OBJECT);
		$metadataElement->setObjectType('DataObject');
		
		$dataObject = new DataObject();
		$anotherDataObject = new DataObject();
		$stdObject = new stdClass();
		
		self::assertTrue($metadataElement->validate($dataObject));
		self::assertFalse($metadataElement->validate(null));
		self::assertFalse($metadataElement->validate(5));
		self::assertFalse($metadataElement->validate($stdObject));
		self::assertFalse($metadataElement->validate(array($dataObject, $anotherDataObject)));
		$metadataElement->setCardinality(METADATA_ELEMENT_CARDINALITY_MANY);
		self::assertTrue($metadataElement->validate(array($dataObject, $anotherDataObject)));
		self::assertFalse($metadataElement->validate(array($dataObject, $stdObject)));
	}
}
?>
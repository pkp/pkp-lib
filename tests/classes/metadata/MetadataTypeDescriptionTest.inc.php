<?php

/**
 * @file tests/classes/metadata/MetadataTypeDescriptionTest.inc.php
 *
 * Copyright (c) 2000-2010 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class MetadataTypeDescriptionTest
 * @ingroup tests_classes_metadata
 * @see MetadataTypeDescription
 *
 * @brief Test class for MetadataTypeDescription.
 */

import('lib.pkp.tests.PKPTestCase');
import('lib.pkp.classes.metadata.MetadataTypeDescription');
import('lib.pkp.classes.metadata.MetadataDescription');
import('lib.pkp.classes.metadata.nlm.NlmCitationSchema');
import('lib.pkp.classes.metadata.nlm.NlmNameSchema');

class MetadataTypeDescriptionTest extends PKPTestCase {
	/**
	 * @covers MetadataTypeDescription
	 */
	public function testInstantiateAndCheck() {
		// Test with specific assoc type
		$typeDescription = new MetadataTypeDescription('lib.pkp.classes.metadata.nlm.NlmCitationSchema(CITATION)');

		$rightSchema = new NlmCitationSchema();
		$wrongSchema = new NlmNameSchema();
		$compatibleMetadataDescription = new MetadataDescription($rightSchema, ASSOC_TYPE_CITATION);
		$wrongMetadataDescription1 = new MetadataDescription($wrongSchema, ASSOC_TYPE_CITATION);
		$wrongMetadataDescription2 = new MetadataDescription($rightSchema, ASSOC_TYPE_AUTHOR);
		self::assertTrue($typeDescription->isCompatible($compatibleMetadataDescription));
		self::assertFalse($typeDescription->isCompatible($wrongMetadataDescription1));
		self::assertFalse($typeDescription->isCompatible($wrongMetadataDescription2));

		// Test with wildcard assoc type
		$typeDescription = new MetadataTypeDescription('lib.pkp.classes.metadata.nlm.NlmCitationSchema(*)');
		self::assertTrue($typeDescription->isCompatible($compatibleMetadataDescription));
		self::assertFalse($typeDescription->isCompatible($wrongMetadataDescription1));
		self::assertTrue($typeDescription->isCompatible($wrongMetadataDescription2));
	}

	/**
	 * @covers MetadataTypeDescription
	 * @expectedException PHPUnit_Framework_Error
	 */
	function testInstantiateWithInvalidTypeDescriptor1() {
		// Type name is not fully qualified.
		$typeDescription = new MetadataTypeDescription('NlmCitationSchema(CITATION)');
	}

	/**
	 * @covers MetadataTypeDescription
	 * @expectedException PHPUnit_Framework_Error
	 */
	function testInstantiateWithInvalidTypeDescriptor2() {
		// Missing assoc type.
		$typeDescription = new MetadataTypeDescription('lib.pkp.classes.metadata.nlm.NlmCitationSchema');
	}

	/**
	 * @covers MetadataTypeDescription
	 * @expectedException PHPUnit_Framework_Error
	 */
	function testInstantiateWithInvalidTypeDescriptor3() {
		// Wrong assoc type.
		$typeDescription = new MetadataTypeDescription('lib.pkp.classes.metadata.nlm.NlmCitationSchema(UNKNOWN)');
	}

	/**
	 * @covers MetadataTypeDescription
	 */
	public function testGetSampleObject() {
		// Test scalar types
		$typeDescription = new MetadataTypeDescription('lib.pkp.classes.metadata.nlm.NlmCitationSchema(CITATION)');
		$sampleObject = $typeDescription->getSampleObject();
		self::assertType('MetadataDescription', $sampleObject);
		self::assertType('NlmCitationSchema', $sampleObject->getMetadataSchema());
		self::assertEquals(ASSOC_TYPE_CITATION, $sampleObject->getAssocType());
	}
}
?>
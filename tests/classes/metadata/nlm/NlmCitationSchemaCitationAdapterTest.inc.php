<?php

/**
 * @file tests/classes/metadata/NlmCitationSchemaCitationAdapterTest.inc.php
 *
 * Copyright (c) 2000-2010 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class NlmCitationSchemaCitationAdapterTest
 * @ingroup tests_classes_metadata
 * @see NlmCitationSchemaCitationAdapter
 *
 * @brief Test class for NlmCitationSchemaCitationAdapter.
 */

import('lib.pkp.tests.PKPTestCase');
import('lib.pkp.classes.citation.Citation');
import('lib.pkp.plugins.metadata.nlm30.filter.NlmCitationSchemaCitationAdapter');
import('lib.pkp.tests.classes.metadata.TestSchema');

class NlmCitationSchemaCitationAdapterTest extends PKPTestCase {
	/**
	 * @covers NlmCitationSchemaCitationAdapter
	 */
	public function testNlmCitationSchemaCitationAdapter() {
		// Test constructor
		$adapter = new NlmCitationSchemaCitationAdapter();
		self::assertEquals(ASSOC_TYPE_CITATION, $adapter->getAssocType());
		self::assertType('NlmCitationSchema', $adapter->getMetadataSchema());
		$expectedTransformations = array(
			array(
				'metadata::lib.pkp.plugins.metadata.nlm30.schema.NlmCitationSchema(CITATION)',
				'class::lib.pkp.classes.citation.Citation'
			),
			array(
				'class::lib.pkp.classes.citation.Citation',
				'metadata::lib.pkp.plugins.metadata.nlm30.schema.NlmCitationSchema(CITATION)'
			)
		);
		self::assertEquals($expectedTransformations, $adapter->getSupportedTransformations());

		// Instantiate a test description
		$authorDescription = new MetadataDescription('lib.pkp.plugins.metadata.nlm30.schema.NlmNameSchema', ASSOC_TYPE_AUTHOR);
		$authorDescription->addStatement('surname', $surname = 'some surname');
		$citationDescription = new MetadataDescription('lib.pkp.plugins.metadata.nlm30.schema.NlmCitationSchema', ASSOC_TYPE_CITATION);
		$citationDescription->addStatement('article-title', $articleTitle = 'article title');
		$citationDescription->addStatement('person-group[@person-group-type="author"]', $authorDescription);

		// Instantiate test citation
		$citation = new Citation();

		// Test metadata injection (no replace)
		$resultCitation =& $adapter->injectMetadataIntoDataObject($citationDescription, $citation, false);
		$expectedResult = array(
			'rawCitation' => '',
			'nlm30:person-group[@person-group-type="author"]' => array(
				array('surname' => 'some surname')
			),
			'nlm30:article-title' => array(
				'en_US' => 'article title'
			)
		);
		self::assertEquals($expectedResult, $resultCitation->getAllData());

		// Instantiate and inject a second test description
		$authorDescription = new MetadataDescription('lib.pkp.plugins.metadata.nlm30.schema.NlmNameSchema', ASSOC_TYPE_AUTHOR);
		$authorDescription->addStatement('surname', $anotherSurname = 'another surname');
		$secondDescription = new MetadataDescription('lib.pkp.plugins.metadata.nlm30.schema.NlmCitationSchema', ASSOC_TYPE_CITATION);
		$secondDescription->addStatement('person-group[@person-group-type="author"]', $authorDescription);
		$secondDescription->addStatement('source', $source = 'some source');
		$resultCitation =& $adapter->injectMetadataIntoDataObject($secondDescription, $citation, false);
		$expectedResult = array(
			'rawCitation' => '',
			'nlm30:person-group[@person-group-type="author"]' => array(
				array('surname' => 'another surname')
			),
			'nlm30:article-title' => array(
				'en_US' => 'article title'
			),
			'nlm30:source' => array(
				'en_US' => 'some source'
			)
		);
		self::assertEquals($expectedResult, $resultCitation->getAllData());

		// Test meta-data injection (replace)
		$resultCitation =& $adapter->injectMetadataIntoDataObject($secondDescription, $citation, true);
		$expectedResult = array(
			'rawCitation' => '',
			'nlm30:person-group[@person-group-type="author"]' => array(
				array('surname' => 'another surname')
			),
			'nlm30:source' => array(
				'en_US' => 'some source'
			)
		);
		self::assertEquals($expectedResult, $resultCitation->getAllData());

		// Test meta-data extraction
		$extractedDescription =& $adapter->extractMetadataFromDataObject($citation);
		self::assertEquals($secondDescription, $extractedDescription);
	}
}
?>
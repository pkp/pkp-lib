<?php

/**
 * @file tests/classes/metadata/Nlm30CitationSchemaCitationAdapterTest.inc.php
 *
 * Copyright (c) 2000-2010 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class Nlm30CitationSchemaCitationAdapterTest
 * @ingroup tests_classes_metadata
 * @see Nlm30CitationSchemaCitationAdapter
 *
 * @brief Test class for Nlm30CitationSchemaCitationAdapter.
 */

import('lib.pkp.tests.PKPTestCase');
import('lib.pkp.classes.citation.Citation');
import('lib.pkp.plugins.metadata.nlm30.filter.Nlm30CitationSchemaCitationAdapter');
import('lib.pkp.tests.classes.metadata.TestSchema');

class Nlm30CitationSchemaCitationAdapterTest extends PKPTestCase {
	/**
	 * @covers Nlm30CitationSchemaCitationAdapter
	 */
	public function testNlm30CitationSchemaCitationAdapter() {
		// Test constructor
		$adapter = new Nlm30CitationSchemaCitationAdapter();
		self::assertEquals(ASSOC_TYPE_CITATION, $adapter->getAssocType());
		self::assertType('Nlm30CitationSchema', $adapter->getMetadataSchema());
		$expectedTransformations = array(
			array(
				'metadata::lib.pkp.plugins.metadata.nlm30.schema.Nlm30CitationSchema(CITATION)',
				'class::lib.pkp.classes.citation.Citation'
			),
			array(
				'class::lib.pkp.classes.citation.Citation',
				'metadata::lib.pkp.plugins.metadata.nlm30.schema.Nlm30CitationSchema(CITATION)'
			)
		);
		self::assertEquals($expectedTransformations, $adapter->getSupportedTransformations());

		// Instantiate a test description
		$authorDescription = new MetadataDescription('lib.pkp.plugins.metadata.nlm30.schema.Nlm30NameSchema', ASSOC_TYPE_AUTHOR);
		$authorDescription->addStatement('surname', $surname = 'some surname');
		$citationDescription = new MetadataDescription('lib.pkp.plugins.metadata.nlm30.schema.Nlm30CitationSchema', ASSOC_TYPE_CITATION);
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
		$authorDescription = new MetadataDescription('lib.pkp.plugins.metadata.nlm30.schema.Nlm30NameSchema', ASSOC_TYPE_AUTHOR);
		$authorDescription->addStatement('surname', $anotherSurname = 'another surname');
		$secondDescription = new MetadataDescription('lib.pkp.plugins.metadata.nlm30.schema.Nlm30CitationSchema', ASSOC_TYPE_CITATION);
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
<?php

/**
 * @file tests/config/IsbndbIsbnNlmCitationSchemaFilterTest.inc.php
 *
 * Copyright (c) 2000-2010 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class IsbndbIsbnNlmCitationSchemaFilterTest
 * @ingroup tests_classes_citation_lookup_isbndb
 *
 * @brief Tests for IsbndbNlmCitationSchemaIsbnFilter
 */

// $Id$

import('citation.lookup.isbndb.IsbndbIsbnNlmCitationSchemaFilter');
import('tests.classes.citation.lookup.isbndb.IsbndbNlmCitationSchemaFilterTest');

class IsbndbIsbnNlmCitationSchemaFilterTest extends IsbndbNlmCitationSchemaFilterTest {

	/**
	 * @covers IsbndbIsbnNlmCitationSchemaFilter
	 * @covers IsbndbNlmCitationSchemaFilter
	 */
	public function testExecute() {
		// Create the expected result data
		// 1) Author
		$nlmNameSchema = new NlmNameSchema();
		$expectedAuthorDescription = new MetadataDescription($nlmNameSchema, ASSOC_TYPE_AUTHOR);
		$expectedAuthorData = array(
			'given-names' => array('John'),
			'surname' => 'Willinsky'
		);
		$expectedAuthorDescription->setStatements($expectedAuthorData);
		// 2) Citation
		$nlmCitationSchema = new NlmCitationSchema();
		$expectedCitationDescription = new MetadataDescription($nlmCitationSchema, ASSOC_TYPE_CITATION);
		$expectedCitationData = array(
			'source' => array(
				'en_US' => 'After literacy: essays'
			),
			'date' => '2001',
			'person-group[@person-group-type="author"]' => array($expectedAuthorDescription),
			'publisher-loc' => 'New York',
			'publisher-name' => 'P. Lang',
			'isbn' => '9780820452425',
			'[@publication-type]' => 'book'
		);
		$expectedCitationDescription->setStatements($expectedCitationData);

		// Execute the filter
		$filter = new IsbndbIsbnNlmCitationSchemaFilter(ISBNDB_TEST_APIKEY);
		$isbn = '9780820452425';
		self::assertEquals($expectedCitationDescription, $filter->execute($isbn));
	}
}
?>

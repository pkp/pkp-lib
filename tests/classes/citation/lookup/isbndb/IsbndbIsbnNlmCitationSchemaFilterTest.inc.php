<?php

/**
 * @file tests/classes/citation/lookup/isbndb/IsbndbIsbnNlmCitationSchemaFilterTest.inc.php
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

import('lib.pkp.classes.citation.lookup.isbndb.IsbndbIsbnNlmCitationSchemaFilter');
import('lib.pkp.tests.classes.citation.lookup.isbndb.IsbndbNlmCitationSchemaFilterTest');

class IsbndbIsbnNlmCitationSchemaFilterTest extends IsbndbNlmCitationSchemaFilterTest {

	/**
	 * @covers IsbndbIsbnNlmCitationSchemaFilter
	 * @covers IsbndbNlmCitationSchemaFilter
	 */
	public function testExecute() {
		// Test data
		$isbnLookupTest = array(
			'testInput' => '9780820452425', // ISBN
			'testOutput' => array(
				'source' => array(
					'en_US' => 'After literacy: essays'
				),
				'date' => '2001',
				'person-group[@person-group-type="author"]' => array(
					0 => array('given-names' => array('John'), 'surname' => 'Willinsky')
				),
				'publisher-loc' => 'New York',
				'publisher-name' => 'P. Lang',
				'isbn' => '9780820452425',
				'[@publication-type]' => 'book'
			)
		);

		// Build the test array
		$citationFilterTests = array(
			$isbnLookupTest
		);

		// Test the filter
		$filter = new IsbndbIsbnNlmCitationSchemaFilter(self::ISBNDB_TEST_APIKEY);
		$this->assertNlmCitationSchemaFilter($citationFilterTests, $filter);
	}
}
?>

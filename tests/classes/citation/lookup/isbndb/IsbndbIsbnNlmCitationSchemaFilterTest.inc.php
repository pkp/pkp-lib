<?php

/**
 * @file tests/classes/citation/lookup/isbndb/IsbndbIsbnNlm30CitationSchemaFilterTest.inc.php
 *
 * Copyright (c) 2000-2010 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class IsbndbIsbnNlm30CitationSchemaFilterTest
 * @ingroup tests_classes_citation_lookup_isbndb
 *
 * @brief Tests for IsbndbNlm30CitationSchemaIsbnFilter
 */

import('lib.pkp.classes.citation.lookup.isbndb.IsbndbIsbnNlm30CitationSchemaFilter');
import('lib.pkp.tests.classes.citation.lookup.isbndb.IsbndbNlm30CitationSchemaFilterTest');

class IsbndbIsbnNlm30CitationSchemaFilterTest extends IsbndbNlm30CitationSchemaFilterTest {

	/**
	 * @covers IsbndbIsbnNlm30CitationSchemaFilter
	 * @covers IsbndbNlm30CitationSchemaFilter
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
		$filter = new IsbndbIsbnNlm30CitationSchemaFilter(self::ISBNDB_TEST_APIKEY);
		$this->assertNlm30CitationSchemaFilter($citationFilterTests, $filter);
	}
}
?>

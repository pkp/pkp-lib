<?php

/**
 * @file tests/classes/citation/lookup/isbndb/IsbndbNlm30CitationSchemaIsbnFilterTest.inc.php
 *
 * Copyright (c) 2000-2010 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class IsbndbNlm30CitationSchemaIsbnFilterTest
 * @ingroup tests_classes_citation_lookup_isbndb
 *
 * @brief Tests for IsbndbNlm30CitationSchemaIsbnFilter
 */

// $Id$

import('lib.pkp.classes.citation.lookup.isbndb.IsbndbNlm30CitationSchemaIsbnFilter');
import('lib.pkp.tests.classes.citation.lookup.isbndb.IsbndbNlm30CitationSchemaFilterTest');

class IsbndbNlm30CitationSchemaIsbnFilterTest extends IsbndbNlm30CitationSchemaFilterTest {

	/**
	 * @covers IsbndbNlm30CitationSchemaIsbnFilter
	 * @covers IsbndbNlm30CitationSchemaFilter
	 */
	public function testExecute() {
		// Test data
		$isbnSearchTest = array(
			'testInput' => array(
				'person-group[@person-group-type="author"]' => array(
					0 => array('given-names' => array('John'), 'surname' => 'Willinsky')
				),
				'source' => array(
					'en_US' => 'After literacy'
				)
			),
			'testOutput' => '9780820452425' // ISBN
		);

		// Build the test array
		$citationFilterTests = array(
			$isbnSearchTest
		);

		// Test the filter
		$filter = new IsbndbNlm30CitationSchemaIsbnFilter(self::ISBNDB_TEST_APIKEY);
		$this->assertNlm30CitationSchemaFilter($citationFilterTests, $filter);
	}
}
?>

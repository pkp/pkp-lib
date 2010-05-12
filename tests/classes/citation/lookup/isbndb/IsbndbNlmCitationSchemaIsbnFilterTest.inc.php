<?php

/**
 * @file tests/classes/citation/lookup/isbndb/IsbndbNlmCitationSchemaIsbnFilterTest.inc.php
 *
 * Copyright (c) 2000-2010 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class IsbndbNlmCitationSchemaIsbnFilterTest
 * @ingroup tests_classes_citation_lookup_isbndb
 *
 * @brief Tests for IsbndbNlmCitationSchemaIsbnFilter
 */

// $Id$

import('lib.pkp.classes.citation.lookup.isbndb.IsbndbNlmCitationSchemaIsbnFilter');
import('lib.pkp.tests.classes.citation.lookup.isbndb.IsbndbNlmCitationSchemaFilterTest');

class IsbndbNlmCitationSchemaIsbnFilterTest extends IsbndbNlmCitationSchemaFilterTest {

	/**
	 * @covers IsbndbNlmCitationSchemaIsbnFilter
	 * @covers IsbndbNlmCitationSchemaFilter
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
		$filter = new IsbndbNlmCitationSchemaIsbnFilter(self::ISBNDB_TEST_APIKEY);
		$this->assertNlmCitationSchemaFilter($citationFilterTests, $filter);
	}
}
?>

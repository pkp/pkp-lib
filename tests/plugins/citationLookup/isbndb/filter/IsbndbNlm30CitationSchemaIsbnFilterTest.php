<?php

/**
 * @file tests/plugins/citationLookup/isbndb/filter/IsbndbNlm30CitationSchemaIsbnFilterTest.php
 *
 * Copyright (c) 2000-2013 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class IsbndbNlm30CitationSchemaIsbnFilterTest
 * @ingroup tests_plugins_citationLookup_isbndb_filter
 *
 * @brief Tests for IsbndbNlm30CitationSchemaIsbnFilter
 */


require_mock_env('env2');

import('lib.pkp.plugins.citationLookup.isbndb.filter.IsbndbNlm30CitationSchemaIsbnFilter');
import('lib.pkp.tests.plugins.citationLookup.isbndb.filter.IsbndbNlm30CitationSchemaFilterTest');

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
		$filter = new IsbndbNlm30CitationSchemaIsbnFilter(PersistableFilter::tempGroup(
				'metadata::lib.pkp.plugins.metadata.nlm30.schema.Nlm30CitationSchema(CITATION)',
				'primitive::string'));
		$filter->setData('apiKey', self::ISBNDB_TEST_APIKEY);
		$this->assertNlm30CitationSchemaFilter($citationFilterTests, $filter);
	}
}
?>

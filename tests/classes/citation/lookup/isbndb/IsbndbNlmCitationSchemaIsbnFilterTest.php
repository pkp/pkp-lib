<?php

/**
 * @file tests/config/IsbndbNlmCitationSchemaIsbnFilterTest.inc.php
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

import('tests.PKPTestCase');
import('citation.lookup.isbndb.IsbndbNlmCitationSchemaIsbnFilter');
import('metadata.nlm.NlmNameSchema');
import('metadata.nlm.NlmCitationSchema');
import('metadata.MetadataDescription');

define('ISBNDB_TEST_APIKEY', '4B5GQSQ4');

class IsbndbNlmCitationSchemaIsbnFilterTest extends PKPTestCase {

	/**
	 * @covers IsbndbNlmCitationSchemaIsbnFilter
	 */
	public function testExecuteFilter() {
		$nameSchema = new NlmNameSchema();
		$nameDescription = new MetadataDescription($nameSchema, ASSOC_TYPE_AUTHOR);
		$nameDescription->addStatement('surname', $surname = 'Willinsky');
		$nameDescription->addStatement('given-names', $givenName = 'John');

		$citationSchema = new NlmCitationSchema();
		$citationDescription = new MetadataDescription($citationSchema, ASSOC_TYPE_CITATION);
		$citationDescription->addStatement('person-group[@person-group-type="author"]', $nameDescription);
		$citationDescription->addStatement('source', $source = 'After literacy');

		$filter = new IsbndbNlmCitationSchemaIsbnFilter(ISBNDB_TEST_APIKEY);
		self::assertEquals('9780820452425', $filter->execute($citationDescription));
	}
}
?>

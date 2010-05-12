<?php

/**
 * @file tests/classes/citation/lookup/crossref/CrossrefNlmCitationSchemaFilterTest.inc.php
 *
 * Copyright (c) 2000-2010 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class CrossrefNlmCitationSchemaFilterTest
 * @ingroup tests_classes_citation_lookup_crossref
 * @see CrossrefNlmCitationSchemaFilter
 *
 * @brief Tests for the CrossrefNlmCitationSchemaFilter class.
 */

// $Id$

import('lib.pkp.classes.citation.lookup.crossref.CrossrefNlmCitationSchemaFilter');
import('lib.pkp.tests.classes.citation.NlmCitationSchemaFilterTestCase');

class CrossrefNlmCitationSchemaFilterTest extends NlmCitationSchemaFilterTestCase {
	const
		ACCESS_EMAIL = 'pkp.contact@gmail.com';

	/**
	 * Test CrossRef lookup with DOI
	 * @covers CrossrefNlmCitationSchemaFilter
	 */
	public function testExecuteWithDoi() {
		// Test article DOI lookup
		$articleTest = array(
			'testInput' => array(
				'pub-id[@pub-id-type="doi"]' => '10.1186/1471-2105-5-147'
			),
			'testOutput' => array (
				'source' => 'BMC Bioinformatics',
				'issue' => '1',
				'volume' => '5',
				'date' => '2004',
				'fpage' => 147,
				'uri' => 'http://www.biomedcentral.com/1471-2105/5/147',
				'person-group[@person-group-type="author"]' => array (
					array ('given-names' => array('Hao'), 'surname' => 'Chen'),
					array ('given-names' => array('Burt', 'M'), 'surname' => 'Sharp')
				),
				'pub-id[@pub-id-type="doi"]' => '10.1186/1471-2105-5-147',
				'issn[@pub-type="ppub"]' => '14712105',
				'[@publication-type]' => 'journal'
			)
		);

		// Conference Proceeding
		$conferenceTest = array (
			'testInput' => array(
				'pub-id[@pub-id-type="doi"]' => '10.1145/311625.311726'
			),
			'testOutput' => array(
				'conf-name' => 'ACM SIGGRAPH 99 Conference abstracts and applications on   - SIGGRAPH \'99',
				'isbn' => '1581131038',
				'publisher-name' => 'ACM Press',
				'publisher-loc' => 'New York, New York, USA',
				'article-title' => 'The SIGGRAFFITI wall',
				'date' => '1999',
				'fpage' => 94,
				'uri' => 'http://portal.acm.org/citation.cfm?doid=311625.311726',
				'person-group[@person-group-type="author"]' => array (
					array ('given-names' => array('Richard'), 'surname' => 'Dunn-Roberts')
				),
				'pub-id[@pub-id-type="doi"]' => '10.1145/311625.311726',
				'[@publication-type]' => 'conf-proc'
			)
		);

		// Book
		$bookTest = array (
			'testInput' => array(
				'pub-id[@pub-id-type="doi"]' => '10.1093/ref:odnb/31418'
			),
			'testOutput' => array(
				'pub-id[@pub-id-type="doi"]' => '10.1093/ref:odnb/31418',
				'source' => 'The Oxford Dictionary of National Biography',
				'date' => '2004-09-23',
				'publisher-name' => 'Oxford University Press',
				'publisher-loc' => 'Oxford',
				'person-group[@person-group-type="author"]' =>
				array (
					array ('given-names' => array('H', 'C', 'G'), 'surname' => 'Matthew'),
					array ('given-names' => array('B'), 'surname' => 'Harrison')
				),
				'[@publication-type]' => 'book'
			)
		);

		// Build the test citations array
		$citationFilterTests = array(
			$articleTest,
			$conferenceTest,
			$bookTest
		);

		// Execute the tests
		$filter = new CrossrefNlmCitationSchemaFilter(self::ACCESS_EMAIL);
		$this->assertNlmCitationSchemaFilter($citationFilterTests, $filter);
	}

	/**
	 * Test CrossRef lookup without DOI
	 * @covers CrossrefNlmCitationSchemaFilter
	 */
	public function testExecuteWithOpenUrlSearch() {
		// Build the test citations array
		$citationFilterTests = array(
			array(
				'testInput' => array(
					'person-group[@person-group-type="author"]' => array (
						array ('given-names' => array('Hao'), 'surname' => 'Chen'),
					),
					'source' => 'BMC Bioinformatics',
					'issue' => '1',
					'volume' => '5',
					'fpage' => 147,
					'[@publication-type]' => 'journal'
				),
				'testOutput' => array (
					'source' => 'BMC Bioinformatics',
					'issue' => '1',
					'volume' => '5',
					'date' => '2004',
					'fpage' => 147,
					'uri' => 'http://www.biomedcentral.com/1471-2105/5/147',
					'issn[@pub-type="ppub"]' => '14712105',
					'person-group[@person-group-type="author"]' => array (
						array ('given-names' => array('Hao'), 'surname' => 'Chen'),
						array ('given-names' => array('Burt', 'M'), 'surname' => 'Sharp')
					),
					'pub-id[@pub-id-type="doi"]' => '10.1186/1471-2105-5-147',
					'[@publication-type]' => 'journal'
				)
			)
		);

		// Execute the test
		$filter = new CrossrefNlmCitationSchemaFilter(self::ACCESS_EMAIL);
		$this->assertNlmCitationSchemaFilter($citationFilterTests, $filter);
	}
}
?>

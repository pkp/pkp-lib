<?php
/**
 * @defgroup tests_plugins_citationLookup_pubmed_filter PubMed Filter Test Suite
 */

/**
 * @file tests/plugins/citationLookup/pubmed/filter/PubmedNlm30CitationSchemaFilterTest.php
 *
 * Copyright (c) 2000-2013 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class PubmedNlm30CitationSchemaFilterTest
 * @ingroup tests_plugins_citationLookup_pubmed_filter
 * @see PubmedNlm30CitationSchemaFilter
 *
 * @brief Tests for the PubmedNlm30CitationSchemaFilter class.
 */


import('lib.pkp.plugins.citationLookup.pubmed.filter.PubmedNlm30CitationSchemaFilter');
import('lib.pkp.tests.plugins.metadata.nlm30.filter.Nlm30CitationSchemaFilterTestCase');

class PubmedNlm30CitationSchemaFilterTest extends Nlm30CitationSchemaFilterTestCase {
	/**
	 * Test Pubmed lookup with PmID
	 * @covers PubmedNlm30CitationSchemaFilter
	 */
	public function testExecuteWithPmid() {
		// Test article Pubmed lookup
		$citationFilterTests = array(
			array(
				'testInput' => array(
					'pub-id[@pub-id-type="pmid"]' => '12140307'
				),
				'testOutput' => $this->getTestOutput()
			)
		);

		// Execute the tests
		$filter = new PubmedNlm30CitationSchemaFilter(PersistableFilter::tempGroup(
				'metadata::lib.pkp.plugins.metadata.nlm30.schema.Nlm30CitationSchema(CITATION)',
				'metadata::lib.pkp.plugins.metadata.nlm30.schema.Nlm30CitationSchema(CITATION)'));
		$this->assertNlm30CitationSchemaFilter($citationFilterTests, $filter);
	}

	/**
	 * Test Pubmed lookup without PmID
	 * @covers PubmedNlm30CitationSchemaFilter
	 */
	public function testExecuteWithSearch() {
		// Build the test citations array
		$citationFilterTests = array(
			// strict search
			array(
				'testInput' => array(
					'person-group[@person-group-type="author"]' => array (
						array ('given-names' => array('Scott', 'D'), 'surname' => 'Halpern'),
						array ('given-names' => array('Peter', 'A'), 'surname' => 'Ubel'),
						array ('given-names' => array('Arthur', 'L'), 'surname' => 'Caplan')
					),
					'article-title' => 'Solid-organ transplantation in HIV-infected patients.',
					'source' => 'N Engl J Med',
					'volume' => '347',
					'issue' => '4'
				),
				'testOutput' => $this->getTestOutput()
			),
			// author search
			array(
				'testInput' => array(
					'person-group[@person-group-type="author"]' => array (
						array ('given-names' => array('Scott', 'D'), 'surname' => 'Halpern'),
						array ('given-names' => array('Peter', 'A'), 'surname' => 'Ubel'),
						array ('given-names' => array('Arthur', 'L'), 'surname' => 'Caplan')
					)
				),
				'testOutput' => $this->getTestOutput()
			)
		);

		// Execute the test
		$filter = new PubmedNlm30CitationSchemaFilter(PersistableFilter::tempGroup(
				'metadata::lib.pkp.plugins.metadata.nlm30.schema.Nlm30CitationSchema(CITATION)',
				'metadata::lib.pkp.plugins.metadata.nlm30.schema.Nlm30CitationSchema(CITATION)'));
		$this->assertNlm30CitationSchemaFilter($citationFilterTests, $filter);
	}

	private function &getTestOutput() {
		$testOutput = array(
			'pub-id[@pub-id-type="pmid"]' => '12140307',
			'article-title' => 'Solid-organ transplantation in HIV-infected patients.',
			'source' => 'N Engl J Med',
			'volume' => '347',
			'issue' => '4',
			'person-group[@person-group-type="author"]' => array (
				array ('given-names' => array('Scott', 'D'), 'surname' => 'Halpern'),
				array ('given-names' => array('Peter', 'A'), 'surname' => 'Ubel'),
				array ('given-names' => array('Arthur', 'L'), 'surname' => 'Caplan')
			),
			'fpage' => 284,
			'lpage' => 287,
			'date' => '2002-07-25',
			'[@publication-type]' => 'journal',
			'pub-id[@pub-id-type="doi"]' => '10.1056/NEJMsb020632',
			'uri' => 'http://www.scholaruniverse.com/ncbi-linkout?id=12140307'
		);
		return $testOutput;
	}
}
?>

<?php
/**
 * @defgroup tests_plugins_citationLookup_worldcat_filter WorldCat Filter Test Suite
 */

/**
 * @file tests/plugins/citationLookup/worldcat/filter/WorldcatNlm30CitationSchemaFilterTest.php
 *
 * Copyright (c) 2000-2013 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class WorldcatNlm30CitationSchemaFilterTest
 * @ingroup tests_plugins_citationLookup_worldcat_filter
 * @see WorldcatNlm30CitationSchemaFilter
 *
 * @brief Tests for the WorldcatNlm30CitationSchemaFilter class.
 *
 * NB: This test requires a WordCat API key to function properly!
 */


import('lib.pkp.plugins.citationLookup.worldcat.filter.WorldcatNlm30CitationSchemaFilter');
import('lib.pkp.tests.plugins.metadata.nlm30.filter.Nlm30CitationSchemaFilterTestCase');

class WorldcatNlm30CitationSchemaFilterTest extends Nlm30CitationSchemaFilterTestCase {
	/**
	 * Test CrossRef lookup with DOI
	 * @covers WorldcatNlm30CitationSchemaFilter
	 */
	public function testExecuteWithDoi() {
		if (is_null(Config::getVar('debug', 'worldcat_apikey'))) {
			$this->markTestSkipped('The WorldCat API key has not been configured.');
		}

		// Test book lookup
		$testWithApiKey = array(
			'testInput' => array(
				'person-group[@person-group-type="author"]' => array (
					array ('given-names' => array('Paula'), 'surname' => 'Fernandes Lopes'),
				),
				'source' => 'A ética platônica: modelo de ética da boa vida'
			),
			'testOutput' => array (
				'person-group[@person-group-type="author"]' => array (
					array ('given-names' => array('Paula', 'Fernandes'), 'surname' => 'Lopes')
				),
				'source' => 'A ética platônica : modelo de ética da boa vida',
				'isbn' => '851503154X',
				'publisher-loc' => 'São Paulo',
				'publisher-name' => 'Ed. Loyola',
				'date' => '2005',
				'[@publication-type]' => NLM30_PUBLICATION_TYPE_BOOK
			)
		);

		// Build the test citations array
		$citationFilterTests = array($testWithApiKey);

		// Execute the tests with API key
		self::assertEquals(80, strlen(Config::getVar('debug', 'worldcat_apikey')), 'It seems that the WorldCat API key has not been correctly configured.');
		$filter = new WorldcatNlm30CitationSchemaFilter(PersistableFilter::tempGroup(
				'metadata::lib.pkp.plugins.metadata.nlm30.schema.Nlm30CitationSchema(CITATION)',
				'metadata::lib.pkp.plugins.metadata.nlm30.schema.Nlm30CitationSchema(CITATION)'));
		$filter->setData('apiKey', Config::getVar('debug', 'worldcat_apikey'));
		$this->assertNlm30CitationSchemaFilter($citationFilterTests, $filter);

		// Try again without API key
		$testWithoutApiKey = array(
			'testInput' => array(
				'person-group[@person-group-type="author"]' => array (
					array ('given-names' => array('Paula'), 'surname' => 'Fernandes Lopes'),
				),
				'source' => 'A ética platônica: modelo de ética da boa vida'
			),
			'testOutput' => array (
				'person-group[@person-group-type="author"]' => array (
					array ('given-names' => array('Paula', 'Fernandes'), 'surname' => 'Lopes')
				),
				'source' => 'A ética platônica : modelo de ética da boa vida',
				'isbn' => '9788515031542',
				'date' => '2005',
				'publisher-name' => 'Ed. Loyola',
				'publisher-loc' => 'São Paulo',
				'[@publication-type]' => NLM30_PUBLICATION_TYPE_BOOK
			)
		);
		$citationFilterTests = array($testWithoutApiKey);
		$filter = new WorldcatNlm30CitationSchemaFilter(PersistableFilter::tempGroup(
				'metadata::lib.pkp.plugins.metadata.nlm30.schema.Nlm30CitationSchema(CITATION)',
				'metadata::lib.pkp.plugins.metadata.nlm30.schema.Nlm30CitationSchema(CITATION)'));
		$this->assertNlm30CitationSchemaFilter($citationFilterTests, $filter);
	}
}
?>

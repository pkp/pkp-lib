<?php

/**
 * @file tests/classes/citation/parser/paracite/ParaciteRawCitationNlmCitationSchemaFilterTest.inc.php
 *
 * Copyright (c) 2000-2010 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class ParaciteRawCitationNlmCitationSchemaFilterTest
 * @ingroup tests_classes_citation_parser_paracite
 * @see ParaciteRawCitationNlmCitationSchemaFilter
 *
 * @brief Tests for the ParaciteRawCitationNlmCitationSchemaFilter class.
 */

import('lib.pkp.tests.classes.citation.parser.NlmCitationSchemaParserFilterTestCase');
import('lib.pkp.classes.citation.parser.paracite.ParaciteRawCitationNlmCitationSchemaFilter');

class ParaciteRawCitationNlmCitationSchemaFilterTest extends NlmCitationSchemaParserFilterTestCase {
	/**
	 * @covers ParaciteRawCitationNlmCitationSchemaFilter
	 */
	public function testExecute() {
		$testCitations = array(
			CITATION_PARSER_PARACITE_STANDARD => array(
				array(
					'testInput' => 'Sheril, R. D. (1956). The terrifying future: Contemplating color television. San Diego: Halstead.',
					'testOutput' => array(
						'[@publication-type]' => 'book',
						'chapter-title' => 'The terrifying future: Contemplating color television',
						'person-group[@person-group-type="author"]' => array(
							array('given-names' => array('R'), 'surname' => 'Sheril')
						),
						'date' => '1956',
						'publisher-name' => 'Halstead',
						'publisher-loc' => 'San Diego'
					)
				),
				array(
					'testInput' => 'Crackton, P. (1987). The Loonie: God\'s long-awaited gift to colourful pocket change? Canadian Change, 64(7), 34-37.',
					'testOutput' => array(
						'[@publication-type]' => 'journal',
						'article-title' => 'The Loonie: God\'s long-awaited gift to colourful pocket change? Canadian Change, 64(7), 34-37',
						'person-group[@person-group-type="author"]' => array(
							array('given-names' => array('P'), 'surname' => 'Crackton')
						),
						'date' => '1987'
					)
				)
			),
			CITATION_PARSER_PARACITE_CITEBASE => array(
				array(
					'testInput' => 'Sheril, R. D. (1956). The terrifying future: Contemplating color television. San Diego: Halstead.',
					'testOutput' => array(
						'[@publication-type]' => 'journal',
						'person-group[@person-group-type="author"]' => array(
							array('given-names' => array('R', 'D'), 'surname' => 'Sheril')
						),
						'date' => '1956',
						'comment' => 'Sheril, R. D. . The terrifying future:Contemplating color television. San Diego:Halstead'
					)
				),
				array(
					'testInput' => 'Crackton, P. (1987). The Loonie: God\'s long-awaited gift to colourful pocket change? Canadian Change, 64(7), 34-37.',
					'testOutput' => array(
						'[@publication-type]' => 'journal',
						'source' => 'Canadian Chan',
						'person-group[@person-group-type="author"]' => array(
							array('given-names' => array('P'), 'surname' => 'Crackton')
						),
						'fpage' => 34,
						'date' => '1987',
						'comment' => 'Crackton, P. (1987). The Loonie:God\'s long-awaited gift to colourful pocket change? Canadian Change, 64(7), 34-37',
						'issue' => '7',
						'volume' => '64'
					)
				)
			),
			CITATION_PARSER_PARACITE_JIAO => array(
				array(
					'testInput' => 'Sheril, R. D. (1956). The terrifying future: Contemplating color television. San Diego: Halstead.',
					'testOutput' => array(
						'[@publication-type]' => 'journal',
						'date' => '1956'
					)
				),
				array(
					'testInput' => 'Crackton, P. (1987). The Loonie: God\'s long-awaited gift to colourful pocket change? Canadian Change, 64(7), 34–37.',
					'testOutput' => array(
						'[@publication-type]' => 'journal',
						'source' => 'Canadian Chan',
						'fpage' => 34,
						'date' => '1987',
						'issue' => '7',
						'volume' => '64'
					)
				)
			)
		);

		foreach (ParaciteRawCitationNlmCitationSchemaFilter::getSupportedCitationModules() as $citationModule) {
			assert(isset($testCitations[$citationModule]));

			$filter = new ParaciteRawCitationNlmCitationSchemaFilter($citationModule);
			$this->assertNlmCitationSchemaFilter($testCitations[$citationModule], $filter);
			unset($filter);
		}
	}

	/**
	 * @covers ParaciteRawCitationNlmCitationSchemaFilter
	 */
	public function testAllCitationsWithThisParser() {
		foreach (ParaciteRawCitationNlmCitationSchemaFilter::getSupportedCitationModules() as $citationModule) {
			$filter = new ParaciteRawCitationNlmCitationSchemaFilter($citationModule);
			parent::testAllCitationsWithThisParser($filter);
			unset($filter);
		}
	}
}
?>

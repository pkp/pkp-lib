<?php

/**
 * @file tests/classes/citation/parser/freecite/FreeciteRawCitationNlmCitationSchemaFilterTest.inc.php
 *
 * Copyright (c) 2000-2012 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class FreeciteRawCitationNlmCitationSchemaFilterTest
 * @ingroup tests_classes_citation_parser_freecite
 * @see FreeciteRawCitationNlmCitationSchemaFilter
 *
 * @brief Tests for the FreeciteRawCitationNlmCitationSchemaFilter class.
 */

// $Id$

import('tests.classes.citation.parser.NlmCitationSchemaParserFilterTestCase');
import('citation.parser.freecite.FreeciteRawCitationNlmCitationSchemaFilter');

class FreeciteRawCitationNlmCitationSchemaFilterTest extends NlmCitationSchemaParserFilterTestCase {
	/**
	 * @covers FreeciteRawCitationNlmCitationSchemaFilter
	 */
	public function testExecute() {
		$testCitations = array(
			array(
				'testInput' => 'Sheril, R. D. (1956). The terrifying future: Contemplating color television. San Diego: Halstead.',
				'testOutput' => array(
					'article-title' => 'The terrifying future: Contemplating color television',
					'person-group[@person-group-type="author"]' => array(
						array('given-names' => array('R', 'D'), 'surname' => 'Sheril')
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
					'article-title' => 'The Loonie: God\'s long-awaited gift to colourful pocket change',
					'person-group[@person-group-type="author"]' => array(
						array('given-names' => array('P'), 'surname' => 'Crackton')
					),
					'fpage' => 34,
					'lpage' => 37,
					'date' => '1987',
					'source' => 'Canadian Change',
					'issue' => '7',
					'volume' => '64'
				)
			),
			array(
				'testInput' => 'Iyer, Naresh Sundaram. "A Family of Dominance Filters for Multiple Criteria Decision Making: Choosing the Right Filter for a Decision Situation." Ph.D. diss., Ohio State University, 2001.',
				'testOutput' => array(
					'[@publication-type]' => 'thesis',
					'source' => 'A Family of Dominance Filters for Multiple Criteria Decision Making: Choosing the Right Filter for a Decision Situation',
					'person-group[@person-group-type="author"]' => array(
						array('given-names' => array('Iyer', 'Naresh'), 'surname' => 'Sundaram')
					),
					'date' => '2001',
					'comment' => 'Ph.D',
					'publisher-name' => 'Ohio State University'
				)
			)
		);

		$filter = new FreeciteRawCitationNlmCitationSchemaFilter();
		$this->assertNlmCitationSchemaFilter($testCitations, $filter);
	}

	/**
	 * @covers FreeciteRawCitationNlmCitationSchemaFilter
	 */
	public function testExecuteWithWebServiceError() {
		$this->assertWebServiceError('FreeciteRawCitationNlmCitationSchemaFilter');
	}

	/**
	 * @see NlmCitationSchemaParserFilterTestCase::testAllCitationsWithThisParser()
	 */
	public function testAllCitationsWithThisParser() {
		$filter = new FreeciteRawCitationNlmCitationSchemaFilter();
		parent::testAllCitationsWithThisParser($filter);
	}
}
?>

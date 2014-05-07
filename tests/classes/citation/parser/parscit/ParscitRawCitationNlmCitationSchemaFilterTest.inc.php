<?php

/**
 * @file tests/classes/citation/parser/parscit/ParscitRawCitationNlmCitationSchemaFilterTest.inc.php
 *
 * Copyright (c) 2000-2012 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class ParscitRawCitationNlmCitationSchemaFilterTest
 * @ingroup tests_classes_citation_parser_parscit
 * @see ParscitRawCitationNlmCitationSchemaFilter
 *
 * @brief Tests for the ParscitRawCitationNlmCitationSchemaFilter class.
 */

// $Id$

import('tests.classes.citation.parser.NlmCitationSchemaParserFilterTestCase');
import('citation.parser.parscit.ParscitRawCitationNlmCitationSchemaFilter');

class ParscitRawCitationNlmCitationSchemaFilterTest extends NlmCitationSchemaParserFilterTestCase {
	/**
	 * @covers ParscitRawCitationNlmCitationSchemaFilter
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
				'testInput' => 'Crackton, P. (1987). The Loonie: God\'s long-awaited gift to colourful pocket change? Canadian Change, 64(7), 34–37.',
				'testOutput' => array(
					'article-title' => 'The Loonie: God&#39;s long-awaited gift to colourful pocket change',
					'person-group[@person-group-type="author"]' => array(
						array('given-names' => array('P'), 'surname' => 'Crackton')
					),
					'fpage' => 34,
					'lpage' => 37,
					'date' => '1987',
					'source' => 'Canadian Change',
					'volume' => '64'
				)
			)
		);

		$filter = new ParscitRawCitationNlmCitationSchemaFilter();
		$this->assertNlmCitationSchemaFilter($testCitations, $filter);
	}

	/**
	 * @covers ParaciteRawCitationNlmCitationSchemaFilter
	 */
	public function testAllCitationsWithThisParser() {
		$filter = new ParscitRawCitationNlmCitationSchemaFilter();
		parent::testAllCitationsWithThisParser($filter);
	}

	/**
	 * @covers ParscitRawCitationNlmCitationSchemaFilter
	 */
	public function testExecuteWithWebServiceError() {
		$this->assertWebServiceError('ParscitRawCitationNlmCitationSchemaFilter');
	}
}
?>

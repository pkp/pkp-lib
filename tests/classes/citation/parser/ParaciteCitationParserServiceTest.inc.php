<?php

/**
 * @file tests/config/ParaciteCitationParserServiceTest.inc.php
 *
 * Copyright (c) 2003-2009 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class ParaciteCitationParserServiceTest
 * @ingroup tests
 * @see ParaciteCitationParserService
 *
 * @brief Tests for the ParaciteCitationParserService class.
 */

// $Id$

import('tests.classes.citation.parser.CitationParserServiceTestCase');
import('citation.parser.ParaciteCitationParserService');

class ParaciteCitationParserServiceTest extends CitationParserServiceTestCase {
	public function setUp() {
		$this->setCitationServiceName('ParaciteCitationParserService');
	}
	
	/**
	 * @covers ParaciteCitationParserService::parseInternal
	 */
	public function testParseInternal() {
		$testCitations = array(
			CITATION_PARSER_PARACITE_STANDARD => array(
				array(
					'testCitation' => 'Sheril, R. D. (1956). The terrifying future: Contemplating color television. San Diego: Halstead.',
					'expectedResult' => array(
						'genre' => METADATA_GENRE_BOOK,
						'articleTitle' => 'The terrifying future: Contemplating color television',
						'authors' => array(
							array('initials' => 'R.', 'lastName' => 'Sheril')
						),
						'issuedDate' => '1956',
						'publisher' => 'Halstead',
						'place' => 'San Diego'
					)
				),
				array(
					'testCitation' => 'Crackton, P. (1987). The Loonie: God\'s long-awaited gift to colourful pocket change? Canadian Change, 64(7), 34-37.',
					'expectedResult' => array(
						'genre' => METADATA_GENRE_UNKNOWN,
						'articleTitle' => 'The Loonie: God\'s long-awaited gift to colourful pocket change? Canadian Change, 64(7), 34-37',
						'authors' => array(
							array('initials' => 'P.', 'lastName' => 'Crackton')
						),
						'issuedDate' => '1987'
					)
				)
			),
			CITATION_PARSER_PARACITE_CITEBASE => array(
				array(
					'testCitation' => 'Sheril, R. D. (1956). The terrifying future: Contemplating color television. San Diego: Halstead.',
					'expectedResult' => array(
						'genre' => METADATA_GENRE_UNKNOWN,
						'authors' => array(
							array('initials' => 'R.D.', 'lastName' => 'Sheril')
						),
						'issuedDate' => '1956',
						'comments' => array(
							'Sheril, R. D. . The terrifying future:Contemplating color television. San Diego:Halstead'
						)
					)
				),
				array(
					'testCitation' => 'Crackton, P. (1987). The Loonie: God\'s long-awaited gift to colourful pocket change? Canadian Change, 64(7), 34-37.',
					'expectedResult' => array(
						'genre' => METADATA_GENRE_UNKNOWN,
						'articleTitle' => 'Canadian Chan',
						'authors' => array(
							array('initials' => 'P.', 'lastName' => 'Crackton')
						),
						'firstPage' => '34',
						'issuedDate' => '1987',
						'comments' => array(
							'Crackton, P. (1987). The Loonie:God\'s long-awaited gift to colourful pocket change? Canadian Change, 64(7), 34-37'
						),
						'issue' => '7',
						'volume' => '64'
					)
				)
			),
			CITATION_PARSER_PARACITE_JIAO => array(
				array(
					'testCitation' => 'Sheril, R. D. (1956). The terrifying future: Contemplating color television. San Diego: Halstead.',
					'expectedResult' => array(
						'genre' => METADATA_GENRE_UNKNOWN,
						'issuedDate' => '1956'
					)
				),
				array(
					'testCitation' => 'Crackton, P. (1987). The Loonie: God\'s long-awaited gift to colourful pocket change? Canadian Change, 64(7), 34–37.',
					'expectedResult' => array(
						'genre' => METADATA_GENRE_UNKNOWN,
						'articleTitle' => 'Canadian Chan',
						'firstPage' => '34',
						'issuedDate' => '1987',
						'issue' => '7',
						'volume' => '64'
					)
				)
			)
		);
		
		foreach (ParaciteCitationParserService::getSupportedCitationModules() as $citationModule) {
			assert(isset($testCitations[$citationModule]));
			
			$parameters = array('citationModule' => $citationModule);
			$this->assertCitationService($testCitations[$citationModule], $parameters);
		}
	}

	public function testAllCitationsWithThisParser() {
		foreach (ParaciteCitationParserService::getSupportedCitationModules() as $citationModule) {
			parent::testAllCitationsWithThisParser(array('citationModule' => $citationModule));
		}
	}
}
?>

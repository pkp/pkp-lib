<?php

/**
 * @file tests/config/ParscitCitationParserServiceTest.inc.php
 *
 * Copyright (c) 2000-2010 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class ParscitCitationParserServiceTest
 * @ingroup tests_classes_citation_parser
 * @see ParscitCitationParserService
 *
 * @brief Tests for the ParscitCitationParserService class.
 */

// $Id$

import('tests.classes.citation.parser.CitationParserServiceTestCase');
import('citation.parser.ParscitCitationParserService');

class ParscitCitationParserServiceTest extends CitationParserServiceTestCase {
	public function setUp() {
		$this->setCitationServiceName('ParscitCitationParserService');
	}

	/**
	 * @covers ParscitCitationParserService::parseInternal
	 */
	public function testParseInternal() {
		$testCitations = array(
			array(
				'testCitation' => 'Sheril, R. D. (1956). The terrifying future: Contemplating color television. San Diego: Halstead.',
				'expectedResult' => array(
					'genre' => METADATA_GENRE_UNKNOWN,
					'articleTitle' => 'The terrifying future: Contemplating color television',
					'authors' => array(
						array('initials' => 'R D', 'lastName' => 'Sheril')
					),
					'issuedDate' => '1956',
					'publisher' => 'Halstead',
					'place' => 'San Diego'
				)
			),
			array(
				'testCitation' => 'Crackton, P. (1987). The Loonie: God\'s long-awaited gift to colourful pocket change? Canadian Change, 64(7), 34–37.',
				'expectedResult' => array(
					'genre' => METADATA_GENRE_UNKNOWN,
					'articleTitle' => 'The Loonie: God&#39;s long-awaited gift to colourful pocket change',
					'authors' => array(
						array('initials' => 'P', 'lastName' => 'Crackton')
					),
					'firstPage' => '34',
					'lastPage' => '37',
					'issuedDate' => '1987',
					'journalTitle' => 'Canadian Change',
					'volume' => '64'
				)
			)
		);

		$this->assertCitationService($testCitations);
	}

	/**
	 * @covers ParscitCitationParserService::parseInternal
	 */
	public function testParseInternalWebServiceError() {
		$this->assertWebServiceError();
	}
}
?>

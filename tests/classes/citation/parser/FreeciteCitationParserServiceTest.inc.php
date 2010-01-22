<?php

/**
 * @file tests/config/FreeciteCitationParserServiceTest.inc.php
 *
 * Copyright (c) 2000-2010 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class FreeciteCitationParserServiceTest
 * @ingroup tests_classes_citation_parser
 * @see FreeciteCitationParserService
 *
 * @brief Tests for the FreeciteCitationParserService class.
 */

// $Id$

import('tests.classes.citation.parser.CitationParserServiceTestCase');
import('citation.parser.FreeciteCitationParserService');

class FreeciteCitationParserServiceTest extends CitationParserServiceTestCase {
	public function setUp() {
		$this->setCitationServiceName('FreeciteCitationParserService');
	}

	/**
	 * @covers FreeciteCitationParserService::parseInternal
	 */
	public function testParseInternal() {
		// TODO: add further citations here when we improve the parser or
		//       encounter specific problems.
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
				'testCitation' => 'Crackton, P. (1987). The Loonie: God\'s long-awaited gift to colourful pocket change? Canadian Change, 64(7), 34-37.',
				'expectedResult' => array(
					'genre' => METADATA_GENRE_JOURNALARTICLE,
					'articleTitle' => 'The Loonie: God\'s long-awaited gift to colourful pocket change',
					'authors' => array(
						array('initials' => 'P', 'lastName' => 'Crackton')
					),
					'firstPage' => '34',
					'lastPage' => '37',
					'issuedDate' => '1987',
					'journalTitle' => 'Canadian Change',
					'issue' => '7',
					'volume' => '64'
				)
			),
			array(
				'testCitation' => 'Iyer, Naresh Sundaram. "A Family of Dominance Filters for Multiple Criteria Decision Making: Choosing the Right Filter for a Decision Situation." Ph.D. diss., Ohio State University, 2001.',
				'expectedResult' => array(
					'genre' => METADATA_GENRE_DISSERTATION,
					'bookTitle' => 'A Family of Dominance Filters for Multiple Criteria Decision Making: Choosing the Right Filter for a Decision Situation',
					'authors' => array(
						array('firstName' => 'Iyer', 'middleName' => 'Naresh', 'lastName' => 'Sundaram')
					),
					'issuedDate' => '2001',
					'comments' => array('Ph.D'),
					'publisher' => 'Ohio State University'
				)
			)
		);

		$this->assertCitationService($testCitations);
	}

	/**
	 * @covers FreeciteCitationParserService::parseInternal
	 */
	public function testParseInternalWebServiceError() {
		$this->assertWebServiceError();
	}
}
?>

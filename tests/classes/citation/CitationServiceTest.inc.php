<?php

/**
 * @file tests/config/CitationParserServiceTest.inc.php
 *
 * Copyright (c) 2000-2010 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class CitationParserServiceTest
 * @ingroup tests_classes_citation
 * @see CitationParserService
 *
 * @brief Tests for the RegexCitationParserService class.
 */

// $Id$

import('tests.PKPTestCase');
import('citation.CitationService');

class CitationServiceTest extends PKPTestCase {
	private $_citationService;

	public function setUp() {
		$this->_citationService = new CitationService();
	}

//	/**
//	 * @todo Implement testCallWebService().
//	 */
//	public function testCallWebService() {
//	}
//
//	/**
//	 * @todo Implement testTransformWebServiceResults().
//	 */
//	public function testTransformWebServiceResults() {
//	}

	/**
	 * @covers CitationService::titleCase
	 */
	public function testTitleCase() {
		$originalTitle = 'AND This IS A TEST title';
		self::assertEquals('And This is a Test Title', $this->_citationService->titleCase($originalTitle));
	}

	/**
	 * @covers CitationService::trimPunctuation
	 */
	public function testTrimPunctuation() {
		$trimmedChars = array(
			' ', ',', '.', ';', ':', '!', '?',
			'(', ')', '[', ']', '\\', '/'
		);

		foreach($trimmedChars as $trimmedChar) {
			self::assertEquals('trim.med',
					$this->_citationService->trimPunctuation($trimmedChar.'trim.med'.$trimmedChar));
		}
	}

	/**
	 * @covers CitationService::parseAuthorString
	 */
	public function testParseAuthorString() {
		$authorArgumentArray = array(
			array('MULLER', false, false),                         // lastname
			array('His Excellency B.C. Van de Haan', true, false), // initials prefix lastname + title
			array('Mrs. P.-B. von Redfield-Brownfox', true, false),// initials prefix double-lastname + title
			array('Professor K-G. Brown, MA, MSc.', true, true),   // initials lastname + title + degree
			array('IFC Peterberg', false, false),                  // initials lastname
			array('Peters, HC', false, false),                     // lastname, initials
			array('Peters HC', false, false),                      // lastname initials
			array('Yu, QK', false, false),                         // short lastname, initials
			array('Yu QK', false, false),                          // short lastname initials
			array('Sperling, Hans P.', false, false),              // lastname, firstname initials
			array('Hans P. Sperling', false, false),               // firstname initials lastname
			array('Sperling, Hans Peter B.', false, false),        // lastname, firstname middlename initials
			array('Hans Peter B. Sperling', false, false),         // firstname middlename initials lastname
			array('Peters, Herbert', false, false),                // lastname, firstname
			array('Prof. Dr. Bernd Rutherford', true, false),      // firstname lastname + title
			array('Her Honour Ruth-Martha Rheinfels', true, false),// double-firstname lastname + title
			array('Sperling, Hans Peter', false, false),           // lastname, firstname middlename
			array('Hans Peter Sperling', false, false),            // firstname middlename lastname
			array('# # # Greenberg # # #', false, false),          // catch-all
		);
		$expectedResults = array(
			array(null, null, null, null, 'Muller'),
			array('His Excellency', null, null, 'B.C.', 'Van de Haan'),
			array('Mrs.', null, null, 'P.-B.', 'von Redfield-Brownfox'),
			array('Professor - MA; MSc', null, null, 'K-G.', 'Brown'),
			array(null, null, null, 'IFC', 'Peterberg'),
			array(null, null, null, 'HC', 'Peters'),
			array(null, null, null, 'HC', 'Peters'),
			array(null, null, null, 'QK', 'Yu'),
			array(null, null, null, 'QK', 'Yu'),
			array(null, 'Hans', null, 'P.', 'Sperling'),
			array(null, 'Hans', null, 'P.', 'Sperling'),
			array(null, 'Hans', 'Peter', 'B.', 'Sperling'),
			array(null, 'Hans', 'Peter', 'B.', 'Sperling'),
			array(null, 'Herbert', null, null, 'Peters'),
			array('Prof. Dr.', 'Bernd', null, null, 'Rutherford'),
			array('Her Honour', 'Ruth-Martha', null, null, 'Rheinfels'),
			array(null, 'Hans', 'Peter', null, 'Sperling'),
			array(null, 'Hans', 'Peter', null, 'Sperling'),
			array(null, null, null, null, '# # # Greenberg # # #'),
		);

		foreach($authorArgumentArray as $testNumber => $authorArguments) {
			$author =& $this->_citationService->parseAuthorString($authorArguments[0], $authorArguments[1], $authorArguments[2]);
			$this->assertAuthor($expectedResults[$testNumber], $author, $testNumber);
		}
	}

	/**
	 * @covers CitationService::parseAuthorsString
	 * @depends testParseAuthorString
	 */
	public function testParseAuthorsString() {
		$authorsString = 'MULLER:IFC Peterberg:Peters HC:Yu QK:Hans Peter B. Sperling:et al';
		$expectedResults = array(
			array(null, null, null, null, 'Muller'),
			array(null, null, null, 'IFC', 'Peterberg'),
			array(null, null, null, 'HC', 'Peters'),
			array(null, null, null, 'QK', 'Yu'),
			array(null, 'Hans', 'Peter', 'B.', 'Sperling'),
		);

		$authors =& $this->_citationService->parseAuthorsString($authorsString, false, false);
		foreach($authors as $testNumber => $author) {
			$this->assertAuthor($expectedResults[$testNumber], $author, $testNumber);
		}

		// Test again, this time with title and degrees
		$authorsString = 'Dr. MULLER; IFC Peterberg; Prof. Peters HC, MSc.; Yu QK;Hans Peter B. Sperling; etal';
		$expectedResults = array(
			array('Dr.', null, null, null, 'Muller'),
			array(null, null, null, 'IFC', 'Peterberg'),
			array('Prof. - MSc', null, null, 'HC', 'Peters'),
			array(null, null, null, 'QK', 'Yu'),
			array(null, 'Hans', 'Peter', 'B.', 'Sperling'),
		);

		$authors =& $this->_citationService->parseAuthorsString($authorsString, true, true);
		foreach($authors as $testNumber => $author) {
			$this->assertAuthor($expectedResults[$testNumber], $author, $testNumber);
		}

	}

	/**
	 * @covers CitationService::normalizeDateString
	 */
	public function testNormalizeDateString() {
		self::assertEquals('2003', $this->_citationService->normalizeDateString(' 2003 '));
		self::assertEquals('2003-07', $this->_citationService->normalizeDateString(' 2003  Jul '));
		self::assertEquals('2003-07-05', $this->_citationService->normalizeDateString(' 2003  Jul 5 '));
		self::assertEquals('2003', $this->_citationService->normalizeDateString(' 2003  5 '));
		self::assertNull($this->_citationService->normalizeDateString('unparsable string'));
	}

	/**
	 * Test a given author object against an array of expected results
	 * @param $expectedResultArray array
	 * @param $author PKPAuthor
	 * @param $testNumber integer The test number for debugging purposes
	 */
	private function assertAuthor($expectedResultArray, $author, $testNumber) {
		self::assertEquals($expectedResultArray[0], $author->getSalutation(), "Wrong salutation for test $testNumber.");
		self::assertEquals($expectedResultArray[1], $author->getFirstName(), "Wrong first name for test $testNumber.");
		self::assertEquals($expectedResultArray[2], $author->getMiddleName(), "Wrong middle name for test $testNumber.");
		self::assertEquals($expectedResultArray[3], $author->getInitials(), "Wrong initials for test $testNumber.");
		self::assertEquals($expectedResultArray[4], $author->getLastName(), "Wrong last name for test $testNumber.");
	}
}
?>

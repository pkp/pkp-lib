<?php

/**
 * @file tests/classes/citation/CitationServiceTest.inc.php
 *
 * Copyright (c) 2000-2010 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class CitationParserServiceTest
 * @ingroup tests_classes_citation
 * @see CitationParserService
 *
 * @brief Tests for the CitationParserService class.
 */

// $Id$

import('tests.PKPTestCase');
import('citation.CitationParserService');
import('citation.Citation');

class CitationParserServiceTest extends PKPTestCase {
	private $_citationParserService;

	public function setUp() {
		$this->_citationParserService = new CitationParserService();
	}

	/**
	 * @covers CitationParserService::parse
	 */
	public function testParse() {
		$citation = new Citation(METADATA_GENRE_JOURNALARTICLE, 'rawCitation');
		// Set an "unclean" article title and add a comment
		// to test whether parse() cleans this up.
		$citation->setArticleTitle('Article Title with Punctuation.');
		$citation->addComment('Comment with Punctuation.');

		// Mock CitationParserService->parseInternal() which will usually
		// be implemented by sub-classes.
		$mockCPService =&
				$this->getMock('CitationParserService', array('parseInternal'));

		// Set up the parseInternal() method
		$mockCPService->expects($this->once())
		              ->method('parseInternal')
		              ->with($this->equalTo($mockCPService->getCitationString($citation)))
		              ->will($this->returnValue($citation));

		// Call the SUT
		$citation = $mockCPService->parse($citation);

		// Test whether the citation has been cleaned up.
		self::assertEquals('Article Title with Punctuation', $citation->getArticleTitle());
		self::assertEquals(array('Comment with Punctuation'), $citation->getComments());
	}

	/**
	 * @covers CitationParserService::getCitationString
	 */
	public function testGetCitationString() {
		$citation = new Citation(METADATA_GENRE_JOURNALARTICLE, 'rawCitation');
		self::assertEquals('rawCitation', $this->_citationParserService->getCitationString($citation));
		$citation->setEditedCitation('editedCitation');
		self::assertEquals('editedCitation', $this->_citationParserService->getCitationString($citation));
		$citation->setCitationState(CITATION_RAW);
		self::assertEquals('rawCitation', $this->_citationParserService->getCitationString($citation));
		$citation->setCitationState(CITATION_PARSED);
		self::assertEquals('editedCitation', $this->_citationParserService->getCitationString($citation));
		$citation->setCitationState(CITATION_LOOKED_UP);
		self::assertEquals('editedCitation', $this->_citationParserService->getCitationString($citation));
		$citation->setRawCitation("String with\n\nline  feed,\rcarriage return,\ttab");
		self::assertEquals('String with line feed, carriage return, tab', $this->_citationParserService->getCitationString($citation));
	}
}
?>

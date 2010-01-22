<?php

/**
 * @file tests/config/CrossrefCitationLookupServiceTest.inc.php
 *
 * Copyright (c) 2000-2010 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class CrossrefCitationLookupServiceTest
 * @ingroup tests_classes_citation_lookup
 * @see CrossrefCitationLookupService
 *
 * @brief Tests for the CrossrefCitationLookupService class.
 */

// $Id$

import('tests.classes.citation.CitationServiceTestCase');
import('citation.lookup.CrossrefCitationLookupService');

class CrossrefCitationLookupServiceTest extends CitationServiceTestCase {
	const
		ACCESS_EMAIL = 'pkp-support@sfu.ca';

	public function setUp() {
		$this->setCitationServiceName('CrossrefCitationLookupService');
	}

	/**
	 * Test CrossRef lookup with DOI
	 * @covers CrossrefCitationLookupService::lookup
	 */
	public function testDoiLookup() {
		// Test Article
		$articleCitation = new Citation(METADATA_GENRE_UNKNOWN);
		$articleCitation->setDOI('10.1186/1471-2105-5-147');
		$expectedArticleElements = array (
			'genre' => METADATA_GENRE_JOURNALARTICLE,
			'authors' => array (
				array ('firstName' => 'Hao', 'lastName' => 'Chen'),
				array ('firstName' => 'Burt', 'initials' => 'M', 'lastName' => 'Sharp')
			),
			'firstPage' => '147',
			'issuedDate' => '2004',
			'doi' => '10.1186/1471-2105-5-147',
			'url' => 'http://www.biomedcentral.com/1471-2105/5/147',
			'journalTitle' => 'BMC Bioinformatics',
			'issue' => '1',
			'volume' => '5'
		);

		// Conference Proceeding
		$proceedingCitation = new Citation(METADATA_GENRE_UNKNOWN);
		$proceedingCitation->setDOI('10.1145/311625.311726');
		$expectedProceedingElements = array (
			'genre' => METADATA_GENRE_CONFERENCEPROCEEDING,
			'articleTitle' => 'The SIGGRAFFITI wall',
			'authors' => array (
				array ('firstName' => 'Richard', 'lastName' => 'Dunn-Roberts')
			),
			'firstPage' => '94',
			'issuedDate' => '1999',
			'doi' => '10.1145/311625.311726',
			'url' => 'http://portal.acm.org/citation.cfm?doid=311625.311726',
			'isbn' => '1581131038',
			'publisher' => 'ACM Press',
			'place' => 'New York, New York, USA',
			'journalTitle' => 'ACM SIGGRAPH 99 Conference abstracts and applications on   - SIGGRAPH \'99'
		);

		// Book
		$bookCitation = new Citation(METADATA_GENRE_UNKNOWN);
		$bookCitation->setDOI('10.1093/ref:odnb/31418');
		$expectedBookElements = array (
			'genre' => METADATA_GENRE_BOOK,
			'authors' =>
			array (
				array ('lastName' => 'Matthew, H. C. G.'),
				array ('initials' => 'B.', 'lastName' => 'Harrison')
			),
			'issuedDate' => '2004',
			'doi' => '10.1093/ref:odnb/31418',
			'bookTitle' => 'The Oxford Dictionary of National Biography',
			'publisher' => 'Oxford University Press',
			'place' => 'Oxford'
		);

		// Build the test citations array
		$testCitations = array(
			array(
				'testCitation' => $articleCitation,
				'expectedResult' => $expectedArticleElements
			),
			array(
				'testCitation' => $proceedingCitation,
				'expectedResult' => $expectedProceedingElements
			),
			array(
				'testCitation' => $bookCitation,
				'expectedResult' => $expectedBookElements
			)
		);

		// Execute the test
		$this->assertCitationService($testCitations, array('email' => self::ACCESS_EMAIL));
	}

	/**
	 * Test CrossRef lookup without DOI
	 * @covers CrossrefCitationLookupService::lookup
	 */
	public function testOpenUrlLookup() {
		$author = new PKPAuthor();
		$author->setFirstName('Hao');
		$author->setLastName('Chen');

		$citation = new Citation(METADATA_GENRE_UNKNOWN);
		$citation->setJournalTitle('BMC Bioinformatics');
		$citation->setIssue('1');
		$citation->setVolume('5');
		$citation->setFirstPage('147');
		$citation->addAuthor($author);

		// Build the test citations array
		$testCitations = array(
			array(
				'testCitation' => $citation,
				'expectedResult' => array (
					'genre' => METADATA_GENRE_JOURNALARTICLE,
					'authors' => array (
						array ('firstName' => 'Hao', 'lastName' => 'Chen'),
						array ('firstName' => 'Burt', 'initials' => 'M', 'lastName' => 'Sharp')
					),
					'firstPage' => '147',
					'issuedDate' => '2004',
					'doi' => '10.1186/1471-2105-5-147',
					'url' => 'http://www.biomedcentral.com/1471-2105/5/147',
					'journalTitle' => 'BMC Bioinformatics',
					'issue' => '1',
					'volume' => '5'
				)
			)
		);

		// Execute the test
		$this->assertCitationService($testCitations, array('email' => self::ACCESS_EMAIL));
	}

	/**
	 * @covers CrossrefCitationLookupService::openUrlKevEncode
	 */
	public function testOpenUrlKevEncode() {
		$author = new PKPAuthor();
		$author->setFirstName('firsname');
		$author->setLastName('lastname');

		$citation = new Citation();
		$citationElements = array(
			'authors' => array($author),
			'journalTitle' => 'some journal title',
			'articleTitle' => 'some article title',
			'issn' => 'some issn',
			'issuedDate' => '2009-09-20',
			'volume' => '64',
			'issue' => '7',
			'firstPage' => '64',
			'lastPage' => '128'
		);

		self::assertTrue($citation->setElementsFromArray($citationElements));
		$lookupService = $this->getCitationServiceInstance();
		$kevString = $lookupService->openUrlKevEncode($citation);
		self::assertEquals('&rft.atitle=some+article+title&rft.spage=64&rft.epage=128&rft.date=2009-09-20&rft.issn=some+issn&rft.jtitle=some+journal+title&rft.issue=7&rft.volume=64&rft.aufirst=firsname&rft.aulast=lastname',
				$kevString);
	}
}
?>

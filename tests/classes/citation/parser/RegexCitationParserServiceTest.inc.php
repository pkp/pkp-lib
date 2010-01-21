<?php

/**
 * @file tests/config/RegexCitationParserServiceTest.inc.php
 *
 * Copyright (c) 2003-2009 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class RegexCitationParserServiceTest
 * @ingroup tests_classes_citation_parser
 * @see RegexCitationParserService
 *
 * @brief Tests for the RegexCitationParserService class.
 */

// $Id$

import('tests.classes.citation.parser.CitationParserServiceTestCase');
import('citation.parser.RegexCitationParserService');

class RegexCitationParserServiceTest extends CitationParserServiceTestCase {
	public function setUp() {
		$this->setCitationServiceName('RegexCitationParserService');
	}

	/**
	 * @covers RegexCitationParserService::parseInternal
	 */
	public function testParseInternal() {
		$testCitations = array(
			array(
				'testCitation' => 'McFarland EG, Park HB. Am J Clin Nutr. 2003 Sep;78(3 Suppl):647-650. PMID: 12936960 [PubMed - indexed for MEDLINE] [] <http://www.ncbi.nlm.nih.gov/entrez/query.fcgi?cmd=Retrieve&db=pubMed&list_uids=12936960&dopt=Abstract>',
				'expectedResult' => array (
					'genre' => METADATA_GENRE_JOURNALARTICLE,
					'authors' => array(
						array('initials' => 'EG', 'lastName' => 'McFarland'),
						array('initials' => 'HB', 'lastName' => 'Park'),
					),
					'firstPage' => '647',
					'lastPage' => '650',
					'issuedDate' => '2003-09',
					'url' => 'http://www.ncbi.nlm.nih.gov/entrez/query.fcgi?cmd=Retrieve&db=pubMed&list_uids=12936960&dopt=Abstract',
					'comments' => array('PubMed - indexed for MEDLINE'),
					'issue' => '3 Suppl',
					'volume' => '78',
					'pmId' => '12936960',
				)
			),
			array(
				'testCitation' => 'McFarland EG, Park HB. Limited lateral acromioplasty for rotator cuff surgery. Orthopedics 2005; 28(3):256-259. [accessed: 2009 Jul 17] http://www.ncbi.nlm.nih.gov/entrez/query.fcgi?cmd=PureSearch&db=pubMed&details_term=15790083',
				'expectedResult' => array(
					'genre' => METADATA_GENRE_JOURNALARTICLE,
					'authors' => array(
						array('initials' => 'EG', 'lastName' => 'McFarland'),
						array('initials' => 'HB', 'lastName' => 'Park')
					),
					'articleTitle' => 'Limited lateral acromioplasty for rotator cuff surgery',
					'firstPage' => '256',
					'lastPage' => '259',
					'issuedDate' => '2005',
					'accessDate' => '2009-07-17',
					'url' => 'http://www.ncbi.nlm.nih.gov/entrez/query.fcgi?cmd=PureSearch&db=pubMed&details_term=15790083',
					'journalTitle' => 'Orthopedics',
					'issue' => '3',
					'volume' => '28',
					'pmId' => '15790083'
				)
			),
			array(
				'testCitation' => 'McFarland EG, Park HB. Limited lateral acromioplasty for rotator cuff surgery. Orthopedics 2005; 28(3):256-259. doi:10.1000/182. http://www.ncbi.nlm.nih.gov/entrez/query.fcgi?cmd=PureSearch&db=pubMed&pubmedid=15790083',
				'expectedResult' => array(
					'genre' => METADATA_GENRE_JOURNALARTICLE,
					'authors' => array(
						array('initials' => 'EG', 'lastName' => 'McFarland'),
						array('initials' => 'HB', 'lastName' => 'Park')
					),
					'articleTitle' => 'Limited lateral acromioplasty for rotator cuff surgery',
					'firstPage' => '256',
					'lastPage' => '259',
					'issuedDate' => '2005',
					'doi' => '10.1000/182',
					'url' => 'http://www.ncbi.nlm.nih.gov/entrez/query.fcgi?cmd=PureSearch&db=pubMed&pubmedid=15790083',
					'journalTitle' => 'Orthopedics',
					'issue' => '3',
					'volume' => '28',
					'pmId' => '15790083'
				)
			),
			array(
				'testCitation' => 'Murray PR, Rosenthal KS, et al. Medical microbiology. 4th ed. New York: Mosby; 2002.',
				'expectedResult' => array(
					'genre' => METADATA_GENRE_BOOK,
					'authors' => array(
						array('initials' => 'PR', 'lastName' => 'Murray'),
						array('initials' => 'KS', 'lastName' => 'Rosenthal')
					),
					'issuedDate' => '2002',
					'bookTitle' => 'Medical microbiology. 4th ed',
					'publisher' => 'Mosby',
					'place' => 'New York'
				)
			),
			array(
				'testCitation' => 'Limited lateral acromioplasty for rotator cuff surgery. URL: http://www.ncbi.nlm.nih.gov/entrez/query.fcgi',
				'expectedResult' => array(
					'genre' => METADATA_GENRE_UNKNOWN,
					'articleTitle' => 'Limited lateral acromioplasty for rotator cuff surgery',
					'url' => 'http://www.ncbi.nlm.nih.gov/entrez/query.fcgi'
				)
			),
			array(
				'testCitation' => 'McFarland EG, Park HB. Limited lateral acromioplasty for rotator cuff surgery. URL: http://www.ncbi.nlm.nih.gov/entrez/query.fcgi',
				'expectedResult' => array(
					'genre' => METADATA_GENRE_UNKNOWN,
					'authors' => array(
						array('initials' => 'EG', 'lastName' => 'McFarland'),
						array('initials' => 'HB', 'lastName' => 'Park')
					),
					'articleTitle' => 'Limited lateral acromioplasty for rotator cuff surgery',
					'url' => 'http://www.ncbi.nlm.nih.gov/entrez/query.fcgi'
				)
			),
			array(
				'testCitation' => 'McFarland EG, Park HB. Limited lateral acromioplasty for rotator cuff surgery. Web edition test. Orthopedics 2005. URL: http://www.ncbi.nlm.nih.gov/entrez/query.fcgi',
				'expectedResult' => array(
					'genre' => METADATA_GENRE_UNKNOWN,
					'authors' => array(
						array('initials' => 'EG', 'lastName' => 'McFarland'),
						array('initials' => 'HB', 'lastName' => 'Park')
					),
					'articleTitle' => 'Limited lateral acromioplasty for rotator cuff surgery. Web edition test',
					'url' => 'http://www.ncbi.nlm.nih.gov/entrez/query.fcgi',
					'journalTitle' => 'Orthopedics 2005'
				)
			)
		);

		$this->assertCitationService($testCitations);
	}
}
?>

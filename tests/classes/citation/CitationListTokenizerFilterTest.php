<?php

/**
 * @file tests/classes/validation/CitationListTokenizerFilterTest.inc.php
 *
 * Copyright (c) 2013-2017 Simon Fraser University
 * Copyright (c) 2000-2016 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class CitationListTokenizerFilterTest
 * @ingroup tests_classes_citation
 * @see CitationListTokenizerFilter
 *
 * @brief Test class for CitationListTokenizerFilter.
 */

import('lib.pkp.tests.PKPTestCase');
import('lib.pkp.classes.citation.CitationListTokenizerFilter');

class CitationListTokenizerFilterTest extends PKPTestCase {
	/**
	 * @covers CitationListTokenizerFilter
	 */
	public function testCitationListTokenizerFilter() {
		$tokenizer = new CitationListTokenizerFilter();

		// Gold standard matches
		$expectedResult = array(
			'Author, F. M. (1900).  Title of Work.  City: Publisher.',
			'Lastname, F. & Surname, F. (2000). Title of Article: Subtitle.  Journal Name, 6(2), 100-102.  Retrieved from http://hostname/path',
			'Writer, N. (2010, January 1). Message subject line [Message posted to LISTNAME-L electronic mailing list].  Retrieved from http://LISTSERV-L-OWNER@lists.hostname',
			'Author, A. & Author, B. (1910). Article title.  Journal Title, 99, 400-404. doi: 10.5555/12345678<99:400>',
		);
		$rawCitationLists = array();
		$rawCitationLists["hash-number-dot"] = "#1. Author, F. M. (1900).  Title of Work.  City: Publisher.\n#2. Lastname, F. & Surname, F. (2000). Title of Article: Subtitle.  Journal Name, 6(2), 100-102.  Retrieved from http://hostname/path\n#3. Writer, N. (2010, January 1). Message subject line [Message posted to LISTNAME-L electronic mailing list].  Retrieved from http://LISTSERV-L-OWNER@lists.hostname\n#4. Author, A. & Author, B. (1910). Article title.  Journal Title, 99, 400-404. doi: 10.5555/12345678<99:400>\n";
		$rawCitationLists["number-dot"] = "1. Author, F. M. (1900).  Title of Work.  City: Publisher.\n2. Lastname, F. & Surname, F. (2000). Title of Article: Subtitle.  Journal Name, 6(2), 100-102.  Retrieved from http://hostname/path\n3. Writer, N. (2010, January 1). Message subject line [Message posted to LISTNAME-L electronic mailing list].  Retrieved from http://LISTSERV-L-OWNER@lists.hostname\n4. Author, A. & Author, B. (1910). Article title.  Journal Title, 99, 400-404. doi: 10.5555/12345678<99:400>\n";
		$rawCitationLists["hash-number-paren"] = "#1) Author, F. M. (1900).  Title of Work.  City: Publisher.\n#2) Lastname, F. & Surname, F. (2000). Title of Article: Subtitle.  Journal Name, 6(2), 100-102.  Retrieved from http://hostname/path\n#3) Writer, N. (2010, January 1). Message subject line [Message posted to LISTNAME-L electronic mailing list].  Retrieved from http://LISTSERV-L-OWNER@lists.hostname\n#4) Author, A. & Author, B. (1910). Article title.  Journal Title, 99, 400-404. doi: 10.5555/12345678<99:400>\n";
		$rawCitationLists["number-paren"] = "1) Author, F. M. (1900).  Title of Work.  City: Publisher.\n2) Lastname, F. & Surname, F. (2000). Title of Article: Subtitle.  Journal Name, 6(2), 100-102.  Retrieved from http://hostname/path\n3) Writer, N. (2010, January 1). Message subject line [Message posted to LISTNAME-L electronic mailing list].  Retrieved from http://LISTSERV-L-OWNER@lists.hostname\n4) Author, A. & Author, B. (1910). Article title.  Journal Title, 99, 400-404. doi: 10.5555/12345678<99:400>\n";
		$rawCitationLists["number-square"] = "[1] Author, F. M. (1900).  Title of Work.  City: Publisher.\n[2] Lastname, F. & Surname, F. (2000). Title of Article: Subtitle.  Journal Name, 6(2), 100-102.  Retrieved from http://hostname/path\n[3] Writer, N. (2010, January 1). Message subject line [Message posted to LISTNAME-L electronic mailing list].  Retrieved from http://LISTSERV-L-OWNER@lists.hostname\n[4] Author, A. & Author, B. (1910). Article title.  Journal Title, 99, 400-404. doi: 10.5555/12345678<99:400>\n";
		$rawCitationLists["newline"] = "Author, F. M. (1900).  Title of Work.  City: Publisher.\nLastname, F. & Surname, F. (2000). Title of Article: Subtitle.  Journal Name, 6(2), 100-102.  Retrieved from http://hostname/path\nWriter, N. (2010, January 1). Message subject line [Message posted to LISTNAME-L electronic mailing list].  Retrieved from http://LISTSERV-L-OWNER@lists.hostname\nAuthor, A. & Author, B. (1910). Article title.  Journal Title, 99, 400-404. doi: 10.5555/12345678<99:400>\n\n";
		$rawCitationLists["newlines"] = "Author, F. M. (1900).  Title of Work.  City: Publisher.\n\nLastname, F. & Surname, F. (2000). Title of Article: Subtitle.  Journal Name, 6(2), 100-102.  Retrieved from http://hostname/path\n\nWriter, N. (2010, January 1). Message subject line [Message posted to LISTNAME-L electronic mailing list].  Retrieved from http://LISTSERV-L-OWNER@lists.hostname\n\nAuthor, A. & Author, B. (1910). Article title.  Journal Title, 99, 400-404. doi: 10.5555/12345678<99:400>\n\n\n\n";
		$rawCitationLists["crlf"] = "Author, F. M. (1900).  Title of Work.  City: Publisher.\r\nLastname, F. & Surname, F. (2000). Title of Article: Subtitle.  Journal Name, 6(2), 100-102.  Retrieved from http://hostname/path\r\nWriter, N. (2010, January 1). Message subject line [Message posted to LISTNAME-L electronic mailing list].  Retrieved from http://LISTSERV-L-OWNER@lists.hostname\r\nAuthor, A. & Author, B. (1910). Article title.  Journal Title, 99, 400-404. doi: 10.5555/12345678<99:400>\r\n\r\n";
		$rawCitationLists["crlfs"] = "Author, F. M. (1900).  Title of Work.  City: Publisher.\r\n\r\nLastname, F. & Surname, F. (2000). Title of Article: Subtitle.  Journal Name, 6(2), 100-102.  Retrieved from http://hostname/path\r\n\r\nWriter, N. (2010, January 1). Message subject line [Message posted to LISTNAME-L electronic mailing list].  Retrieved from http://LISTSERV-L-OWNER@lists.hostname\r\n\r\nAuthor, A. & Author, B. (1910). Article title.  Journal Title, 99, 400-404. doi: 10.5555/12345678<99:400>\r\n\r\n\r\n\r\n";
		foreach ($rawCitationLists as $key => $rawCitationList) {
			self::assertEquals($expectedResult, $tokenizer->process($rawCitationList), $key);
		}

		// Whitespace may be lossy, but count should be good
		$rawCitationsLists = array();
		$rawCitationLists["wordwrap-tab"] = "Author, F. M. \n\t(1900).  Title of \n\tWork.  City: \n\tPublisher.\nLastname, F. & \n\tSurname, F. (2000). \n\tTitle of Article: \n\tSubtitle.  Journal \n\tName, 6(2), 100-102. \n\t Retrieved from \n\thttp://hostname/path\nWriter, N. (2010, \n\tJanuary 1). Message \n\tsubject line \n\t[Message posted to \n\tLISTNAME-L \n\telectronic mailing \n\tlist].  Retrieved \n\tfrom \n\thttp://LISTSERV-L-OW\n\tNER@lists.hostname\nAuthor, A. & Author, \n\tB. (1910). Article \n\ttitle.  Journal \n\tTitle, 99, 400-404. \n\tdoi: \n  10.5555/12345678<99:\n\t400>\n";
		$rawCitationLists["wordwrap-space"] = "Author, F. M.\n    (1900).  Title of\n    Work.  City:\n    Publisher.\nLastname, F. &\n    Surname, F. (2000).\n    Title of Article:\n    Subtitle.  Journal\n    Name, 6(2), 100-102.\n     Retrieved from\n    http://hostname/path\nWriter, N. (2010,\n    January 1). Message\n    subject line\n    [Message posted to\n    LISTNAME-L\n    electronic mailing\n    list].  Retrieved\n    from\n    http://LISTSERV-L-OW\n    NER@lists.hostname\nAuthor, A. & Author,\n    B. (1910). Article\n    title.  Journal\n    Title, 99, 400-404.\n    doi:\n    10.5555/12345678<99:\n    400>\n";
		$rawCitationLists["wordwrap-messy"] = "\n\nAuthor, F. M.\n    (1900).  Title of\n    Work.  City:\n    Publisher.\n\n\n    \n    \nLastname, F. &\n    Surname, F. (2000).\n    Title of Article:\n    Subtitle.  Journal\n    Name, 6(2), 100-102.\n     Retrieved from\n    http://hostname/path\n\n\nWriter, N. (2010,\n    January 1). Message\n    subject line\n    [Message posted to\n    LISTNAME-L\n    electronic mailing\n    list].  Retrieved\n    from\n    http://LISTSERV-L-OW\n    NER@lists.hostname\n\nAuthor, A. & Author,\n    B. (1910). Article\n    title.  Journal\n    Title, 99, 400-404.\n    doi:\n    10.5555/12345678<99:\n    400>\n\n\n";
		foreach ($rawCitationLists as $key => $rawCitationList) {
			self::assertCount(count($expectedResult), $tokenizer->process($rawCitationList), $key);
		}

		// Empty input should give empty output
		foreach (array('empty' => '', 'only-newlines' => "\n\n", 'only-crlf' => "\r\n", 'only-newline' => "\n", 'only-whitespace' => "\n  \n\t\n   ") as $key => $rawCitationList) {
			self::assertEquals(array(), $tokenizer->process($rawCitationList), $key);
		}
	}
}
?>

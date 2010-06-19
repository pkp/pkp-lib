<?php

/**
 * @file tests/classes/validation/CitationListTokenizerFilterTest.inc.php
 *
 * Copyright (c) 2000-2010 John Willinsky
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
	    $rawCitationList = "citation1\n\ncitation2\r\ncitation3\ncitation4";
	    $expectedResult = array(
	    	'citation1',
	    	'citation2',
	    	'citation3',
	    	'citation4'
	    );
	    self::assertEquals($expectedResult, $tokenizer->process($rawCitationList));
	}
}
?>
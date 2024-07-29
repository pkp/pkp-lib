<?php

/**
 * @file tests/classes/citation/CitationListTokenizerFilterTest.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class CitationListTokenizerFilterTest
 *
 * @ingroup tests_classes_citation
 *
 * @see CitationListTokenizerFilter
 *
 * @brief Test class for CitationListTokenizerFilter.
 */

namespace PKP\tests\classes\citation;

use PKP\citation\CitationListTokenizerFilter;
use PKP\tests\PKPTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(CitationListTokenizerFilter::class)]
class CitationListTokenizerFilterTest extends PKPTestCase
{
    public function testCitationListTokenizerFilter()
    {
        $tokenizer = new CitationListTokenizerFilter();
        $rawCitationList = "\t1. citation1\n\n2 citation2\r\n 3) citation3\n[4]citation4";
        $expectedResult = [
            '1. citation1',
            '2 citation2',
            '3) citation3',
            '[4]citation4'
        ];
        self::assertEquals($expectedResult, $tokenizer->process($rawCitationList));

        $rawCitationList = '';
        self::assertEquals([], $tokenizer->process($rawCitationList));
    }
}

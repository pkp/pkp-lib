<?php

/**
 * @file tests/classes/citation/ExtractPidsHelperTest.php
 *
 * Copyright (c) 2026 Simon Fraser University
 * Copyright (c) 2026 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class ExtractPidsHelperTest
 *
 * @ingroup tests_classes_citation
 *
 * @see \PKP\citation\pid\ExtractPidsHelper
 *
 * @brief Test extracting PIDs and the URL from a reference's text.
 */

namespace PKP\tests\classes\citation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PKP\citation\Citation;
use PKP\citation\pid\ExtractPidsHelper;
use PKP\tests\PKPTestCase;

#[CoversClass(ExtractPidsHelper::class)]
class ExtractPidsHelperTest extends PKPTestCase
{
    public static function referenceDataProvider(): array
    {
        return [
            // The URL is kept as written, not rewritten to https.
            ['Smith J. Report. Available at http://example.org/report', ['url' => 'http://example.org/report']],
            ['Smith J. Report. Available at https://example.org/report.', ['url' => 'https://example.org/report']],
            ['Smith J. Report. Available at HTTP://example.org/report', ['url' => 'HTTP://example.org/report']],
            ['Smith J. Report. Available at: Http://example.org/report', ['url' => 'Http://example.org/report']],
            ['Smith J. Report. Available at HTTPS://EXAMPLE.ORG/report', ['url' => 'HTTPS://EXAMPLE.ORG/report']],
            // A DOI link, http or https and in any case, is not taken as the URL.
            ['Smith J. Title. http://dx.doi.org/10.1234/abc', ['doi' => '10.1234/abc', 'url' => null]],
            ['Smith J. Title. DOI: 10.1234/abc. Data at http://example.org/data', ['doi' => '10.1234/abc', 'url' => 'http://example.org/data']],
            ['Doe J. Attention. arXiv:2101.12345v2. Code at https://example.org/code', ['arxiv' => '2101.12345v2', 'url' => 'https://example.org/code']],
            ['Report. http://hdl.handle.net/10419/12345', ['handle' => '10419/12345', 'url' => null]],
        ];
    }

    #[DataProvider('referenceDataProvider')]
    public function testExecute(string $rawCitation, array $expected): void
    {
        $citation = new Citation();
        $citation->setRawCitation($rawCitation);

        $citation = (new ExtractPidsHelper())->execute($citation);

        foreach ($expected as $key => $value) {
            self::assertSame($value, $citation->getData($key), $key);
        }
    }
}

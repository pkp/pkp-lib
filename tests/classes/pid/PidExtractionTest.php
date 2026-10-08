<?php

/**
 * @file tests/classes/pid/PidExtractionTest.php
 *
 * Copyright (c) 2026 Simon Fraser University
 * Copyright (c) 2026 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PidExtractionTest
 *
 * @ingroup tests_classes_pid
 *
 * @see \PKP\pid\BasePid
 *
 * @brief Test extracting PIDs from reference text and removing their prefixes.
 */

namespace PKP\tests\classes\pid;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PKP\pid\Ark;
use PKP\pid\Arxiv;
use PKP\pid\BasePid;
use PKP\pid\Doi;
use PKP\pid\Handle;
use PKP\pid\Orcid;
use PKP\pid\Pmid;
use PKP\pid\Urn;
use PKP\tests\PKPTestCase;

#[CoversClass(BasePid::class)]
class PidExtractionTest extends PKPTestCase
{
    public static function extractFromStringDataProvider(): array
    {
        return [
            [Doi::class, 'Smith J. Title. 2020. https://doi.org/10.1234/abc.123', '10.1234/abc.123'],
            [Doi::class, 'Smith J. Title. 2020. https://dx.doi.org/10.1234/abc', '10.1234/abc'],
            [Doi::class, 'Smith J. Title. 2020. http://dx.doi.org/10.1234/abc', '10.1234/abc'],
            [Doi::class, 'Smith J. Title. 2020. https://www.doi.org/10.1234/abc', '10.1234/abc'],
            [Doi::class, 'Smith J. Title. doi:10.1234/abc, 2020', '10.1234/abc'],
            [Doi::class, 'Smith J. Title. doi: 10.1234/abc;', '10.1234/abc'],
            [Doi::class, 'Smith J. Title (doi:10.1234/abc).', '10.1234/abc'],
            [Doi::class, 'Smith J. Title (https://doi.org/10.1234/(abc)def).', '10.1234/(abc)def'],
            [Doi::class, 'Smith J. Title. doi:10.1000/jdoi.2020.5', '10.1000/jdoi.2020.5'],
            [Doi::class, 'Smith J. Title. 10.1234/abc.', ''],
            [Arxiv::class, 'Doe J. Attention. arXiv:2101.12345v2 [cs.CL].', '2101.12345v2'],
            [Arxiv::class, 'Doe J. Attention. https://arxiv.org/abs/hep-th/9901001v1.', 'hep-th/9901001v1'],
            [Arxiv::class, 'Doe J. Attention. arXiv:2101.12345 vol 2', '2101.12345'],
            [Handle::class, 'Report. hdl:10419/12345. Accessed 2020-01-01.', '10419/12345'],
            [Handle::class, 'Report. https://hdl.handle.net/10419/12345, 2020.', '10419/12345'],
            [Ark::class, 'Map. ark:/12345/abc123, accessed 2020.', 'ark:/12345/abc123'],
            [Urn::class, 'Thesis. urn:nbn:de:101:1-2019072802401757702913, 2019.', 'urn:nbn:de:101:1-2019072802401757702913'],
        ];
    }

    #[DataProvider('extractFromStringDataProvider')]
    public function testExtractFromString(string $pidClass, string $string, string $expected): void
    {
        self::assertSame($expected, $pidClass::extractFromString($string));
    }

    public static function removePrefixDataProvider(): array
    {
        return [
            [Doi::class, '10.1000/jdoi.2020.5', '10.1000/jdoi.2020.5'],
            [Doi::class, 'doi: 10.1234/abc', '10.1234/abc'],
            [Doi::class, 'http://doi.org/10.1234/abc', '10.1234/abc'],
            [Doi::class, 'https://www.doi.org/10.1234/abc', '10.1234/abc'],
            [Handle::class, '10419/hdlfoo', '10419/hdlfoo'],
            [Handle::class, 'hdl:10419/hdlfoo', '10419/hdlfoo'],
            [Arxiv::class, 'arxiv:2101.12345v2', '2101.12345v2'],
            [Pmid::class, 'PMID12345678', '12345678'],
            [Orcid::class, 'http://orcid.org/0000-0002-1694-233X', '0000-0002-1694-233X'],
        ];
    }

    #[DataProvider('removePrefixDataProvider')]
    public function testRemovePrefix(string $pidClass, string $string, string $expected): void
    {
        self::assertSame($expected, $pidClass::removePrefix($string));
    }
}

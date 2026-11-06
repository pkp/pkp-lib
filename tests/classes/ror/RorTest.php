<?php

/**
 * @file tests/classes/ror/RorTest.php
 *
 * Copyright (c) 2026 Simon Fraser University
 * Copyright (c) 2026 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class RorTest
 *
 * @brief Tests for the Ror class.
 */

namespace PKP\tests\classes\ror;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PKP\ror\Ror;
use PKP\tests\PKPTestCase;

#[CoversClass(Ror::class)]
class RorTest extends PKPTestCase
{
    public static function nameForLocaleProvider(): array
    {
        return [
            'ISO 639-1 locale' => ['ga', 'Coláiste na hOllscoile'],
            'regional variant' => ['en_US', 'University College Cork'],
            'no ROR name in locale' => ['fr_CA', 'University College Cork'],
            'no ISO 639-1 code' => ['ckb', 'University College Cork'],
            'regional variant without ISO 639-1 code' => ['ckb_IQ', 'University College Cork'],
            'locale not in Weblate list' => ['fa_AF', 'University College Cork'],
        ];
    }

    #[DataProvider('nameForLocaleProvider')]
    public function testGetNameForLocale(string $locale, string $expected): void
    {
        $ror = new Ror();
        $ror->setData('displayLocale', 'en');
        $ror->setData('name', [
            'en' => 'University College Cork',
            'ga' => 'Coláiste na hOllscoile',
        ]);

        self::assertSame($expected, $ror->getNameForLocale($locale));
    }
}

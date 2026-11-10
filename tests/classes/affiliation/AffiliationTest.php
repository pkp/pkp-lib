<?php

/**
 * @file tests/classes/affiliation/AffiliationTest.php
 *
 * Copyright (c) 2026 Simon Fraser University
 * Copyright (c) 2026 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class AffiliationTest
 *
 * @brief Tests for the Affiliation class.
 */

namespace PKP\tests\classes\affiliation;

use Mockery;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PKP\affiliation\Affiliation;
use PKP\facades\Locale;
use PKP\ror\Ror;
use PKP\tests\PKPTestCase;

#[CoversClass(Affiliation::class)]
class AffiliationTest extends PKPTestCase
{
    private \PKP\i18n\Locale $locale;

    protected function setUp(): void
    {
        parent::setUp();
        $this->locale = Locale::getFacadeRoot();
    }

    protected function tearDown(): void
    {
        Locale::swap($this->locale);
        parent::tearDown();
    }

    public static function localizedNameProvider(): array
    {
        return [
            'ISO 639-1 locale' => ['de', 'de', 'Universität Cork'],
            'regional variant' => ['ga', 'en_US', 'University College Cork'],
            'no ISO 639-1 code' => ['de', 'ckb', 'University College Cork'],
            'regional interface locale' => ['de_CH', null, 'Universität Cork'],
            'interface locale without ISO 639-1 code' => ['kab', null, 'University College Cork'],
        ];
    }

    #[DataProvider('localizedNameProvider')]
    public function testGetLocalizedNameWithRor(string $uiLocale, ?string $preferredLocale, string $expected): void
    {
        $this->setUiLocale($uiLocale);

        self::assertSame($expected, $this->createRorAffiliation()->getLocalizedName($preferredLocale));
    }

    public function testGetAffiliationNameWithRor(): void
    {
        $this->setUiLocale('de');

        self::assertSame(
            [
                'en' => 'University College Cork',
                'fr_CA' => 'University College Cork',
                'de_CH' => 'Universität Cork',
                'ckb' => 'University College Cork',
            ],
            $this->createRorAffiliation()->getAffiliationName(null, ['en', 'fr_CA', 'de_CH', 'ckb'])
        );
    }

    private function createRorAffiliation(): Affiliation
    {
        $ror = new Ror();
        $ror->setData('displayLocale', 'en');
        $ror->setData('name', [
            'en' => 'University College Cork',
            'de' => 'Universität Cork',
        ]);

        $affiliation = new Affiliation();
        $affiliation->setData('rorObject', $ror);
        return $affiliation;
    }

    private function setUiLocale(string $locale): void
    {
        $mock = Mockery::mock($this->locale::class)
            ->makePartial()
            ->shouldReceive('getLocale')
            ->andReturn($locale)
            ->getMock();
        Locale::swap($mock);
    }
}

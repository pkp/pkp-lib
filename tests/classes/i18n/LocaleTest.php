<?php

/**
 * @defgroup tests_classes_i18n I18N Class Test Suite
 */

/**
 * @file tests/classes/i18n/LocaleTest.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class LocaleTest
 * @ingroup tests_classes_i18n
 *
 * @see Locale
 *
 * @brief Tests for the Locale class.
 */

namespace PKP\tests\classes\i18n;

use Mockery;
use Mockery\MockInterface;
use PKP\facades\Locale;
use PKP\i18n\LocaleConversion;
use PKP\i18n\LocaleMetadata;
use PKP\tests\PKPTestCase;

class LocaleTest extends PKPTestCase
{
    private \PKP\i18n\Locale $_locale;
    private array $_supportedLocales = ['en_US' => 'English', 'pt_BR' => 'Portuguese (Brazil)', 'pt_PT' => 'Portuguese (Portugal)'];
    private string $_primaryLocale = 'pt_BR';

    protected function setUp(): void
    {
        parent::setUp();
        // Save the underlying Locale implementation and replaces it by a generic mock
        $this->_locale = Locale::getFacadeRoot();
        $mock = Mockery::mock($this->_locale::class)
            ->makePartial()
            // Custom locales
            ->shouldReceive('getLocales')
            ->andReturn(
                [
                    'en_US' => $this->_createMetadataMock('en_US', true),
                    'pt_BR' => $this->_createMetadataMock('pt_BR'),
                    'pt_PT' => $this->_createMetadataMock('pt_PT'),
                    'de_DE' => $this->_createMetadataMock('de_DE')
                ]
            )
            // Forward get() calls to the real locale, in order to use the already loaded translations
            ->shouldReceive('get')
            ->andReturnUsing(fn (...$args) => $this->_locale->get(...$args))
            // Custom supported locales
            ->shouldReceive('getSupportedLocales')
            ->andReturnUsing(fn () => $this->_supportedLocales)
            // Custom primary locale
            ->shouldReceive('getPrimaryLocale')
            ->andReturnUsing(fn () => $this->_primaryLocale)
            ->getMock();

        Locale::swap($mock);
    }

    protected function tearDown(): void
    {
        // Restores the original locale instance
        Locale::swap($this->_locale);
        parent::tearDown();
    }

    private function _createMetadataMock(string $locale, bool $isComplete = false): MockInterface
    {
        return Mockery::mock(LocaleMetadata::class, [$locale])
            ->makePartial()
            ->shouldReceive('isComplete')
            ->andReturn($isComplete)
            ->getMock();
    }

    /**
     * @covers Locale
     */
    public function testIsLocaleComplete()
    {
        self::assertTrue(Locale::getMetadata('en_US')->isComplete());
        self::assertFalse(Locale::getMetadata('pt_BR')->isComplete());
        self::assertNull(Locale::getMetadata('xx_XX'));
    }

    /**
     * @covers Locale
     */
    public function testGetLocales()
    {
        $expectedLocales = [
            'en_US' => 'English',
            'pt_BR' => 'Portuguese',
            'pt_PT' => 'Portuguese',
            'de_DE' => 'German'
        ];
        $locales = array_map(fn(LocaleMetadata $locale) => $locale->getDisplayName(), Locale::getLocales());
        self::assertEquals($expectedLocales, $locales);
    }

    /**
     * @covers Locale
     */
    public function testGetLocalesWithCountryName()
    {
        $this->markTestSkipped('TODO: Will be fixed by the issue #8040');

        $expectedLocalesWithCountry = [
            'en_US' => 'English (United States)',
            'pt_BR' => 'Portuguese (Brazil)',
            'pt_PT' => 'Portuguese (Portugal)',
            'de_DE' => 'German (Germany)'
        ];
        $locales = array_map(fn(LocaleMetadata $locale) => $locale->getDisplayName(null, true), Locale::getLocales());
        self::assertEquals($expectedLocalesWithCountry, $locales);
    }

    /**
     * @covers Locale
     */
    public function testGet3LetterFrom2LetterIsoLanguage()
    {
        self::assertEquals('eng', LocaleConversion::get3LetterFrom2LetterIsoLanguage('en'));
        self::assertEquals('por', LocaleConversion::get3LetterFrom2LetterIsoLanguage('pt'));
        self::assertNull(LocaleConversion::get3LetterFrom2LetterIsoLanguage('xx'));
    }

    /**
     * @covers Locale
     */
    public function testGet2LetterFrom3LetterIsoLanguage()
    {
        self::assertEquals('en', LocaleConversion::get2LetterFrom3LetterIsoLanguage('eng'));
        self::assertEquals('pt', LocaleConversion::get2LetterFrom3LetterIsoLanguage('por'));
        self::assertNull(LocaleConversion::get2LetterFrom3LetterIsoLanguage('xxx'));
    }

    /**
     * @covers Locale
     */
    public function testGet3LetterIsoFromLocale()
    {
        self::assertEquals('eng', LocaleConversion::get3LetterIsoFromLocale('en_US'));
        self::assertEquals('por', LocaleConversion::get3LetterIsoFromLocale('pt_BR'));
        self::assertEquals('por', LocaleConversion::get3LetterIsoFromLocale('pt_PT'));
        self::assertNull(LocaleConversion::get3LetterIsoFromLocale('xx_XX'));
    }

    /**
     * @covers Locale
     */
    public function testGetLocaleFrom3LetterIso()
    {
        // A locale that does not have to be disambiguated.
        self::assertEquals('en_US', LocaleConversion::getLocaleFrom3LetterIso('eng'));

        // The primary locale will be used if that helps to disambiguate.
        self::assertEquals('pt_BR', LocaleConversion::getLocaleFrom3LetterIso('por'));
        $this->_primaryLocale  = 'pt_PT';
        self::assertEquals('pt_PT', LocaleConversion::getLocaleFrom3LetterIso('por'));

        // If the primary locale doesn't help then use the first supported locale found.
        $this->_primaryLocale  = 'en_US';
        self::assertEquals('pt_BR', LocaleConversion::getLocaleFrom3LetterIso('por'));
        $this->_supportedLocales = ['en_US' => 'English', 'pt_PT' => 'Portuguese (Portugal)', 'pt_BR' => 'Portuguese (Brazil)'];
        self::assertEquals('pt_PT', LocaleConversion::getLocaleFrom3LetterIso('por'));

        // If the locale isn't even in the supported locales then use the first locale found.
        $this->_supportedLocales = ['en_US' => 'English'];
        self::assertEquals('pt_BR', LocaleConversion::getLocaleFrom3LetterIso('por'));

        // Unknown language.
        self::assertNull(LocaleConversion::getLocaleFrom3LetterIso('xxx'));
    }
}

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

use PKP\facades\Locale;
use PKP\i18n\LocaleConversion;
use PKP\i18n\LocaleMetadata;

require_mock_env('env1');

import('classes.i18n.Locale'); // This will import our mock Locale class
import('lib.pkp.tests.PKPTestCase');

class LocaleTest extends PKPTestCase
{
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
            'en_US' => 'English (United States)',
            'pt_BR' => 'Portuguese (Brazil)',
            'pt_PT' => 'Portuguese (Portugal)',
            'de_DE' => 'German (Germany)'
        ];
        $locales = array_map(fn(LocaleMetadata $locale) => $locale->getDisplayName(), Locale::getLocales());
        self::assertEquals($expectedLocales, $locales);
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
        Locale::setSupportedLocales(['en_US' => 'English', 'pt_BR' => 'Portuguese (Brazil)', 'pt_PT' => 'Portuguese (Portugal)']);
        Locale::setPrimaryLocale('pt_BR');
        self::assertEquals('pt_BR', LocaleConversion::getLocaleFrom3LetterIso('por'));
        Locale::setPrimaryLocale('pt_PT');
        self::assertEquals('pt_PT', LocaleConversion::getLocaleFrom3LetterIso('por'));

        // If the primary locale doesn't help then use the first supported locale found.
        Locale::setPrimaryLocale('en_US');
        self::assertEquals('pt_BR', LocaleConversion::getLocaleFrom3LetterIso('por'));
        Locale::setSupportedLocales(['en_US' => 'English', 'pt_PT' => 'Portuguese (Portugal)', 'pt_BR' => 'Portuguese (Brazil)']);
        self::assertEquals('pt_PT', LocaleConversion::getLocaleFrom3LetterIso('por'));

        // If the locale isn't even in the supported locales then use the first locale found.
        Locale::setSupportedLocales(['en_US' => 'English']);
        self::assertEquals('pt_BR', LocaleConversion::getLocaleFrom3LetterIso('por'));

        // Unknown language.
        self::assertNull(LocaleConversion::getLocaleFrom3LetterIso('xxx'));
    }
}

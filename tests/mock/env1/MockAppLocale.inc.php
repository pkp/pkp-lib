<?php

/**
 * @file tests/mock/env1/MockAppLocale.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class AppLocale
 * @ingroup tests_mock_env1
 *
 * @brief Mock implementation of the Locale class
 */

namespace APP\i18n;

use PKP\i18n\PKPLocale;

define('LOCALE_ENCODING', 'utf-8');

class AppLocale extends PKPLocale
{
    public const LOCALE_REGISTRY_FILE = 'lib/pkp/tests/registry/locales.xml';

    public static $primaryLocale = 'en_US';
    public static $supportedLocales = ['en_US' => 'English/America'];
    public static $translations = [];

    /**
     * method required during setup of
     * the PKP application framework
     */
    public static function initialize($request)
    {
        // do nothing
    }

    /**
     * method required during setup of
     * the PKP application framework
     * @return string test locale
     */
    public static function getLocale()
    {
        return 'en_US';
    }

    /**
     * method required during setup of
     * the PKP application framework
     */
    public static function registerLocaleFile($locale, $filename, $addToTop = false)
    {
        // do nothing
    }

    /**
     * method required during setup of
     * the PKP templating engine and application framework
     */
    public static function requireComponents()
    {
        // do nothing
    }

    /**
     * Mocked method
     *
     * @return array a test array of locales
     */
    public static function getLocalePrecedence()
    {
        return ['en_US', 'fr_FR'];
    }

    /**
     * Mocked method
     *
     * @param string $key
     * @param array $params named substitution parameters
     * @param string $locale the locale to use
     *
     * @return string
     */
    public static function translate($key, $params = [], $locale = null, $missingKeyHandler = [])
    {
        if (isset(self::$translations[$key])) {
            return self::$translations[$key];
        }
        return "##${key}##";
    }

    /**
     * Setter to configure a custom
     * primary locale for testing.
     *
     * @param string $primaryLocale
     */
    public static function setPrimaryLocale($primaryLocale)
    {
        self::$primaryLocale = $primaryLocale;
    }

    /**
     * Mocked method
     *
     * @return string
     */
    public static function getPrimaryLocale()
    {
        return self::$primaryLocale;
    }

    /**
     * Setter to configure a custom
     * primary locale for testing.
     *
     * @param array $supportedLocales
     *  example array(
     *   'en_US' => 'English',
     *   'de_DE' => 'German'
     *  )
     */
    public static function setSupportedLocales($supportedLocales)
    {
        self::$supportedLocales = $supportedLocales;
    }

    /**
     * Mocked method
     *
     * @return array
     */
    public static function getSupportedLocales()
    {
        return self::$supportedLocales;
    }

    /**
     * Mocked method
     *
     * @return array
     */
    public static function getSupportedFormLocales()
    {
        return ['en_US'];
    }

    /**
     * Set translation keys to be faked.
     *
     * @param array $translations
     */
    public static function setTranslations($translations)
    {
        self::$translations = $translations;
    }
}

if (!PKP_STRICT_MODE) {
    class_alias('\APP\i18n\AppLocale', '\AppLocale');
}

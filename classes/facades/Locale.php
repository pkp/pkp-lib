<?php

/**
 * @file classes/facades/Locale.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class Locale
 *
 * @brief This facade provides access to the locale
 */

namespace PKP\facades;

use Illuminate\Support\Facades\Facade;
use PKP\i18n\interfaces\LocaleInterface;

/**
 * @method static string get(string $key, array $replace = [], $locale = null) Get the translation for a given key.
 * @method static string choice(string $key, int $number, array $replace = [], string $locale = null) Get a translation according to an integer value.
 * @method static string getLocale() Get the default locale being used.
 * @method static void setLocale(string $locale) Set the default locale.
 * @method static string getPrimaryLocale() Deprecated on 3.4.0, use Context::getPrimaryLocale()
 * @method static void registerPath(string $path, int $priority = 0) Register a locale folder
 * @method static void registerLoader(callable $fileLoader, int $priority = 0) Register a locale file loader
 * @method static bool isLocaleValid(string $locale) Check if the supplied locale is valid.
 * @method static \PKP\i18n\LocaleMetadata getMetadata(string $locale) Retrieves the metadata of a locale
 * @method static \PKP\i18n\LocaleMetadata[] getLocales() Retrieves a list of available locales with their metadata
 * @method static void installLocale(string $locale) Install support for a new locale.
 * @method static void uninstallLocale(string $locale) Uninstall support for an existing locale.
 * @method static bool isSupported(string $locale) Retrieves whether the given locale is in the list of supported locales
 * @method static array getSupportedFormLocales() Deprecated on 3.4.0, use Context::getSupportedFormLocales()
 * @method static array getSupportedLocales() Deprecated 3.4.0, use Context::getSupportedLocales()
 * @method static void setMissingKeyHandler(callable $handler) Sets the handler to format missing locale keys
 * @method static callable getMissingKeyHandler() Retrieves the handler to format missing locale keys
 * @method static \PKP\i18n\translation\LocaleBundle getBundle(?string $locale = null, bool $useCache = true) Retrieves a locale bundle to translate texts.
 * @method static string getDefaultLocale() Retrieves the default locale
 * @method static \Sokil\IsoCodes\Database\Countries getCountries(?string $locale = null) Retrieve the countries
 * @method static \Sokil\IsoCodes\Database\Currencies getCurrencies(?string $locale = null) Retrieve the currencies
 * @method static \Sokil\IsoCodes\Database\LanguagesInterface getLanguages(?string $locale = null) Retrieve the languages
 * @method static \Sokil\IsoCodes\Database\Scripts getScripts(?string $locale = null) Retrieve the scripts
 * @method static array getFormattedDisplayNames(array $filterByLocales = null, array $locales = null, int $langLocaleStatus = LocaleMetadata::LANGUAGE_LOCALE_WITH) Get the formatted locale display names with country if same language code present multiple times
 * @method static array getFormattedDisplayNamesFromOnlySpecifiedLocales(array $filterByLocales, array $locales = null, int $langLocaleStatus = LocaleMetadata::LANGUAGE_LOCALE_WITH) Get the formatted locale display names only for give specific list of locales
 */

class Locale extends Facade
{
    /**
     * Connects the facade to a container component
     */
    protected static function getFacadeAccessor(): string
    {
        return LocaleInterface::class;
    }
}

<?php
declare(strict_types = 1);

/**
 * @defgroup i18n I18N
 * Implements localization concerns such as locale files, time zones, and country lists.
 */

/**
 * @file classes/i18n/interfaces/LocaleInterface.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class LocaleInterface
 * @ingroup i18n
 *
 * @brief Provides methods for loading gettext locale files and translating texts
 */

namespace PKP\i18n\interfaces;

use PKP\i18n\LocaleMetadata;
use PKP\i18n\translation\LocaleBundle;
use Sokil\IsoCodes\Database\Countries;
use Sokil\IsoCodes\Database\Currencies;
use Sokil\IsoCodes\Database\LanguagesInterface;
use Sokil\IsoCodes\Database\Scripts;

interface LocaleInterface extends \Illuminate\Contracts\Translation\Translator
{
    /** Keeps the default locale of the application */
    public const DEFAULT_LOCALE = 'en_US';

    /** Regular expression to validate and extract pieces of a locale, variants can be composed of five to eight letters, or of four characters starting with a digit */
    public const LOCALE_EXPRESSION = '/^(?P<language>[a-z]{2})(?:_(?P<country>[A-Z]{2}))?(?:@(?P<script>[A-Za-z\d]{5,8}|\d[A-Za-z\d]{3}))?$/';

    /**
     * Attempts to retrieve the primary locale for the current context, if not available, then for the site.
     */
    public function getPrimaryLocale(): string;

    /**
     * Register a locale folder
     * @param string $path The given folder is expected to have sub-folders, each one representing a locale (e.g. "./en_US").
     * The application will then look for .po files and attempt to lazy load them when requested.
     * @param int $priority The priority controls which locale should be loaded first, higher priorities overwrite smaller ones (in case of locale key conflicts), the default is 0
     */
    public function registerPath(string $path, int $priority = 0): void;

    /**
     * Register a locale file loader
     * @param callable $fileLoader Receives two arguments.
     * string $locale The locale string
     * array $localeFiles An array (key = file path, value = the loading priority) with the locale files to be loaded.
     * The second argument might be received as a reference (&) in order to update the locales.
     * The $fileLoader will be invoked once when loading a locale.
     * @param int $priority Defines the calling priority, higher values will be called later, the default is 0
     */
    public function registerLoader(callable $fileLoader, int $priority = 0): void;

    /**
     * Check if the supplied locale is valid.
     */
    public function isLocaleValid(?string $locale): bool;

    /**
     * Retrieves the metadata of a locale
     */
    public function getMetadata(string $locale): ?LocaleMetadata;

    /**
     * Retrieves a list of available locales with their metadata

     * @return LocaleMetadata[]
     */
    public function getLocales(): array;

    /**
     * Install support for a new locale.
     */
    public function installLocale(string $locale): void;

    /**
     * Uninstall support for an existing locale.
     */
    public function uninstallLocale(string $locale): void;

    /**
     * Retrieves whether the given locale is in the list of supported locales
     */
    public function isSupported(string $locale): bool;

    /**
     * Get all supported form locales for the current context (if not available, then from the site).
     * @return string[]
     */
    public function getSupportedFormLocales(): array;

    /**
     * Get all supported locales for the current context (if not available, then from the site).
     * @return string[]
     */
    public function getSupportedLocales(): array;

    /**
     * Sets the handler to format missing locale keys
     */
    public function setMissingKeyHandler(?callable $handler): void;

    /**
     * Retrieves the handler to format missing locale keys
     */
    public function getMissingKeyHandler(): ?callable;

    /**
     * Retrieves a locale bundle to translate texts.
     * 
     * @return LocaleBundle
     */
    public function getBundle(?string $locale = null, bool $useCache = true): LocaleBundle;

    /**
     * Retrieves the default locale
     */
    public function getDefaultLocale(): string;

    /**
     * Retrieve the countries
     */
    public function getCountries(?string $locale = null): Countries;

    /**
     * Retrieve the currencies
     */
    public function getCurrencies(?string $locale = null): Currencies;

    /**
     * Retrieve the languages
     */
    public function getLanguages(?string $locale = null): LanguagesInterface;

    /**
     * Retrieve the scripts
     */
    public function getScripts(?string $locale = null): Scripts;
}

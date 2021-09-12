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

interface LocaleInterface extends \Illuminate\Contracts\Translation\Translator
{
    /**
     * Retrieves the default encoding
     */
    public function getDefaultEncoding(): ?string;

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
    public function registerFolder(string $path, int $priority = 0): void;

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
    public function getLocaleMetadata(string $locale): ?LocaleMetadata;

    /**
     * Retrieves a list of available locales with their metadata

     * @return LocaleMetadata[]
     */
    public function getLocales(): array;

    /**
     * Return a list of all available locales.

     * @return string[]
     */
    public function getAllLocales(): array;

    /**
     * Install support for a new locale.
     */
    public function installLocale(string $locale): void;

    /**
     * Uninstall support for an existing locale.
     */
    public function uninstallLocale(string $locale): void;

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
    public function getBundle(string $locale): LocaleBundle;

    /**
     * Retrieves the default locale
     */
    public function getDefaultLocale(): string;
}

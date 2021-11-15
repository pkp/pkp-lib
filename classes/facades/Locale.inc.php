<?php

/**
 * @file classes/facades/Locale.inc.php
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
 * @method static mixed get(string $key, array $replace = [], $locale = null)
 * @method static string choice(string $key, int $number, array $replace = [], string $locale = null)
 * @method static string getLocale()
 * @method static void setLocale(string $locale)
 * @method static string getPrimaryLocale()
 * @method static void registerFolder(string $path, int $priority = 0)
 * @method static void registerLoader(callable $fileLoader, int $priority = 0)
 * @method static bool isLocaleValid(string $locale)
 * @method static \PKP\i18n\LocaleMetadata getLocaleMetadata(string $locale)
 * @method static \PKP\i18n\LocaleMetadata[] getLocales()
 * @method static void installLocale(string $locale)
 * @method static void uninstallLocale(string $locale)
 * @method static array getSupportedFormLocales()
 * @method static array getSupportedLocales()
 * @method static void setMissingKeyHandler(callable $handler)
 * @method static callable getMissingKeyHandler()
 * @method static \PKP\i18n\translation\LocaleBundle getBundle(string $locale)
 * @method static string getDefaultLocale()
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

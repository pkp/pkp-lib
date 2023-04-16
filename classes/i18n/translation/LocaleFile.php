<?php

declare(strict_types=1);

/**
 * @defgroup i18n I18N
 * Implements localization concerns such as locale files, time zones, and country lists.
 */

/**
 * @file classes/i18n/translation/LocaleFile.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class LocaleFile
 *
 * @ingroup i18n
 *
 * @brief Loads translations from a locale file
 */

namespace PKP\i18n\translation;

use DateInterval;
use Exception;
use Gettext\Generator\ArrayGenerator;
use Gettext\Loader\LoaderInterface;
use Gettext\Loader\MoLoader;
use Gettext\Loader\PoLoader;
use Gettext\Translations;
use Illuminate\Support\Facades\Cache;
use SplFileInfo;

abstract class LocaleFile
{
    /** @var string Max lifetime for the cache, a new cache file is created whenever the translation file is modified */
    protected const MAX_CACHE_LIFETIME = '1 year';

    /**
     * Retrieves a suitable loader
     */
    public static function getLoader(string $path): LoaderInterface
    {
        return match (strtolower((new SplFileInfo($path))->getExtension())) {
            'po' => new PoLoader(),
            'mo' => new MoLoader(),
            default => throw new Exception("There's no suitable gettext loader for this file type")
        };
    }

    /**
     * Loads the translations from a file
    */
    public static function loadTranslations(string $path): Translations
    {
        return self::getLoader($path)->loadFile($path);
    }

    /**
     * Loads the translations from a file as an array and caches the content physically as a PHP file in order to use the opcache
    */
    public static function loadArray(string $path, bool $useCache = false): array
    {
        $loader = fn () => (new ArrayGenerator(['includeEmpty' => false]))->generateArray(static::loadTranslations($path));
        $key = __METHOD__ . static::MAX_CACHE_LIFETIME . $path . filemtime($path);
        return $useCache ? Cache::remember($key, DateInterval::createFromDateString(static::MAX_CACHE_LIFETIME), $loader) : $loader();
    }
}

<?php
declare(strict_types = 1);

/**
 * @defgroup i18n I18N
 * Implements localization concerns such as locale files, time zones, and country lists.
 */

/**
 * @file classes/i18n/translation/LocaleFile.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class LocaleFile
 * @ingroup i18n
 *
 * @brief Loads translations from a locale file
 */

namespace PKP\i18n\translation;

use DateInterval;
use Gettext\Generator\ArrayGenerator;
use Gettext\Loader\PoLoader;
use Gettext\Translations;
use Illuminate\Support\Facades\Cache;

abstract class LocaleFile
{
    /** @var string Max lifetime for the cache, a new cache file is created whenever the translation file is modified */
    protected const MAX_CACHE_LIFETIME = '1 year';

    /**
     * Loads the translations from a file
    */
    public static function loadTranslations(string $path): Translations
    {
        $loader = new PoLoader();
        return $loader->loadFile($path);
    }

    /**
     * Loads the translations from a file as an array
    */
    public static function loadArray(string $path): array
    {
        $key = self::_getCacheKey($path);
        $cache = Cache::get($key);
        if (!is_array($cache)) {
            $arrayGenerator = new ArrayGenerator();
            $cache = $arrayGenerator->generateArray(static::loadTranslations($path));
            Cache::put($key, $cache, DateInterval::createFromDateString(static::MAX_CACHE_LIFETIME));
        }
        return $cache;
    }

    /**
     * Retrieves the cache key
     */
    private static function _getCacheKey(string $path): string
    {
        return static::class . '.' . sha1($path . filemtime($path));
    }
}

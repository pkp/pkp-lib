<?php
declare(strict_types = 1);

/**
 * @file classes/i18n/translation/LocaleBundle.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class LocaleBundle
 * @ingroup i18n
 *
 * @brief Bundles several locale files for a given locale into a single object
 */

namespace PKP\i18n\translation;

use DateInterval;
use Illuminate\Support\Facades\Cache;
use PKP\facades\Locale;

class LocaleBundle
{
    /** @var string The locale assigned to this bundle */
    public $locale;

    /** @var string Max lifetime for the bundle cache. A new cache is created anytime a locale file in the bundle is modified */
    protected const MAX_CACHE_LIFETIME = '1 year';

    /** @var int[] Keeps the locale filenames (key) and their loading priorities (value) */
    protected $paths;

    /** @var Translator Keeps the translations, lazy initialized when a translation is requested */
    protected $translator;

    /**
     * Constructor.
     *
     * @param string $locale Locale assigned to this locale bundle
     * @param int[] $paths Optional list of gettext files to load, where the key must contain the locale path and the value its priority
     */
    public function __construct(string $locale, ?array $paths = null)
    {
        $this->locale = $locale;
        $this->paths = $paths ?? [];
        asort($this->paths);
    }

    /**
     * Translate a string using the selected locale.
     * Substitution works by replacing tokens like "{$foo}" with the value of
     * the parameter named "foo" (if supplied).
     *
     * @param string $key Locale key
     * @param array $params Named substitution parameters
     *
     * @return ?string
     */
    public function translateSingular(string $key, array $params = []): ?string
    {
        $this->_prepare();
        $message = $this->translator->getSingular($key);
        return strlen($message) ? $this->_format($message, $params) : null;
    }

    /**
     * Translate a string using the selected locale with support for plurals.
     * Substitution works by replacing tokens like "{$foo}" with the value of
     * the parameter named "foo" (if supplied).
     *
     * @param string $key Locale key
     * @param int $count Count of items
     * @param array $params Named substitution parameters
     *
     * @return string
     */
    public function translatePlural(string $key, int $count, array $params = []): ?string
    {
        $this->_prepare();
        $message = $this->translator->getPlural($key, $count);
        return strlen($message) ? $this->_format($message, $params) : null;
    }

    
    /**
     * Adds a new locale to the bundle
    */
    public function addPath(string $path, int $priority = 0): void
    {
        if (($this->paths[$path] ?? null) === $priority) {
            return;
        }

        asort($this->paths);
        // Clears the cache
        $this->translator = null;
    }

    /**
     * Formats the translation
     */
    private function _format(string $message, array $params = []) {
        // Replace custom parameters
        if (count($params)) {
            $message = str_replace(
                array_map(
                    function (string $search): string {
                        return "{\$${search}}";
                    },
                    array_keys($params)
                ),
                array_values($params),
                $message
            );
        }

        return strtolower(Locale::getDefaultEncoding()) == 'iso-8859-1'
            // If the client encoding is set to iso-8859-1, transcode string from utf8 since we store all XML files in utf8
            ? utf8_decode($message)
            : $message;
    }

    /**
     * Lazily prepares the class to retrieve translations
     */
    private function _prepare(): void
    {
        // Quit if it's already initialized
        if ($this->translator) {
            return;
        }

        $key = $this->_getCacheKey();
        $cache = Cache::get($key);
        // Attempts to load from the cache
        if (!($cache instanceof Translator)) {
            $cache = new Translator();
            foreach (array_keys($this->paths) as $path) {
                // Merge all the locale files into a single structure
                $cache->addTranslations(LocaleFile::loadArray($path));
            }
            // Store for a limited amount of time, given that cache invalidations will not attempt to clean old data
            Cache::put($this->_getCacheKey(), $cache, DateInterval::createFromDateString(static::MAX_CACHE_LIFETIME));
        }
        $this->translator = $cache;
    }

    /**
     * Retrieves a cache key based on the path and modification date of all locale files
     */
    private function _getCacheKey(): string
    {
        $key = array_reduce(
            array_keys($this->paths),
            function (string $hash, string $path): string {
                return sha1($hash . $path . filemtime($path));
            },
            ''
        );

        return static::class . '.' . $key;
    }

    /**
     * Retrieves the locale paths (keys) that are part of this bundle together with their priorities (values)
     * 
     * @return int[]
     */
    public function getEntries(): array
    {
        return $this->paths;
    }
}

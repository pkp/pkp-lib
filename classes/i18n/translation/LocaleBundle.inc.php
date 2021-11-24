<?php

declare(strict_types=1);

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
    /** @var string Max lifetime for the bundle cache. A new cache is created anytime a locale file in the bundle is modified */
    protected const MAX_CACHE_LIFETIME = '1 year';

    /** The locale assigned to this bundle */
    public string $locale;

    /** @var int[] Keeps the locale filenames (key) and their loading priorities (value) */
    protected array $paths = [];

    /** Keeps the translations, lazy initialized when a translation is requested */
    protected ?Translator $translator = null;

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
        $message = $this->getTranslator()->getSingular($key);
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
        $message = $this->getTranslator()->getPlural($key, $count);
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
     * Retrieves the locale paths (keys) that are part of this bundle together with their priorities (values)
     *
     * @return int[]
     */
    public function getEntries(): array
    {
        return $this->paths;
    }

    /**
     * Lazily build and retrieves the Translator instance
     */
    public function getTranslator(): Translator
    {
        // Caches only the supported locales (avoid spending time with one-offs)
        $isSupported = Locale::isSupported($this->locale);
        $loader = function () use ($isSupported): Translator {
            $translator = new Translator();
            // Merge all the locale files into a single structure
            array_walk($this->paths, fn (int $_, string $path) => $translator->addTranslations(LocaleFile::loadArray($path, $isSupported)));
            return $translator;
        };
        return $this->translator ??= $isSupported
            ? Cache::remember($this->_getCacheKey(), DateInterval::createFromDateString(static::MAX_CACHE_LIFETIME), $loader)
            : $loader();
    }

    /**
     * Retrieves a cache key based on the path and modification date of all locale files
     */
    private function _getCacheKey(): string
    {
        $key = array_reduce(array_keys($this->paths), fn(string $hash, string $path): string => sha1($hash . $path . filemtime($path)), '');
        return __METHOD__ . static::MAX_CACHE_LIFETIME . ".${key}";
    }

    /**
     * Formats the translation
     */
    private function _format(string $message, array $params = [])
    {
        return count($params) ? str_replace(array_map(fn(string $search): string => "{\$${search}}", array_keys($params)), array_values($params), $message) : $message;
    }
}

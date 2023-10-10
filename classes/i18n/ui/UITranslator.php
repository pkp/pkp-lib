<?php

/**
 * @file classes/i18n/translation/ui/UITranslator.php
 *
 * Copyright (c) 2023 Simon Fraser University
 * Copyright (c) 2023 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class UITranslator
 *
 * @ingroup i18n
 *
 * @brief Provides translation strings for all keys used in UI (vue.js)
 */

namespace PKP\i18n\ui;

use DateInterval;
use Illuminate\Support\Facades\Cache;
use PKP\facades\Locale;

class UITranslator
{
    /** @var string Max lifetime for the cache. A new cache is created anytime a locale file in the bundle or json file with key list is modified */
    protected const MAX_CACHE_LIFETIME = '1 year';

    /** The locale assigned to this bundle */
    protected string $locale;

    /** Paths that has been registered with Locale.php */
    protected array $localePaths;


    /** Last chache key used in LocaleBundle, used for caching translations to ensure that any po file change gets reflected */
    protected string $localeBundleCacheKey;

    /**
     * Constructor.
     *
     * @param string $locale Locale assigned to this locale bundle
     */
    public function __construct(string $locale, array $localePaths, string $localeBundleCacheKey)
    {
        $this->locale = $locale;
        $this->localePaths = $localePaths;
        $this->localeBundleCacheKey = $localeBundleCacheKey;
    }

    /**
     * Getting all translation strings needed in Vue.js UI
     *
     *
     * @return  array Key are translation keys and values are translation strings
     */
    public function getTranslationStrings(): array
    {

        $filePaths = $this->getJsonFilePaths();

        $key = $this->getCacheKey();
        $expiration = DateInterval::createFromDateString(static::MAX_CACHE_LIFETIME);
        return Cache::remember($key, $expiration, function () use ($filePaths) {
            $translations = [];

            // iterate over paths looking for json
            foreach ($filePaths as $filePath) {
                $keysJsonData = file_get_contents($filePath);
                $keysArray = json_decode($keysJsonData, true);
                foreach ($keysArray as $key) {
                    $translations[$key] ??= Locale::get($key);
                }
            }

            return $translations;
        });
    }

    /**
     * Used in TemplateManager as unique hash, to ensure browser re-fetch translations if po/json files changes
     *
     *
     * @return  string hash key for frontend
     */

    public function getCacheHash(): string
    {
        $key = $this->getCacheKey();

        return sha1($key);
    }

    /**
     * Creating accurate cache key to ensure it invalidates if any of po/json files changes
     *
     *
     * @return  string cache key
     */

    private function getCacheKey(): string
    {
        $filePaths = $this->getJsonFilePaths();

        return static::MAX_CACHE_LIFETIME . $this->locale . $this->localeBundleCacheKey . array_reduce($filePaths, fn (string $hash, string $path): string => sha1($hash . $path . filemtime($path)), '');
    }

    /**
     * Helper function that provides array of existing file paths to uiLocaleKeysBackend.json.
     *
     *
     * @return  array Values are all paths to existing uiLocaleKeysBackend.json files, including plugins.
     */
    private function getJsonFilePaths(): array
    {
        $filePaths = [];

        foreach (array_keys($this->localePaths) as $folder) {
            $parentDir = dirname($folder);
            $filePath = $parentDir . '/' . 'registry' . '/uiLocaleKeysBackend.json' ;
            if (file_exists($filePath)) {
                $filePaths[] = $filePath;
            }
        }

        return $filePaths;
    }


}

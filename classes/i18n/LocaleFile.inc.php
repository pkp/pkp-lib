<?php

/**
 * @file classes/i18n/LocaleFile.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class LocaleFile
 * @ingroup i18n
 *
 * @brief Abstraction of a locale file
 */

namespace PKP\i18n;

use PKP\cache\CacheManager;

class LocaleFile
{
    /** @var object Cache of this locale file */
    public $cache;

    /** @var string The identifier for this locale file */
    public $locale;

    /** @var string The filename for this locale file */
    public $filename;

    /**
     * Constructor.
     *
     * @param string $locale Key for this locale file
     * @param string $filename Filename to this locale file
     */
    public function __construct($locale, $filename)
    {
        $this->locale = $locale;
        $this->filename = $filename;
    }

    /**
     * Get the cache object for this locale file.
     */
    public function _getCache($locale)
    {
        if (!isset($this->cache)) {
            $cacheManager = CacheManager::getManager();
            $this->cache = $cacheManager->getFileCache(
                'locale',
                md5($this->filename),
                [$this, '_cacheMiss']
            );

            // Check to see if the cache is outdated.
            // Only some kinds of caches track cache dates;
            // if there's no date available (ie cachedate is
            // null), we have to assume it's up to date.
            $cacheTime = $this->cache->getCacheTime();
            if ($cacheTime === null || $cacheTime < filemtime($this->filename)) {
                // This cache is out of date; flush it.
                $this->cache->setEntireCache(self::load($this->filename));
            }
        }
        return $this->cache;
    }

    /**
     * Register a cache miss.
     */
    public function _cacheMiss($cache, $id)
    {
        return null; // It's not in this locale file.
    }

    /**
     * Get the filename for this locale file.
     */
    public function getFilename()
    {
        return $this->filename;
    }

    /**
     * Translate a string using the selected locale.
     * Substitution works by replacing tokens like "{$foo}" with the value of
     * the parameter named "foo" (if supplied).
     *
     * @param string $key
     * @param array $params named substitution parameters
     * @param string $locale the locale to use
     *
     * @return string
     */
    public function translate($key, $params = [], $locale = null)
    {
        if ($this->isValid()) {
            $key = trim($key);
            if (empty($key)) {
                return '';
            }

            $cache = $this->_getCache($this->locale);
            $message = $cache->get($key);
            if (!isset($message)) {
                // Try to force loading the plugin locales.
                $message = $this->_cacheMiss($cache, $key);
            }

            if (isset($message)) {
                if (!empty($params)) {
                    // Substitute custom parameters
                    foreach ($params as $key => $value) {
                        $message = str_replace("{\$${key}}", (string) $value, $message);
                    }
                }

                // if client encoding is set to iso-8859-1, transcode string from utf8 since we store all XML files in utf8
                if (LOCALE_ENCODING == 'iso-8859-1') {
                    $message = utf8_decode($message);
                }

                return $message;
            }
        }
        return null;
    }

    /**
     * Static method: Load a locale array from a file. Not cached!
     *
     * @param string $filename Filename to locale .po file to load
     *
     * @return array
     */
    public static function &load($filename)
    {
        $localeData = [];
        $loader = new \Gettext\Loader\PoLoader();
        foreach ($loader->loadFile($filename) as $translation) {
            $localeData[$translation->getOriginal()] = $translation->getTranslation();
        }
        return $localeData;
    }

    /**
     * Check if a locale is valid.
     *
     * @return bool
     */
    public function isValid()
    {
        return isset($this->locale) && file_exists($this->filename);
    }

    /**
     * Test a locale file against the given reference locale file and
     * return an array of errorType => array(errors).
     *
     * @param object $referenceLocaleFile
     *
     * @return array
     */
    public function testLocale(&$referenceLocaleFile)
    {
        $errors = [
            LOCALE_ERROR_MISSING_KEY => [],
            LOCALE_ERROR_EXTRA_KEY => [],
            LOCALE_ERROR_DIFFERING_PARAMS => [],
            LOCALE_ERROR_MISSING_FILE => []
        ];

        if ($referenceLocaleFile->isValid()) {
            if (!$this->isValid()) {
                $errors[LOCALE_ERROR_MISSING_FILE][] = [
                    'locale' => $this->locale,
                    'filename' => $this->filename
                ];
                return $errors;
            }
        } else {
            // If the reference file itself does not exist or is invalid then
            // there's nothing to be translated here.
            return $errors;
        }

        $localeContents = self::load($this->filename);
        $referenceContents = self::load($referenceLocaleFile->filename);

        foreach ($referenceContents as $key => $referenceValue) {
            if (!isset($localeContents[$key])) {
                $errors[LOCALE_ERROR_MISSING_KEY][] = [
                    'key' => $key,
                    'locale' => $this->locale,
                    'filename' => $this->filename,
                    'reference' => $referenceValue
                ];
                continue;
            }
            $value = $localeContents[$key];

            $referenceParams = AppLocale::getParameterNames($referenceValue);
            $params = AppLocale::getParameterNames($value);
            if (count(array_diff($referenceParams, $params)) > 0) {
                $errors[LOCALE_ERROR_DIFFERING_PARAMS][] = [
                    'key' => $key,
                    'locale' => $this->locale,
                    'mismatch' => array_diff($referenceParams, $params),
                    'filename' => $this->filename,
                    'reference' => $referenceValue,
                    'value' => $value
                ];
            }
            // After processing a key, remove it from the list;
            // this way, the remainder at the end of the loop
            // will be extra unnecessary keys.
            unset($localeContents[$key]);
        }

        // Leftover keys are extraneous.
        foreach ($localeContents as $key => $value) {
            $errors[LOCALE_ERROR_EXTRA_KEY][] = [
                'key' => $key,
                'locale' => $this->locale,
                'filename' => $this->filename
            ];
        }

        return $errors;
    }
}

if (!PKP_STRICT_MODE) {
    class_alias('\PKP\i18n\LocaleFile', '\LocaleFile');
}

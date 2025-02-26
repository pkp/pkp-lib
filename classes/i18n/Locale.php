<?php

declare(strict_types=1);

/**
 * @defgroup i18n I18N
 * Implements localization concerns such as locale files, languages, currencies, and country lists.
 */

/**
 * @file classes/i18n/Locale.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class Locale
 *
 * @ingroup i18n
 *
 * @brief Provides methods for loading locale data and translating strings identified by unique keys
 */

namespace PKP\i18n;

use Closure;
use DateInterval;
use DirectoryIterator;
use Exception;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use InvalidArgumentException;
use PKP\config\Config;
use PKP\core\Core;
use PKP\core\PKPRequest;
use PKP\core\PKPSessionGuard;
use PKP\facades\Repo;
use PKP\i18n\interfaces\LocaleInterface;
use PKP\i18n\translation\LocaleBundle;
use PKP\i18n\ui\UITranslator;
use PKP\plugins\Hook;
use PKP\plugins\PluginRegistry;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RecursiveRegexIterator;
use RegexIterator;
use Sokil\IsoCodes\Database\Countries;
use Sokil\IsoCodes\Database\Currencies;
use Sokil\IsoCodes\Database\LanguagesInterface;
use Sokil\IsoCodes\Database\Scripts;
use Sokil\IsoCodes\IsoCodesFactory;
use SplFileInfo;

class Locale implements LocaleInterface
{
    /** Max lifetime for the locale metadata cache, the cache is built by scanning the provided paths */
    protected const MAX_CACHE_LIFETIME = '1 hour';

    /** @var string Max lifetime for the submission locales cache. */
    protected const MAX_WEBLATE_LOCALES_CACHE_LIFETIME = '1 year';

    /**
     * @var callable Formatter for missing locale keys
     * Receives the locale key and must return a string
     */
    protected ?Closure $missingKeyHandler = null;

    /** Current locale cache */
    protected ?string $locale = null;

    /** @var int[] Folders where locales can be found, where key = path and value = loading priority */
    protected array $paths = [];

    /** @var callable[] Custom locale loaders */
    protected array $loaders = [];

    /** Keeps the request */
    protected ?PKPRequest $request = null;

    /** @var LocaleMetadata[]|null Discovered locales cache */
    protected ?array $locales = null;

    /** Primary locale cache */
    protected ?string $primaryLocale = null;

    /** @var string[]|null Supported form locales cache, where key = locale and value = name */
    protected ?array $supportedFormLocaleNames = null;

    /** @var string[]|null Supported locales cache, where key = locale and value = name */
    protected ?array $supportedLocaleNames = null;

    /** @var string[]|null Supported locales cache */
    protected ?array $supportedLocales = null;

    /** @var LocaleBundle[] Keeps a cache for the locale bundles */
    protected array $localeBundles = [];

    /** @var string[][][]|null Discovered locale files, keyed first by base path and then by locale */
    protected array $localeFiles = [];

    /** Keeps cached data related only to the current locale */
    protected array $cache = [];

    /** @var string[]|null Available weblate locales cache, where key = locale and value = weblate name */
    protected ?array $weblateLocaleNames = null;

    /**
     * @copy \Illuminate\Contracts\Translation\Translator::get()
     *
     * @param null|mixed $locale
     */
    public function get($key, array $params = [], $locale = null): string
    {
        return $this->translate($key, null, $params, $locale);
    }

    /**
     * @copy \Illuminate\Contracts\Translation\Translator::choice()
     *
     * @param null|mixed $locale
     */
    public function choice($key, $number, array $params = [], $locale = null): string
    {
        return $this->translate($key, $number, $params, $locale);
    }

    /**
     * @copy \Illuminate\Contracts\Translation\Translator::getLocale()
     */
    public function getLocale(): string
    {
        if (isset($this->locale)) {
            return $this->locale;
        }
        $request = $this->_getRequest();
        $locale = $request->getUserVar('setLocale')
            ?: $request->getSession()->get('currentLocale')
            ?: $request->getCookieVar('currentLocale')
            ?: $this->getPreferredLocale();
        $this->setLocale($locale);
        return $this->locale;
    }

    /**
     * @copy \Illuminate\Contracts\Translation\Translator::setLocale()
     */
    public function setLocale($locale): void
    {
        if (!$this->isLocaleValid($locale) || !$this->isSupported($locale)) {
            if ($locale) {
                error_log((string) new InvalidArgumentException("Invalid/unsupported locale \"{$locale}\", default locale restored"));
            }
            $locale = $this->getPrimaryLocale();
        }

        $this->locale = $locale;
        setlocale(LC_ALL, 'C.utf8', 'C');
        $locales = array_keys($this->getWeblateLocaleNames());
        \Locale::setDefault(\Locale::lookup($locales, $locale, true));
    }

    /**
     * @copy LocaleInterface::getPrimaryLocale()
     */
    public function getPrimaryLocale(): string
    {
        if (isset($this->primaryLocale)) {
            return $this->primaryLocale;
        }
        $request = $this->_getRequest();
        $locale = PKPSessionGuard::isSessionDisable() ? null : $request->getContext()?->getPrimaryLocale() ?? $request->getSite()?->getPrimaryLocale();
        return $this->primaryLocale = $this->isLocaleValid($locale) ? $locale : $this->getDefaultLocale();
    }

    /**
     * @copy LocaleInterface::registerPath()
     */
    public function registerPath(string $path, int $priority = 0): void
    {
        $path = new SplFileInfo($path);
        if (!$path->isDir()) {
            throw new InvalidArgumentException("\"{$path}\" isn't a valid folder");
        }

        // Invalidate the loaded bundles cache
        $realPath = $path->getRealPath();
        if (($this->paths[$realPath] ?? null) !== $priority) {
            $this->paths[$realPath] = $priority;
            $this->localeBundles = [];
            $this->locales = null;
        }
    }

    /**
     * @copy LocaleInterface::registerLoader()
     */
    public function registerLoader(callable $fileLoader, int $priority = 0): void
    {
        // Invalidate the loaded bundles cache
        if (array_search($fileLoader, $this->loaders[$priority] ?? [], true) === false) {
            $this->loaders[$priority][] = $fileLoader;
            $this->localeBundles = [];
            ksort($this->loaders, SORT_NUMERIC);
        }
    }

    /**
     * @copy LocaleInterface::isLocaleValid()
     */
    public function isLocaleValid(?string $locale): bool
    {
        $locales = $this->getWeblateLocaleNames();
        return !empty($locale) && array_key_exists($locale, $locales);
    }

    /**
     * @copy LocaleInterface::getMetadata()
     */
    public function getMetadata(string $locale): ?LocaleMetadata
    {
        return $this->getLocales()[$locale] ?? null;
    }

    /**
     * @copy LocaleInterface::getLocales()
     */
    public function getLocales(): array
    {
        $key = __METHOD__ . static::MAX_CACHE_LIFETIME . array_reduce(
            array_keys($this->paths),
            fn (string $hash, string $path): string => sha1($hash . $path),
            ''
        );
        $expiration = DateInterval::createFromDateString(static::MAX_CACHE_LIFETIME);
        return $this->locales ??= Cache::remember($key, $expiration, function () {
            $locales = [];
            foreach (array_keys($this->paths) as $folder) {
                foreach (new DirectoryIterator($folder) as $cursor) {
                    if ($cursor->isDir() && $this->isLocaleValid($cursor->getBasename())) {
                        $locales[$cursor->getBasename()] ??= new LocaleMetadata($cursor->getBasename());
                    }
                }
            }
            ksort($locales);
            return $locales;
        });
    }

    /**
     * @copy LocaleInterface::installLocale()
     *
     * @hook Locale::installLocale [[&$locale]]
     */
    public function installLocale(string $locale): void
    {
        Repo::emailTemplate()->dao->installEmailTemplateLocaleData(Repo::emailTemplate()->dao->getMainEmailTemplatesFilename(), [$locale]);
        // Load all plugins so they can add locale data if needed
        PluginRegistry::loadAllPlugins();
        Hook::call('Locale::installLocale', [&$locale]);
    }

    /**
     * @copy LocaleInterface::uninstallLocale()
     */
    public function uninstallLocale(string $locale): void
    {
        // Delete locale-specific data
        Repo::emailTemplate()->dao->deleteEmailTemplatesByLocale($locale);
    }

    /**
     * Retrieves whether the given locale is supported
     */
    public function isSupported(string $locale): bool
    {
        return isset($this->_getSupportedLocales()[$locale]);
    }

    /**
     * @copy LocaleInterface::getSupportedFormLocales()
     */
    public function getSupportedFormLocales(): array
    {
        return $this->supportedFormLocaleNames ??= (PKPSessionGuard::isSessionDisable() ? null : $this->_getRequest()->getContext()?->getSupportedFormLocaleNames())
            ?? $this->getSupportedLocales();
    }

    /**
     * @copy LocaleInterface::getSupportedLocales()
     */
    public function getSupportedLocales(): array
    {
        return $this->supportedLocaleNames ??= array_map(fn (string $locale) => $this->getMetadata($locale)?->getDisplayName() ?? $locale, $this->_getSupportedLocales());
    }

    /**
     * @copy LocaleInterface::setMissingKeyHandler()
     */
    public function setMissingKeyHandler(?callable $handler): void
    {
        $this->missingKeyHandler = $handler;
    }

    /**
     * @copy LocaleInterface::getMissingKeyHandler()
     */
    public function getMissingKeyHandler(): ?callable
    {
        return $this->missingKeyHandler;
    }

    /**
     * @copy LocaleInterface::getBundle()
     */
    public function getBundle(?string $locale = null, bool $useCache = true): LocaleBundle
    {
        $locale ??= $this->getLocale();
        $getter = function () use ($locale): LocaleBundle {
            $bundle = [];
            foreach ($this->paths as $folder => $priority) {
                $bundle += $this->_getLocaleFiles((string)$folder, $locale, $priority);
            }
            foreach ($this->loaders as $loader) {
                $loader($locale, $bundle);
            }
            return new LocaleBundle($locale, $bundle);
        };
        return $useCache ? $this->localeBundles[$locale] ??= $getter() : $getter();
    }

    /**
     * @copy LocaleInterface::getDefaultLocale()
     */
    public function getDefaultLocale(): string
    {
        return Config::getVar('i18n', 'locale');
    }

    /**
     * @copy LocaleInterface::getCountries()
     */
    public function getCountries(?string $locale = null): Countries
    {
        return $this->_getLocaleCache(__METHOD__, $locale, fn () => $this->_getIsoCodes($locale)->getCountries());
    }

    /**
     * @copy LocaleInterface::getCurrencies()
     */
    public function getCurrencies(?string $locale = null): Currencies
    {
        return $this->_getLocaleCache(__METHOD__, $locale, fn () => $this->_getIsoCodes($locale)->getCurrencies());
    }

    /**
     * @copy LocaleInterface::getLanguages()
     */
    public function getLanguages(?string $locale = null, bool $fromCache = true): LanguagesInterface
    {
        if ($fromCache) {
            return $this->_getLocaleCache(
                __METHOD__,
                $locale,
                fn () => $this->_getIsoCodes($locale)->getLanguages()
            );
        }

        return $this->_getIsoCodes($locale)->getLanguages();
    }

    /**
     * @copy LocaleInterface::getScripts()
     */
    public function getScripts(?string $locale = null): Scripts
    {
        return $this->_getLocaleCache(__METHOD__, $locale, fn () => $this->_getIsoCodes($locale)->getScripts());
    }

    /**
     * @copy LocaleInterface::getFormattedDisplayNames()
     */
    public function getFormattedDisplayNames(?array $filterByLocales = null, ?array $locales = null, int $langLocaleStatus = LocaleMetadata::LANGUAGE_LOCALE_WITH, bool $omitLocaleCodeInDisplay = true): array
    {
        $locales ??= $this->getLocales();

        if ($filterByLocales !== null) {
            $locales = array_intersect_key($locales, array_flip($filterByLocales));
            $filterByLocales = array_keys($locales);
        }

        $localeCodesCount = array_count_values(
            collect($filterByLocales ?? array_keys($locales))
                ->map(fn (string $value) => trim(explode('@', explode('_', $value)[0])[0]))
                ->toArray()
        );

        return collect($locales)
            ->map(function (LocaleMetadata $locale, string $localeKey) use ($localeCodesCount, $langLocaleStatus, $omitLocaleCodeInDisplay) {
                $localeCode = trim(explode('@', explode('_', $localeKey)[0])[0]);
                $localeDisplay = $locale->getDisplayName(null, ($localeCodesCount[$localeCode] ?? 0) > 1, $langLocaleStatus);
                return $localeDisplay . ($omitLocaleCodeInDisplay ? '' : " ({$localeKey})");
            })
            ->toArray();
    }

    /**
     * @copy LocaleInterface::getUiTranslator()
    */
    public function getUiTranslator(): UITranslator
    {
        $locale = $this->getLocale();
        $localeBundleCacheKey = $this->getBundle($locale)->getLastCacheKey();
        return new UITranslator($locale, $this->paths, $localeBundleCacheKey);
    }

    /**
     * Get Weblate languages to array
     * Combine app's language names with weblate's in English.
     * Weblate's names override app's if same locale key
     *
     * @throws Exception
     *
     * @return string[]
     *
     */
    public function getWeblateLocaleNames(): array
    {
        return $this->weblateLocaleNames ??= (function (): array {
            $file = Core::getBaseDir() . '/' . PKP_LIB_PATH . '/lib/weblateLanguages/languages.json';
            $key = __METHOD__ . self::MAX_WEBLATE_LOCALES_CACHE_LIFETIME . filemtime($file);
            $expiration = DateInterval::createFromDateString(self::MAX_WEBLATE_LOCALES_CACHE_LIFETIME);
            return Cache::remember($key, $expiration, fn (): array => collect(json_decode(file_get_contents($file) ?: throw new Exception('Failed to load Weblate locales'), true))
                ->sortKeys()
                ->all());
        })();
    }

    /**
     * Get appropriately localized display names for submission locales to array
     * If $filterByLocales empty, return all languages.
     * Adds '*' (= in English) to display name if no translation available
     *
     * @param array $filterByLocales Optional list of locale codes/code-name-pairs to filter
     * @param ?string $displayLocale Optional display locale
     *
     * @return array The list of locales with formatted display name
     */
    public function getSubmissionLocaleDisplayNames(array $filterByLocales = [], ?string $displayLocale = null): array
    {
        $displayLocale = $displayLocale ?: $this->getLocale();
        return collect($this->getWeblateLocaleNames())
            ->when($filterByLocales, fn (Collection $sln) => $sln->intersectByKeys(array_is_list($filterByLocales) ? array_flip(array_filter($filterByLocales)) : $filterByLocales))
            ->when($displayLocale !== 'en', fn (Collection $sln) => $sln->map(function ($nameEn, $l) use ($displayLocale) {
                $dn = locale_get_display_name($l, $displayLocale);
                return ($dn && $dn !== $l) ? $dn : "*{$nameEn}";
            }))
            ->toArray();
    }

    /**
     * Translates the texts
     *
     * @hook Locale::translate [[&$value, $key, $params, $number, $locale, $localeBundle]]
     */
    protected function translate(string $key, ?int $number, array $params, ?string $locale): string
    {
        if (($key = trim($key)) === '') {
            return '';
        }

        $locale ??= $this->getLocale();
        $localeBundle = $this->getBundle($locale);
        $value = $number === null ? $localeBundle->translateSingular($key, $params) : $localeBundle->translatePlural($key, $number, $params);
        if ($value !== null || Hook::call('Locale::translate', [&$value, $key, $params, $number, $locale, $localeBundle])) {
            return $value;
        }

        // In order to reduce the noise, we're only logging missing entries for the en locale
        // TODO: Allow the other missing entries to be logged once the Laravel's logging is setup
        if ($locale === LocaleInterface::DEFAULT_LOCALE) {
            error_log("Missing locale key \"{$key}\" for the locale \"{$locale}\"");
        }
        return is_callable($this->missingKeyHandler) ? ($this->missingKeyHandler)($key) : '##' . htmlentities($key) . '##';
    }

    /**
     * Retrieves a cached item only if it belongs to the current locale. If it doesn't exist, the getter will be called
     */
    private function _getLocaleCache(string $key, ?string $locale, callable $getter)
    {
        if (($locale ??= $this->getLocale()) !== $this->getLocale()) {
            return $getter();
        }
        if (!isset($this->cache[$key][$locale])) {
            // Ensures the previous cache is cleared
            $this->cache[$key] = [$locale => $getter()];
        }
        return $this->cache[$key][$locale];
    }

    /**
     * Given a locale folder, retrieves all locale files (.po)
     *
     * @return int[]
     */
    private function _getLocaleFiles(string $folder, string $locale, int $priority): array
    {
        $files = $this->localeFiles[$folder][$locale] ?? null;
        if ($files === null) {
            $files = [];
            if (is_dir($path = "{$folder}/{$locale}")) {
                $directory = new RecursiveDirectoryIterator($path);
                $iterator = new RecursiveIteratorIterator($directory);
                $files = array_keys(iterator_to_array(new RegexIterator($iterator, '/\.po$/i', RecursiveRegexIterator::GET_MATCH)));
            }
            $this->localeFiles[$folder][$locale] = $files;
        }
        return array_fill_keys($files, $priority);
    }

    /**
     * Retrieves the request
     */
    private function _getRequest(): PKPRequest
    {
        return app(PKPRequest::class);
    }

    /**
     * Retrieves the ISO codes factory
     */
    private function _getIsoCodes(?string $locale = null): IsoCodesFactory
    {
        return app(IsoCodesFactory::class, $locale ? ['locale' => $locale] : []);
    }

    /**
     * Retrieves the supported locales
     *
     * @return string[]
     */
    private function _getSupportedLocales(): array
    {
        if (isset($this->supportedLocales)) {
            return $this->supportedLocales;
        }
        $locales = (PKPSessionGuard::isSessionDisable() ? null : $this->_getRequest()->getContext()?->getSupportedLocales() ?? $this->_getRequest()->getSite()?->getSupportedLocales())
            ?? array_map(fn (LocaleMetadata $locale) => $locale->locale, $this->getLocales());
        return $this->supportedLocales = array_combine($locales, $locales);
    }

    /**
     * Retrieve the preferred user locale from our supported locales using the Accept-Language header
     * If there's no match, it falls back to the server's primary locale
     */
    private function getPreferredLocale(): ?string
    {
        $serverPreference = $this->getPrimaryLocale() ?: LocaleInterface::DEFAULT_LOCALE;
        $supportedLocales = array_values($this->_getSupportedLocales());
        // Move the server preference to the top, in case the user preference doesn't match with the supported locales, the server one will be picked
        if (is_int($index = array_search($serverPreference, $supportedLocales))) {
            array_splice($supportedLocales, $index, 1);
        }
        return app(\Illuminate\Http\Request::class)->getPreferredLanguage([$serverPreference, ...$supportedLocales]);
    }
}

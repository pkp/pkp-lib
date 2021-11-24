<?php

declare(strict_types=1);

/**
 * @defgroup i18n I18N
 * Implements localization concerns such as locale files, time zones, and country lists.
 */

/**
 * @file classes/i18n/translation/IsoCodesTranslationDriver.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class IsoCodesTranslationDriver
 * @ingroup i18n
 *
 * @brief Translation provider for the IsoCodes package, faster and optimized to not keep items in memory
 */

namespace PKP\i18n\translation;

use APP\core\Application;
use DateInterval;
use DomainException;
use Illuminate\Support\Facades\Cache;
use PKP\i18n\interfaces\LocaleInterface;
use PKP\i18n\translation\Translator;
use Sokil\IsoCodes\TranslationDriver\TranslationDriverInterface;

class IsoCodesTranslationDriver implements TranslationDriverInterface
{
    /** @var string Max lifetime for the cache, a new cache file is created whenever the translation file is modified */
    protected const MAX_CACHE_LIFETIME = '1 year';

    protected ?Translator $translator = null;
    protected string $locale;

    /**
     * Constructor
     */
    public function __construct(string $locale)
    {
        $this->setLocale($locale);
    }

    /**
     * Setups the translator
     */
    public function configureDirectory(string $isoNumber, string $directory): void
    {
        if (!preg_match(LocaleInterface::LOCALE_EXPRESSION, $this->locale, $matches)) {
            throw new DomainException("Invalid locale \"{$this->locale}\"");
        }
        $locale = (object) [
            'language' => $matches['language'],
            'country' => $matches['country'] ?? null,
            'script' => $matches['script'] ?? null
        ];
        $locales = [$this->locale, ...($locale->script ? [$locale->language . '@' . $locale->script] : []), $locale->language];
        // Attempts to find the best locale
        foreach ($locales as $locale) {
            $path = "${directory}/${locale}/LC_MESSAGES/${isoNumber}.mo";
            if (file_exists($path)) {
                // Check if it's installed before caching the ISO codes (huge dataset), just to avoid a slow installation page
                $loader = fn () => Translator::createFromTranslationsArray(LocaleFile::loadArray($path, Application::isInstalled()));
                $this->translator = Application::isInstalled()
                    ? Cache::remember($this->_getCacheKey($path), DateInterval::createFromDateString(static::MAX_CACHE_LIFETIME), $loader)
                    : $loader();
                break;
            }
        }
    }

    /**
     * Setup the driver locale
     */
    public function setLocale(string $locale): void
    {
        $this->locale = $locale;
    }

    /**
     * Attempts to translate an entry
     */
    public function translate(string $isoNumber, string $message): string
    {
        return ($this->translator ? $this->translator->getSingular($message) : $message) ?: $message;
    }

    /**
     * Retrieves the cache key
     */
    private static function _getCacheKey(string $path): string
    {
        return __METHOD__ . static::MAX_CACHE_LIFETIME . '.' . sha1($path . filemtime($path));
    }
}

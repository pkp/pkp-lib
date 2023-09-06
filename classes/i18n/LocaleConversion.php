<?php

declare(strict_types=1);

/**
 * @defgroup i18n I18N
 * Implements localization concerns such as locale files, time zones, and country lists.
 */

/**
 * @file classes/i18n/LocaleConversion.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class LocaleConversion
 *
 * @ingroup i18n
 *
 * @brief Provides methods to convert locales from one format to another
 */

namespace PKP\i18n;

use DateInterval;
use Exception;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use PKP\core\Core;
use PKP\facades\Locale;

class LocaleConversion
{
    /** @var string Max lifetime for the ISO639-2b cache. */
    protected const MAX_ISO6392B_CACHE_LIFETIME = '1 year';

    /**
     * Get ISO639-2b array
     *
     * @throw Exception
     */
    protected static function getISO6392b(): array
    {
        $iso6392bFile = Core::getBaseDir() . '/' . PKP_LIB_PATH . '/lib/vendor/sokil/php-isocodes-db-i18n/databases/iso_639-2.json';
        if (!file_exists($iso6392bFile)) {
            throw new Exception("The ISO639-2b file {$iso6392bFile} does not exist.");
        }
        $key = __METHOD__ . 'iso639-2b' . self::MAX_ISO6392B_CACHE_LIFETIME . filemtime($iso6392bFile);
        $expiration = DateInterval::createFromDateString(static::MAX_ISO6392B_CACHE_LIFETIME);
        return Cache::remember($key, $expiration, function () use ($iso6392bFile) {
            return json_decode(file_get_contents($iso6392bFile), true);
        });
    }

    /**
     * Translate the ISO 2-letter language string (ISO639-1) into a ISO compatible 3-letter string (ISO639-2b).
     */
    public static function get3LetterFrom2LetterIsoLanguage(?string $iso2Letter): ?string
    {
        try {
            $languages = self::getISO6392b();
        } catch (Exception $e) {
            error_log($e->getMessage());
            return null;
        }
        foreach (reset($languages) as $languageRaw) {
            if (($languageRaw['alpha_2'] ?? null) === $iso2Letter) {
                return $languageRaw['bibliographic'] ?? $languageRaw['alpha_3'];
            }
        }
        return null;
    }

    /**
     * Translate the ISO 3-letter language string (ISO639-2b) into a ISO compatible 2-letter string (ISO639-1).
     */
    public static function get2LetterFrom3LetterIsoLanguage(?string $iso3Letter): ?string
    {
        try {
            $languages = self::getISO6392b();
        } catch (Exception $e) {
            error_log($e->getMessage());
            return null;
        }
        foreach (reset($languages) as $languageRaw) {
            if (($languageRaw['bibliographic'] ?? null) === $iso3Letter || $languageRaw['alpha_3'] === $iso3Letter) {
                return $languageRaw['alpha_2'] ?? null;
            }
        }
        return null;
    }

    /**
     * Translate the PKP locale identifier into an ISO639-2b compatible 3-letter string.
     */
    public static function get3LetterIsoFromLocale(?string $locale): ?string
    {
        $iso2Letter = substr($locale, 0, 2);
        return static::get3LetterFrom2LetterIsoLanguage($iso2Letter);
    }

    /**
     * Translate an ISO639-2b compatible 3-letter string into the PKP locale identifier.
     * This can be ambiguous if several locales are defined for the same language. In this case we'll use the primary locale to disambiguate.
     * If that still doesn't determine a unique locale then we'll choose the first locale found.
     */
    public static function getLocaleFrom3LetterIso(?string $iso3Letter): ?string
    {
        $primaryLocale = Locale::getPrimaryLocale();

        $alpha2Candidates = $localeCandidates = [];
        try {
            $languages = self::getISO6392b();
        } catch (Exception $e) {
            error_log($e->getMessage());
            return null;
        }
        foreach (reset($languages) as $languageRaw) {
            if (($languageRaw['bibliographic'] ?? null) === $iso3Letter || $languageRaw['alpha_3'] === $iso3Letter) {
                if (array_key_exists('alpha_2', $languageRaw)) {
                    $alpha2Candidates[] = $languageRaw['alpha_2'];
                }
            }
        }
        foreach (Locale::getLocales() as $identifier => $locale) {
            if (in_array($locale->getIsoAlpha2(), $alpha2Candidates)) {
                if ($identifier === $primaryLocale) {
                    // In case of ambiguity the primary locale overrides all other options so we're done.
                    return $primaryLocale;
                }
                $localeCandidates[$identifier] = true;
            }
        }

        // Attempts to retrieve the first matching locale which is in the supported list, otherwise defaults to the first found candidate
        return Arr::first(array_keys(Locale::getSupportedLocales()), fn (string $locale) => $localeCandidates[$locale] ?? false, array_key_first($localeCandidates));
    }

    /**
     * Translate the ISO 2-letter language string (ISO639-1) into ISO639-3.
     */
    public static function getIso3FromIso1(?string $iso1): ?string
    {
        $locale = Arr::first(Locale::getLocales(), fn (LocaleMetadata $locale) => $locale->getIsoAlpha2() === $iso1);
        return $locale ? $locale->getIsoAlpha3() : null;
    }

    /**
     * Translate the ISO639-3 into ISO639-1.
     */
    public static function getIso1FromIso3(?string $iso3): ?string
    {
        $locale = Arr::first(Locale::getLocales(), fn (LocaleMetadata $locale) => $locale->getIsoAlpha3() === $iso3);
        return $locale ? $locale->getIsoAlpha2() : null;
    }

    /**
     * Translate the PKP locale identifier into an ISO639-3 compatible 3-letter string.
     */
    public static function getIso3FromLocale(?string $locale): ?string
    {
        $iso1 = substr($locale, 0, 2);
        return static::getIso3FromIso1($iso1);
    }

    /**
     * Translate the PKP locale identifier into an ISO639-1 compatible 2-letter string.
     */
    public static function getIso1FromLocale(?string $locale): string
    {
        return substr($locale, 0, 2);
    }
}

<?php
declare(strict_types = 1);

/**
 * @defgroup i18n I18N
 * Implements localization concerns such as locale files, time zones, and country lists.
 */

/**
 * @file classes/i18n/LocaleConversion.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class LocaleConversion
 * @ingroup i18n
 *
 * @brief Provides methods to convert locales from one format to another
 */

namespace PKP\i18n;

use PKP\facades\Locale;

class LocaleConversion
{
    /**
     * Translate the ISO 2-letter language string (ISO639-1) into a ISO compatible 3-letter string (ISO639-2b).

     * @return string The translated string or null if we don't know about the given language.
     */
    public static function get3LetterFrom2LetterIsoLanguage(?string $iso2Letter): ?string
    {
        assert(strlen($iso2Letter) == 2);
        $locales = Locale::getLocales();
        foreach ($locales as $locale => $localeData) {
            if (substr($locale, 0, 2) == $iso2Letter) {
                return $localeData->iso639_2b;
            }
        }
        return null;
    }

    /**
     * Translate the ISO 3-letter language string (ISO639-2b)
     * into a ISO compatible 2-letter string (ISO639-1).

     * @return string the translated string or null if we don't know about the given language.
     */
    public static function get2LetterFrom3LetterIsoLanguage(?string $iso3Letter): ?string
    {
        assert(strlen($iso3Letter) == 3);
        $locales = Locale::getLocales();
        foreach ($locales as $locale => $localeData) {
            if ($localeData->iso639_2b == $iso3Letter) {
                return substr($locale, 0, 2);
            }
        }
        return null;
    }

    /**
     * Translate the PKP locale identifier into an ISO639-2b compatible 3-letter string.
     */
    public static function get3LetterIsoFromLocale(?string $locale): ?string
    {
        assert(strlen($locale) >= 5);
        $iso2Letter = substr($locale, 0, 2);
        return static::get3LetterFrom2LetterIsoLanguage($iso2Letter);
    }

    /**
     * Translate an ISO639-2b compatible 3-letter string
     * into the PKP locale identifier.
     *
     * This can be ambiguous if several locales are defined
     * for the same language. In this case we'll use the
     * primary locale to disambiguate.
     *
     * If that still doesn't determine a unique locale then
     * we'll choose the first locale found.
     */
    public static function getLocaleFrom3LetterIso(?string $iso3Letter): ?string
    {
        assert(strlen($iso3Letter) == 3);
        $primaryLocale = Locale::getPrimaryLocale();

        $localeCandidates = [];
        $locales = Locale::getLocales();
        foreach ($locales as $locale => $localeData) {
            if ($localeData->iso639_2b == $iso3Letter) {
                if ($locale == $primaryLocale) {
                    // In case of ambiguity the primary locale
                    // overrides all other options so we're done.
                    return $primaryLocale;
                }
                $localeCandidates[] = $locale;
            }
        }

        // Return null if we found no candidate locale.
        if (!count($localeCandidates)) {
            return null;
        }

        if (count($localeCandidates) > 1) {
            // Check whether one of the candidate locales
            // is a supported locale. If so choose the first
            // supported locale.
            $supportedLocales = array_keys(Locale::getSupportedLocales());
            foreach ($supportedLocales as $supportedLocale) {
                if (in_array($supportedLocale, $localeCandidates)) {
                    return $supportedLocale;
                }
            }
        }

        // If there is only one candidate (or if we were
        // unable to disambiguate) then return the unique
        // (first) candidate found.
        return array_shift($localeCandidates);
    }

    /**
     * Translate the ISO 2-letter language string (ISO639-1) into ISO639-3.

     * @return string The translated string or null if we don't know about the given language.
     */
    public static function getIso3FromIso1(?string $iso1): ?string
    {
        assert(strlen($iso1) == 2);
        $locales = Locale::getLocales();
        foreach ($locales as $locale => $localeData) {
            if (substr($locale, 0, 2) == $iso1) {
                return $localeData->iso639_3;
            }
        }
        return null;
    }

    /**
     * Translate the ISO639-3 into ISO639-1.

     * @return string The translated string or null if we don't know about the given language.
     */
    public static function getIso1FromIso3(?string $iso3): ?string
    {
        assert(strlen($iso3) == 3);
        $locales = Locale::getLocales();
        foreach ($locales as $locale => $localeData) {
            if ($localeData->iso639_3 == $iso3) {
                return substr($locale, 0, 2);
            }
        }
        return null;
    }

    /**
     * Translate the PKP locale identifier into an ISO639-3 compatible 3-letter string.
     */
    public static function getIso3FromLocale(?string $locale): ?string
    {
        assert(strlen($locale) >= 5);
        $iso1 = substr($locale, 0, 2);
        return static::getIso3FromIso1($iso1);
    }

    /**
     * Translate the PKP locale identifier into an ISO639-1 compatible 2-letter string.
     */
    public static function getIso1FromLocale(?string $locale): string
    {
        assert(strlen($locale) >= 5);
        return substr($locale, 0, 2);
    }

    /**
     * Translate an ISO639-3 compatible 3-letter string
     * into the PKP locale identifier.
     *
     * This can be ambiguous if several locales are defined
     * for the same language. In this case we'll use the
     * primary locale to disambiguate.
     *
     * If that still doesn't determine a unique locale then
     * we'll choose the first locale found.
     */
    public static function getLocaleFromIso3(?string $iso3): string
    {
        assert(strlen($iso3) == 3);
        $primaryLocale = Locale::getPrimaryLocale();

        $localeCandidates = [];
        $locales = Locale::getLocales();
        foreach ($locales as $locale => $localeData) {
            if ($localeData->iso639_3 == $iso3) {
                if ($locale == $primaryLocale) {
                    // In case of ambiguity the primary locale
                    // overrides all other options so we're done.
                    return $primaryLocale;
                }
                $localeCandidates[] = $locale;
            }
        }

        // Return null if we found no candidate locale.
        if (!count($localeCandidates)) {
            return null;
        }

        if (count($localeCandidates) > 1) {
            // Check whether one of the candidate locales
            // is a supported locale. If so choose the first
            // supported locale.
            $supportedLocales = array_keys(Locale::getSupportedLocales());
            foreach ($supportedLocales as $supportedLocale) {
                if (in_array($supportedLocale, $localeCandidates)) {
                    return $supportedLocale;
                }
            }
        }

        // If there is only one candidate (or if we were
        // unable to disambiguate) then return the unique
        // (first) candidate found.
        return array_shift($localeCandidates);
    }
}

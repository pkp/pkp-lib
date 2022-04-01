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

use Illuminate\Support\Arr;
use PKP\facades\Locale;

class LocaleConversion
{
    /**
     * Translate the ISO 2-letter language string (ISO639-1) into a ISO compatible 3-letter string (ISO639-2b).
     */
    public static function get3LetterFrom2LetterIsoLanguage(?string $iso2Letter): ?string
    {
        assert(strlen($iso2Letter) === 2);
        $locale = Arr::first(Locale::getLocales(), fn(LocaleMetadata $locale) => $locale->getIsoAlpha2() === $iso2Letter);
        return $locale ? $locale->getIsoAlpha3() : null;
    }

    /**
     * Translate the ISO 3-letter language string (ISO639-2b) into a ISO compatible 2-letter string (ISO639-1).
     */
    public static function get2LetterFrom3LetterIsoLanguage(?string $iso3Letter): ?string
    {
        assert(strlen($iso3Letter) === 3);
        $locale = Arr::first(Locale::getLocales(), fn(LocaleMetadata $locale) => $locale->getIsoAlpha3() === $iso3Letter);
        return $locale ? $locale->getIsoAlpha2() : null;
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
     * Translate an ISO639-2b compatible 3-letter string into the PKP locale identifier.
     * This can be ambiguous if several locales are defined for the same language. In this case we'll use the primary locale to disambiguate.
     * If that still doesn't determine a unique locale then we'll choose the first locale found.
     */
    public static function getLocaleFrom3LetterIso(?string $iso3Letter): ?string
    {
        assert(strlen($iso3Letter) === 3);
        $primaryLocale = Locale::getPrimaryLocale();

        $candidates = [];
        foreach (Locale::getLocales() as $identifier => $locale) {
            if ($locale->getIsoAlpha3() === $iso3Letter) {
                if ($identifier === $primaryLocale) {
                    // In case of ambiguity the primary locale overrides all other options so we're done.
                    return $primaryLocale;
                }
                $candidates[$identifier] = true;
            }
        }

        // Attempts to retrieve the first matching locale which is in the supported list, otherwise defaults to the first found candidate
        return Arr::first(array_keys(Locale::getSupportedLocales()), fn(string $locale) => $candidates[$locale] ?? false, array_key_first($candidates));
    }

    /**
     * Translate the ISO 2-letter language string (ISO639-1) into ISO639-3.
     */
    public static function getIso3FromIso1(?string $iso1): ?string
    {
        assert(strlen($iso1) === 2);
        $locale = Arr::first(Locale::getLocales(), fn(LocaleMetadata $locale) => $locale->getIsoAlpha2() === $iso1);
        return $locale ? $locale->getIsoAlpha3() : null;
    }

    /**
     * Translate the ISO639-3 into ISO639-1.
     */
    public static function getIso1FromIso3(?string $iso3): ?string
    {
        assert(strlen($iso3) === 3);
        $locale = Arr::first(Locale::getLocales(), fn(LocaleMetadata $locale) => $locale->getIsoAlpha3() === $iso3);
        return $locale ? $locale->getIsoAlpha2() : null;
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
}

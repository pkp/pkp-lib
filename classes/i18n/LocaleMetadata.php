<?php

declare(strict_types=1);

/**
 * @defgroup i18n I18N
 * Implements localization concerns such as locale files, time zones, and country lists.
 */

/**
 * @file classes/i18n/LocaleMetadata.php
 *
 * Copyright (c) 2014-2024 Simon Fraser University
 * Copyright (c) 2000-2024 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class LocaleMetadata
 *
 * @ingroup i18n
 *
 * @brief Holds metadata about a system locale
 */

namespace PKP\i18n;

use DomainException;
use Exception;
use PKP\core\ExportableTrait;
use PKP\facades\Locale;
use PKP\i18n\interfaces\LocaleInterface;
use Sokil\IsoCodes\Database\Languages\Language;

class LocaleMetadata
{
    use ExportableTrait;

    /**
     * The following constants define how the locale information will be presented
     *
     * LANGUAGE_LOCALE_WITHOUT : The locale will be presented in the current selected language
     * So, if English and French is available and user selected locale is French, it will be
     * shown as Français | Anglais
     *
     * LANGUAGE_LOCALE_WITH : The locale will be presented in current selected language along
     * with each locale's own translated name . So, if English and French is available and user
     * selected locale is French, it will be shown as Français/Français | Anglais/English
     *
     * LANGUAGE_LOCALE_ONLY : The locale will be presented only in each locale's translated
     * name . So, if English and French is available and user
     * selected locale is French, it will be shown as Français | English
     */
    public const LANGUAGE_LOCALE_WITHOUT = 1;
    public const LANGUAGE_LOCALE_WITH = 2;
    public const LANGUAGE_LOCALE_ONLY = 3;

    private ?object $_parsedLocale = null;

    /**
     * Constructor
     */
    public function __construct(
        /** Locale identification */
        public ?string $locale = null
    ) {
    }

    /**
     * Get the language locale conversion status
     *
     */
    public static function getLanguageLocaleStatuses(): array
    {
        return [
            self::LANGUAGE_LOCALE_WITHOUT,
            self::LANGUAGE_LOCALE_WITH,
            self::LANGUAGE_LOCALE_ONLY
        ];
    }

    /**
     * Retrieves this locale display name
     *
     * @param string    $locale             The locale code the name of this locale should be displayed in
     * @param bool      $withCountry        Whether to append the country name to language
     * @param int       $langLocaleStatus   The language locale conversion value specified by const LocaleMetadata::LANGUAGE_LOCALE_*
     *
     * @return string The fully qualified locale with/without own translated locale and with/without country name
     */
    public function getDisplayName(?string $locale = null, bool $withCountry = false, int $langLocaleStatus = self::LANGUAGE_LOCALE_WITHOUT): string
    {
        if (!in_array($langLocaleStatus, static::getLanguageLocaleStatuses())) {
            throw new Exception(
                sprintf(
                    'Invalid language locale conversion status %s given, must be among [%s]',
                    $langLocaleStatus,
                    implode(',', static::getLanguageLocaleStatuses())
                )
            );
        }

        $locale ??= Locale::getLocale();
        $displayLocale = $langLocaleStatus === self::LANGUAGE_LOCALE_ONLY ? $this->locale : $locale;

        $weblateLocaleName = Locale::getWeblateLocaleNames()[$this->locale];
        $displayName = locale_get_display_language($this->locale, $displayLocale);
        $name = ($displayName && $displayName !== $this->locale) ? $displayName : $weblateLocaleName;

        if ($langLocaleStatus === self::LANGUAGE_LOCALE_WITH) {
            // Get the translated language name in language's own locale
            $displayName = locale_get_display_language($this->locale, $this->locale);
            $nameInLangLocale = ($displayName && $displayName !== $this->locale) ? $displayName : $weblateLocaleName;

            $name = __(
                'common.withForwardSlash',
                [
                    'item' => $name,
                    'afterSlash' => $nameInLangLocale,
                ]
            );
        }

        if (!$withCountry) {
            return $name;
        }

        $country = locale_get_display_region($this->locale, $displayLocale);

        if (!$country) {
            return $name;
        }

        if ($langLocaleStatus === self::LANGUAGE_LOCALE_WITH) {
            // Get the translated country name in language's own locale
            $localizedCountryName = locale_get_display_region($this->locale, $this->locale);
            if (strcmp($localizedCountryName, $country) !== 0) {
                $country = __(
                    'common.withForwardSlash',
                    [
                        'item' => $country,
                        'afterSlash' => $localizedCountryName
                    ]
                );
            }
        }

        return __(
            'common.withParenthesis',
            [
                'item' => $name,
                'inParenthesis' => $country,
            ]
        );
    }

    /**
     * Retrieves the country name
     */
    public function getCountry(?string $locale = null): ?string
    {
        return locale_get_display_region($locale) ?? null;
    }

    /**
     * Retrieves the script name
     */
    public function getScript(?string $locale = null): ?string
    {
        return locale_get_display_script($locale) ?? null;
    }

    /**
     * Whether the locale expects text on the right-to-left format
     */
    public function isRightToLeft(): bool
    {
        $locale = $this->_parse();
        $language = strtolower($locale->language ?? '');
        $script = strtolower($locale->script ?? '');
        $rightToLeftLanguages = array_fill_keys(['ar', 'dv', 'fa', 'he', 'ku', 'nqo', 'prs', 'ps', 'sd', 'syr', 'ug', 'ur', 'yi'], true);
        $languageScriptExceptions = ['sd-deva' => false, 'tzm-arab' => true, 'pa-arab' => true];
        return $languageScriptExceptions["{$language}-{$script}"]
            ?? $rightToLeftLanguages[$language]
            ?? false;
    }

    /**
     * Compares two locales and retrieves the completeness ratio (source locale keys which are present in the reference)
     * If a locale isn't supplied, LocaleInterface::DEFAULT_LOCALE will be used as reference
     */
    public function getCompletenessRatio(?string $referenceLocale = null): float
    {
        $destiny = Locale::getBundle($referenceLocale ?? LocaleInterface::DEFAULT_LOCALE)->getTranslator()->getEntries();
        $source = Locale::getBundle($this->locale, false)->getTranslator()->getEntries();
        $intersection = array_intersect_key($source, $destiny);
        return min(1, count($intersection) / max(1, count($destiny)));
    }

    /**
     * Retrieves whether the locale can be considered complete respecting a threshold level of completeness
     */
    public function isComplete(float $minimumThreshold = 0.9, ?string $referenceLocale = null): bool
    {
        return $this->getCompletenessRatio($referenceLocale) >= $minimumThreshold;
    }

    /**
     * Retrieves the ISO639-1 representation
     */
    public function getIsoAlpha2(): string
    {
        return $this->_getLanguage()->getAlpha2();
    }

    /**
     * Retrieves the ISO639-3 representation
     */
    public function getIsoAlpha3(): string
    {
        return $this->_getLanguage()->getAlpha3();
    }

    /**
     * Retrieves the language (Sokil Language object)
     */
    private function _getLanguage(?string $locale = null, bool $fromCache = true): ?Language
    {
        $locale ??= $this->locale;
        $language = locale_get_primary_language($locale);
        return Locale::getLanguages($language, $fromCache)->getByAlpha2($language) ?? Locale::getLanguages($language, $fromCache)->getByAlpha3($language);
    }

    /**
     * Parses the locale string and retrieve its pieces
     */
    private function _parse(): object
    {
        if (isset($this->_parsedLocale)) {
            return $this->_parsedLocale;
        }
        if (!Locale::isLocaleValid($this->locale)) {
            throw new DomainException("Invalid locale \"{$this->locale}\"");
        }
        return $this->_parsedLocale = (object) [
            'language' => locale_get_primary_language($this->locale),
            'country' => locale_get_region($this->locale) ?? null,
            'script' => locale_get_script($this->locale) ?? null
        ];
    }
}

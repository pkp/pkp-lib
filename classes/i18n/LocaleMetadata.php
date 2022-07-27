<?php

declare(strict_types=1);

/**
 * @defgroup i18n I18N
 * Implements localization concerns such as locale files, time zones, and country lists.
 */

/**
 * @file classes/i18n/LocaleMetadata.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class LocaleMetadata
 * @ingroup i18n
 *
 * @brief Holds metadata about a system locale
 */

namespace PKP\i18n;

use DomainException;
use PKP\core\ExportableTrait;
use PKP\core\PKPString;
use PKP\facades\Locale;
use PKP\i18n\interfaces\LocaleInterface;
use Sokil\IsoCodes\Database\Languages\Language;

class LocaleMetadata
{
    use ExportableTrait;

    /** Locale identification */
    public string $locale;

    private ?object $_parsedLocale = null;

    /**
     * Constructor
     */
    public static function create(string $locale): self
    {
        $instance = new static();
        $instance->locale = $locale;
        return $instance;
    }

    /**
     * Retrieves the display name
     *
     * @param bool $withCountry Whether to append the country name to language
     */
    public function getDisplayName(?string $locale = null, bool $withCountry = false): string
    {
        $name = PKPString::regexp_replace('/\s*\([^)]*\)\s*/', '', PKPString::ucfirst($this->_getLanguage($locale)->getLocalName()));
        if (!$withCountry) {
            return $name;
        }

        $country = $this->getCountry($locale);
        if (!$country) {
            return $name;
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
     * Retrieves the language name
     */
    public function getLanguage(?string $locale = null): string
    {
        return $this->_getLanguage($locale)->getLocalName();
    }

    /**
     * Retrieves the country name
     */
    public function getCountry(?string $locale = null): ?string
    {
        return $this->_parse()->country ? Locale::getCountries($locale)->getByAlpha2($this->_parse()->country)->getLocalName() : null;
    }

    /**
     * Retrieves the script name
     */
    public function getScript(?string $locale = null): ?string
    {
        $script = ucfirst($this->_parse()->script);
        return $this->_parse()->script ? Locale::getScripts($locale)->getByAlpha4($script)->getLocalName() : null;
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
        return $languageScriptExceptions["${language}-${script}"]
            ?? $rightToLeftLanguages[$language]
            ?? $languageScriptExceptions[$this->getIsoAlpha3() . "-${script}"]
            ?? $rightToLeftLanguages[$this->getIsoAlpha3()]
            ?? false;
    }

    /**
     * Compares two locales and retrieves the completeness ratio (source locale keys which are present in the reference)
     * If a locale isn't supplied, LocaleInterface::DEFAULT_LOCALE will be used as reference
     */
    public function getCompletenessRatio(string $referenceLocale = null): float
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
     * Retrieves the ISO639-2b representation
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
     * Private constructor
     */
    private function __construct()
    {
    }

    /**
     * Retrieves the language
     */
    private function _getLanguage(?string $locale = null): ?Language
    {
        return Locale::getLanguages($locale)->getByAlpha2($this->_parse()->language);
    }

    /**
     * Parses the locale string and retrieve its pieces
     */
    private function _parse(): object
    {
        if (isset($this->_parsedLocale)) {
            return $this->_parsedLocale;
        }
        if (!preg_match(LocaleInterface::LOCALE_EXPRESSION, $this->locale, $matches)) {
            throw new DomainException("Invalid locale \"{$this->locale}\"");
        }
        return $this->_parsedLocale = (object) [
            'language' => $matches['language'],
            'country' => $matches['country'] ?? null,
            // Updates our script definitions to match the ISO 15924
            'script' => isset($matches['script']) ? str_replace(['cyrillic', 'latin'], ['latn', 'cyrl'], strtolower($matches['script'])) : null
        ];
    }
}

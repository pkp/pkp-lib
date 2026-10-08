<?php

/**
 * @file classes/pid/BasePid.php
 *
 * Copyright (c) 2025-2026 Simon Fraser University
 * Copyright (c) 2025-2026 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class BasePid
 *
 * @ingroup pid
 *
 * @brief BasePid abstract class
 */

namespace PKP\pid;

abstract class BasePid
{
    /** @var array Regexes to extract PIDs */
    public const regexes = [];

    /** @var array Strict regexes for validating bare identifiers (without prefix or URL). Used by isValid(). */
    public const validationRegexes = [];

    /** @var string Default prefix, e.g. doi: arxiv: handle: */
    public const prefix = '';

    /** @var string Url prefix, e.g. https://doi.org/ https://arxiv.org/abs/ https://hdl.handle.net/ */
    public const urlPrefix = '';

    /** @var array|string[] Alternate prefixes */
    public const alternatePrefixes = [];

    /** @var string Default characters which are trimmed */
    public const defaultTrimCharacters = ' ./';

    /**
     * Add prefix.
     *
     * @param string|null $string e.g. 10.123/tib123
     *
     * @return string e.g. https://doi.org/10.123/tib123
     */
    public static function addPrefix(?string $string): string
    {
        if (empty($string)) {
            return '';
        }

        /* @var BasePid $class */
        $class = get_called_class();

        return $class::prefix . $string;
    }

    /**
     * Add urlPrefix.
     *
     * @param string|null $string e.g. 10.123/tib123
     *
     * @return string e.g. https://doi.org/10.123/tib123
     */
    public static function addUrlPrefix(?string $string): string
    {
        if (empty($string)) {
            return '';
        }

        /* @var BasePid $class */
        $class = get_called_class();

        return $class::urlPrefix . $string;
    }

    /**
     * Remove prefixes.
     *
     * @param string|null $string e.g. doi:10.123/tib123 https://doi.org/10.123/tib123
     *
     * @return string e.g. 10.123/tib123
     */
    public static function removePrefix(?string $string): string
    {
        if (empty($string)) {
            return '';
        }

        /* @var BasePid $class */
        $class = get_called_class();

        // Only a leading prefix: short alternate prefixes such as "doi" or "hdl" can occur inside an identifier.
        $string = preg_replace('#^http://#i', 'https://', trim($string));
        foreach ($class::getPrefixes() as $prefix) {
            if (stripos($string, $prefix) === 0) {
                $string = substr($string, strlen($prefix));
                break;
            }
        }

        return trim($string, $class::defaultTrimCharacters);
    }

    /**
     * Remove all instances of prefix . pid from string.
     */
    public static function removePrefixesWithPid(?string $pid, ?string $string): string
    {
        if (empty($pid) || empty($string)) {
            return $string ?: '';
        }

        /* @var BasePid $class */
        $class = get_called_class();

        return trim(
            str_replace(
                array_map(fn ($prefix) => $prefix . $pid, $class::getPrefixes()),
                '',
                $string
            )
        );
    }

    /**
     * Extract from string with regex.
     *
     * @return string e.g. 10.123/tib123
     */
    public static function extractFromString(?string $string): string
    {
        /* @var BasePid $class */
        $class = get_called_class();

        if (empty($class::regexes)) {
            return $string ?: '';
        }

        $match = '';
        foreach ($class::regexes as $regex) {
            if (preg_match($regex, $string ?? '', $matches)) {
                $match = $matches[0];
                break;
            }
        }

        if (empty($match)) {
            return '';
        }

        return trim($class::removePrefix(static::trimTrailingPunctuation($match)), $class::defaultTrimCharacters);
    }

    /**
     * Remove the punctuation that follows an identifier in running text: ".", ",", ";" or ":",
     * and a ")" that closes a parenthesis opened before the identifier. Identifiers may contain
     * all of these (e.g. SICI DOIs), so one that really ends in them cannot be told apart.
     */
    public static function trimTrailingPunctuation(string $string): string
    {
        while ($string !== '') {
            $char = substr($string, -1);
            $closesOuterParenthesis = $char === ')' && substr_count($string, ')') > substr_count($string, '(');
            if (!in_array($char, ['.', ',', ';', ':']) && !$closesOuterParenthesis) {
                break;
            }
            $string = substr($string, 0, -1);
        }
        return $string;
    }

    /**
     * Check if a bare identifier value is valid.
     *
     * @param string|null $value e.g. 10.123/tib123 (bare, no prefix)
     *
     *
     */
    public static function isValid(?string $value): bool
    {
        if (empty($value)) {
            return false;
        }

        /* @var BasePid $class */
        $class = get_called_class();

        if (!empty($class::validationRegexes)) {
            foreach ($class::validationRegexes as $regex) {
                if (preg_match($regex, $value)) {
                    return true;
                }
            }
            return false;
        }

        return $class::removePrefix($value) !== '';
    }

    /**
     * Get a list of possible prefixes.
     */
    public static function getPrefixes(): array
    {
        /* @var BasePid $class */
        $class = get_called_class();

        $prefixes = array_merge(
            [$class::prefix, $class::prefix . ' '],
            [$class::urlPrefix],
            $class::alternatePrefixes,
            array_map(fn ($value) => trim($value) . ' ', $class::alternatePrefixes)
        );
        $prefixes = array_filter($prefixes, fn ($value) => !empty(trim($value)));
        $prefixes = array_unique($prefixes);
        usort($prefixes, fn ($a, $b) => strlen($b) - strlen($a));

        return $prefixes;
    }
}

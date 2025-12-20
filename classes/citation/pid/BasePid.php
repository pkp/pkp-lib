<?php

/**
 * @file classes/citation/pid/BasePid.php
 *
 * Copyright (c) 2025 Simon Fraser University
 * Copyright (c) 2025 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class BasePid
 *
 * @ingroup citation
 *
 * @brief BasePid abstract class
 */

namespace PKP\citation\pid;

abstract class BasePid
{
    /** @var string Regexes to extract PIDs */
    public const regexes = [];

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

        return trim(
            str_ireplace($class::getPrefixes(), '', $string),
            $class::defaultTrimCharacters
        );
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
                array_map(fn($prefix) => $prefix . $pid, $class::getPrefixes()),
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
            if (preg_match($regex, $string, $matches)) {
                $match = $matches[0];
                break;
            }
        }

        if (empty($match)) {
            return '';
        }

        return trim($class::removePrefix($match), $class::defaultTrimCharacters);
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
            array_map(fn($value) => trim($value) . ' ', $class::alternatePrefixes)
        );
        $prefixes = array_filter($prefixes, fn($value) => !empty(trim($value)));
        $prefixes = array_unique($prefixes);
        usort($prefixes, fn($a, $b) => strlen($b) - strlen($a));

        return $prefixes;
    }
}

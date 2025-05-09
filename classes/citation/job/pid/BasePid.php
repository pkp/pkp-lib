<?php

/**
 * @file classes/citation/job/pid/BasePid.php
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

namespace PKP\citation\job\pid;

abstract class BasePid
{
    /** @var string Regex to extract PID */
    public const regex = '';

    /** @var string Correct prefix, e.g. https://doi.org */
    public const prefix = '';

    /** @var array|string[] Incorrect prefixes; omit http:// https:// */
    public const prefixInCorrect = [];

    /** @var string Default characters which are trimmed */
    public const defaultTrimCharacters = ' ./';

    /**
     * Add prefix
     * @param string|null $string e.g. 10.123/tib123
     * @return string e.g. https://doi.org10.123/tib123
     */
    public static function addPrefix(?string $string): string
    {
        if (empty($string)) return '';

        /* @var BasePid $class */
        $class = get_called_class();

        // no prefix defined, return original string
        if (empty($class::prefix)) return $string;

        $string = trim($string, $class::defaultTrimCharacters);

        return $class::prefix . '/' . $string;
    }

    /**
     * Remove prefix
     * @param string|null $string e.g. https://doi.org10.123/tib123
     * @return string e.g. 10.123/tib123
     */
    public static function removePrefix(?string $string): string
    {
        if (empty($string)) return '';

        /* @var BasePid $class */
        $class = get_called_class();

        // no prefix defined, return original string
        if (empty($class::prefix)) return $string;

        $string = str_replace($class::prefix, '', $string);

        return trim($string, $class::defaultTrimCharacters);
    }

    /**
     * Normalize PID by removing any incorrect prefixes.
     * @param string|null $string e.g. doi:10.123/tib123
     * @return string e.g. https://doi.org/10.123/tib123
     */
    public static function normalize(?string $string): string
    {
        if (empty($string)) return '';

        /* @var BasePid $class */
        $class = get_called_class();

        // no prefix defined, return original string
        if (empty($class::prefix)) return $string;

        $prefixInCorrect = $class::prefixInCorrect;

        // prefix without https://
        $prefixAlt = str_replace('https://', '', $class::prefix);

        // make secure
        $string = str_replace('http://', 'https://', $string);

        // process longer first, e.g. dx.doi.org before doi.org
        usort($prefixInCorrect, function ($a, $b) {
            return strlen($b) - strlen($a);
        });
        $string = str_replace($prefixInCorrect, $prefixAlt, $string);

        // common mistakes, e.g. doi.org:10.123/tib123
        $fixes = [
            "$prefixAlt: ",
            "$prefixAlt:",
            "$prefixAlt ",
            "www.$prefixAlt"
        ];
        $string = str_replace($fixes, $prefixAlt, $string);

        // add https://
        $string = str_replace($prefixAlt, "https://$prefixAlt/", $string);

        // clean doubles
        $doubles = [
            "https://$prefixAlt//",
            "https://https://$prefixAlt/",
            "https://https://$prefixAlt//"
        ];
        $string = str_replace($doubles, "https://$prefixAlt/", $string);

        return trim($string, $class::defaultTrimCharacters);
    }

    /**
     * Extract from string with regex
     * @param string|null $string
     * @return string e.g. 10.123/tib123
     */
    public static function extractFromString(?string $string): string
    {
        if (empty($string)) return '';

        /* @var BasePid $class */
        $class = get_called_class();

        // no regex defined, return empty
        if (empty($class::regex)) return '';

        $matches = [];

        preg_match($class::regex, $string, $matches);

        if (empty($matches[0])) return '';

        return trim($matches[0], $class::defaultTrimCharacters);
    }
}

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

    /** @var string Default prefix, e.g. https://doi.org/ arxiv: */
    public const defaultPrefix = '';

    /** @var string Url prefix, e.g. https://doi.org/ */
    public const urlPrefix = '';

    /** @var string Default characters which are trimmed */
    public const defaultTrimCharacters = ' ./';

    /**
     * Add prefix
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

        return $class::defaultPrefix . $string;
    }

    /**
     * Remove prefix
     *
     * @param string|null $string e.g. https://doi.org/10.123/tib123
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

        return str_ireplace($class::defaultPrefix, '', $string);
    }

    /**
     * Add urlPrefix
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
     * Remove urlPrefix
     *
     * @param string|null $string e.g. https://doi.org/10.123/tib123
     *
     * @return string e.g. 10.123/tib123
     */
    public static function removeUrlPrefix(?string $string): string
    {
        if (empty($string)) {
            return '';
        }

        /* @var BasePid $class */
        $class = get_called_class();

        return str_ireplace($class::urlPrefix, '', $string);
    }

    /**
     * Extract from string with regex
     *
     * @return string e.g. 10.123/tib123
     */
    public static function extractFromString(?string $string): string
    {
        if (empty($string)) {
            return '';
        }

        /* @var BasePid $class */
        $class = get_called_class();

        if (empty($class::regexes)) {
            return '';
        }

        $matches = [];
        foreach ($class::regexes as $regex) {
            if (preg_match($regex, $string, $localMatches)) {
                $matches = $localMatches;
                break;
            }
        }

        if (empty($matches[0])) {
            return '';
        }

        return trim(
            str_ireplace([$class::defaultPrefix, $class::urlPrefix], '', $matches[0]),
            $class::defaultTrimCharacters
        );
    }
}

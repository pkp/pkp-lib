<?php

/**
 * @file includes/functions.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @ingroup index
 *
 * @brief Contains definitions for common functions used system-wide.
 * Any frequently-used functions that cannot be put into an appropriate class should be added here.
 */


/*
 * Constants for expressing human-readable data sizes in their respective number of bytes.
 */
define('KB_IN_BYTES', 1024);
define('MB_IN_BYTES', 1024 * KB_IN_BYTES);
define('GB_IN_BYTES', 1024 * MB_IN_BYTES);
define('TB_IN_BYTES', 1024 * GB_IN_BYTES);
define('PB_IN_BYTES', 1024 * TB_IN_BYTES);
define('EB_IN_BYTES', 1024 * PB_IN_BYTES);
define('ZB_IN_BYTES', 1024 * EB_IN_BYTES);
define('YB_IN_BYTES', 1024 * ZB_IN_BYTES);

/**
 * Recursively strip HTML from a (multidimensional) array.
 *
 * @param array $values
 *
 * @return array the cleansed array
 */
function stripAssocArray($values)
{
    foreach ($values as $key => $value) {
        if (is_scalar($value)) {
            $values[$key] = strip_tags($values[$key]);
        } else {
            $values[$key] = stripAssocArray($values[$key]);
        }
    }
    return $values;
}

/**
 * @copydoc Core::cleanFileVar
 * Warning: Call this function from Core class. It is only exposed here to make
 * it available early in bootstrapping.
 */
function cleanFileVar($var)
{
    return preg_replace('/[^\w\-]/u', '', $var);
}

/**
 * Translates a pluralized locale key
 */
function __p(string $key, int $number, array $replace = [], ?string $locale = null): string
{
    return trans_choice($key, $number, $replace, $locale);
}

/**
 * Check if run on CLI
 *
 * @deprecated 3.5.0 use PKPContainer::getInstance()->runningInConsole()
 */
if (!function_exists('runOnCLI')) {
    function runOnCLI(?string $scriptPath = null): bool
    {
        return \PKP\core\PKPContainer::getInstance()->runningInConsole($scriptPath);
    }
}

/**
 * Converts a shorthand byte value to an integer byte value.
 *
 * @link https://secure.php.net/manual/en/function.ini-get.php
 * @link https://secure.php.net/manual/en/faq.using.php#faq.using.shorthandbytes
 *
 * @param   string  A (PHP ini) byte value, either shorthand or ordinary.
 *
 * @return  int     An integer byte value.
 */
if (!function_exists('convertHrToBytes')) {
    function convertHrToBytes(string $value): int
    {
        $value = strtolower(trim($value));
        $bytes = (int) $value;

        if (false !== strpos($value, 'g')) {
            $bytes *= GB_IN_BYTES;
        } elseif (false !== strpos($value, 'm')) {
            $bytes *= MB_IN_BYTES;
        } elseif (false !== strpos($value, 'k')) {
            $bytes *= KB_IN_BYTES;
        }

        // Deal with large (float) values which run into the maximum integer size.
        return min($bytes, PHP_INT_MAX);
    }
}

/**
 * Check if valid JSON
 *
 * @param   mixed  $data
 *
 * @return  bool
 */
if (!function_exists('isValidJson')) {
    function isValidJson(mixed $data): bool
    {
        if (!empty($data) && is_string($data)) {
            @json_decode($data);
            return (json_last_error() === JSON_ERROR_NONE);
        }
        return false;
    }
}

/**
 * Convert a query parameter to an array
 *
 * This method will convert a query parameter to an array, and
 * supports a comma-separated list of values
 *
 * @param mixed $value
 *
 * @return array
 */
if (!function_exists('paramToArray')) {
    function paramToArray(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }

        if (is_string($value)) {
            return explode(',', $value);
        }

        return [$value];
    }
}

/**
 * Deep merge two arrays when both the keys of is array
 * This is better than `array_merge_recursive` which turns the scaler values into array also
 * at the merge time.
 *
 * @param array $array1
 * @param array $array2
 *
 * @return array
 */
if (!function_exists('deepArrayMerge')) {
    function deepArrayMerge(array $array1, array $array2): array
    {
        $merged = $array1;

        foreach ($array2 as $key => $value) {
            // If the key exists in both arrays and both values are arrays, merge them recursively
            if (isset($merged[$key]) && is_array($merged[$key]) && is_array($value)) {
                $merged[$key] = deepArrayMerge($merged[$key], $value);
            } else {
                // Otherwise, assign the value from the second array
                $merged[$key] = $value;
            }
        }

        return $merged;
    }
}

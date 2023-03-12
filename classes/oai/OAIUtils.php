<?php

/**
 * @file classes/oai/OAIUtils.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class OAI
 * @ingroup oai
 *
 * @see OAIDAO
 *
 * @brief Utility functions used by OAI related classes.
 */

namespace PKP\oai;

use Transliterator;

class OAIUtils
{
    private static $TO_ASCII;
    private static $NOT_IN_URI_NON_RESERVED_CHARS = '/[^A-Za-z0-9\-_\.!~*\'()]/';

    public static function init()
    {
        self::$TO_ASCII = Transliterator::createFromRules(':: Any-Latin; :: Latin-ASCII; :: NFD; :: [:Nonspacing Mark:] Remove; :: NFC;', Transliterator::FORWARD);
    }

    /**
     * Return a UTC-formatted datestamp from the specified UNIX timestamp.
     *
     * @param int $timestamp *nix timestamp (if not used, the current time is used)
     * @param bool $includeTime include both the time and date
     *
     * @return string UTC datestamp
     */
    public static function UTCDate($timestamp = 0, $includeTime = true)
    {
        $format = 'Y-m-d';
        if ($includeTime) {
            $format .= "\TH:i:s\Z";
        }

        if ($timestamp == 0) {
            return gmdate($format);
        } else {
            return gmdate($format, $timestamp);
        }
    }

    /**
     * Returns a UNIX timestamp from a UTC-formatted datestamp.
     * Returns the string "invalid" if datestamp is invalid,
     * or "invalid_granularity" if unsupported granularity.
     *
     * @param string $date UTC datestamp
     * @param string $requiredGranularity Datestamp granularity to require (default: not checked)
     *
     * @return int timestamp
     */
    public static function UTCtoTimestamp($date, $requiredGranularity = null)
    {
        // FIXME Has limited range (see http://php.net/strtotime)
        if (preg_match("/^\d\d\d\d\-\d\d\-\d\d$/", $date)) {
            // Match date
            $time = strtotime("{$date} UTC");
            return ($time != -1) ? $time : 'invalid';
        } elseif (preg_match("/^(\d\d\d\d\-\d\d\-\d\d)T(\d\d:\d\d:\d\d)Z$/", $date, $matches)) {
            // Match datetime
            // FIXME
            $date = "{$matches[1]} {$matches[2]}";
            if ($requiredGranularity && $requiredGranularity != 'YYYY-MM-DDThh:mm:ssZ') {
                return 'invalid_granularity';
            } else {
                $time = strtotime("{$date} UTC");
                return ($time != -1) ? $time : 'invalid';
            }
        } else {
            return 'invalid';
        }
    }


    /**
     * Clean input variables (by reference).
     *
     * @param mixed $data request parameter(s)
     */
    public static function prepInput(&$data)
    { // REFERENCE REQUIRED
        if (!is_array($data)) {
            $data = urldecode($data);
        } else {
            foreach ($data as $k => $v) {
                if (is_array($data[$k])) {
                    self::prepInput($data[$k]);
                } else {
                    $data[$k] = urldecode($v);
                }
            }
        }
        return $data;
    }

    /**
     * Prepare variables for output (by reference).
     * Data is assumed to be UTF-8 encoded (FIXME?)
     *
     * @param mixed $data output parameter(s)
     *
     * @return mixed cleaned output parameter(s)
     */
    public static function prepOutput(&$data)
    { // REFERENCE REQUIRED
        if (!is_array($data)) {
            $data = htmlspecialchars($data);
        } else {
            foreach ($data as $k => $v) {
                if (is_array($data[$k])) {
                    self::prepOutput($data[$k]);
                } else {
                    // FIXME FIXME FIXME
                    $data[$k] = htmlspecialchars($v);
                }
            }
        }
        return $data;
    }

    /**
     * Parses string $string into an associate array $array.
     * Acts like parse_str($string, $array) except duplicate
     * variable names in $string are converted to an array.
     *
     * @param array $array of parsed parameters
     */
    public static function parseStr($string, &$array)
    {
        $pairs = explode('&', $string);
        foreach ($pairs as $p) {
            $vars = explode('=', $p);
            if (!empty($vars[0]) && isset($vars[1])) {
                $key = $vars[0];
                $value = join('=', array_splice($vars, 1));

                if (!isset($array[$key])) {
                    $array[$key] = $value;
                } elseif (is_array($array[$key])) {
                    array_push($array[$key], $value);
                } else {
                    $array[$key] = [$array[$key], $value];
                }
            }
        }
    }

    /**
     * Valid characters in setSpec [1] are URI unreserved characters [2]
     * Create a valid string by
     *  - transliterating all non-Latin characters
     *  - stripping all diacritics
     *  - dropping any remaining invalid characters
     *
     * [1] https://www.openarchives.org/OAI/2.0/openarchivesprotocol.htm#Set
     * [2] http://www.ietf.org/rfc/rfc2396.txt
     *
     */
    public static function toValidSetSpec(string $string): string
    {
        $t = self::$TO_ASCII->transliterate($string);
        return preg_replace(self::$NOT_IN_URI_NON_RESERVED_CHARS, '', $t);
    }
}
OAIUtils::init();

if (!PKP_STRICT_MODE) {
    class_alias('\PKP\oai\OAIUtils', '\OAIUtils');
}

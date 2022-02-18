<?php

/**
 * @file classes/core/PKPString.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PKPString
 * @ingroup core
 *
 * @brief String manipulation wrapper class.
 *
 */

namespace PKP\core;

use PKP\config\Config;
use Stringy\Stringy;

class PKPString
{
    /** @var int Camel case for class names */
    public const CAMEL_CASE_HEAD_UP = 1;

    /** @var int Camel case for method names */
    public const CAMEL_CASE_HEAD_DOWN = 2;

    /**
     * Perform initialization required for the string wrapper library.
     */
    public static function initialize()
    {
        static $isInitialized;
        if (!$isInitialized) {
            if (self::hasMBString()) {
                // Set up default encoding
                mb_internal_encoding('utf-8');
                ini_set('default_charset', 'utf-8');
            }
            $isInitialized = true;
        }
    }

    /**
     * Check if server has the mbstring library.
     *
     * @return bool Returns true iff the server supports mbstring functions.
     */
    public static function hasMBString()
    {
        static $hasMBString;
        if (isset($hasMBString)) {
            return $hasMBString;
        }

        // If string overloading is active, it will break many of the
        // native implementations. mbstring.func_overload must be set
        // to 0, 1 or 4 in php.ini (string overloading disabled).
        // Note: Overloading has been deprecated on PHP 7.2
        if (ini_get('mbstring.func_overload') && defined('MB_OVERLOAD_STRING')) {
            $hasMBString = false;
        } else {
            $hasMBString = extension_loaded('mbstring') &&
                function_exists('mb_strlen') &&
                function_exists('mb_strpos') &&
                function_exists('mb_strrpos') &&
                function_exists('mb_substr') &&
                function_exists('mb_strtolower') &&
                function_exists('mb_strtoupper') &&
                function_exists('mb_substr_count') &&
                function_exists('mb_send_mail');
        }
        return $hasMBString;
    }

    //
    // Wrappers for basic string manipulation routines.
    //

    /**
     * @see https://www.php.net/strlen
     *
     * @param string $string Input string
     *
     * @return int String length
     */
    public static function strlen($string)
    {
        return Stringy::create($string)->length();
    }

    /**
     * @see https://www.php.net/strpos
     *
     * @param string $haystack Input haystack to search
     * @param string $needle Input needle to search for
     * @param int $offset Offset at which to begin searching
     *
     * @return int Position of needle within haystack
     */
    public static function strpos($haystack, $needle, $offset = 0)
    {
        return Stringy::create($haystack)->indexOf($needle, $offset);
    }

    /**
     * @see https://www.php.net/strrpos
     *
     * @param string $haystack Haystack to search
     * @param string $needle Needle to search haystack for
     *
     * @return int Last index of Needle in Haystack
     */
    public static function strrpos($haystack, $needle)
    {
        return Stringy::create($haystack)->indexOfLast($needle);
    }

    /**
     * @see https://www.php.net/substr
     *
     * @param string $string Subject to extract substring from
     * @param int $start Position to start from
     * @param int $length Length to extract, or false for entire string from start position
     *
     * @return string Substring of $string
     */
    public static function substr($string, $start, $length = null)
    {
        return (string) Stringy::create($string)->substr($start, $length);
    }

    /**
     * @see https://www.php.net/strtolower
     *
     * @param string $string Input string
     *
     * @return string Lower case version of input string
     */
    public static function strtolower($string)
    {
        return (string) Stringy::create($string)->toLowerCase();
    }

    /**
     * @see https://www.php.net/strtoupper
     *
     * @param string $string Input string
     *
     * @return string Upper case version of input string
     */
    public static function strtoupper($string)
    {
        return (string) Stringy::create($string)->toUpperCase();
    }

    /**
     * @see https://www.php.net/ucfirst
     *
     * @param string $string Input string
     *
     * @return string ucfirst version of input string
     */
    public static function ucfirst($string)
    {
        return (string) Stringy::create($string)->upperCaseFirst();
    }

    /**
     * @see https://www.php.net/substr_count
     *
     * @param string $haystack Input string to search
     * @param string $needle String to search within $haystack for
     *
     * @return int Count of number of times $needle appeared in $haystack
     */
    public static function substr_count($haystack, $needle)
    {
        return Stringy::create($haystack)->countSubstring($needle);
    }

    /**
     * @see https://www.php.net/encode_mime_header
     *
     * @param string $string Input MIME header to encode.
     *
     * @return string Encoded MIME header.
     */
    public static function encode_mime_header($string)
    {
        static::initialize();
        return static::hasMBString()
            ? mb_encode_mimeheader($string, mb_internal_encoding(), 'B', Core::isWindows() ? "\r\n" : "\n")
            : $string;
    }

    //
    // Wrappers for PCRE-compatible regular expression routines.
    // See the php.net documentation for usage.
    //

    /**
     * @see https://www.php.net/preg_quote
     *
     * @param string $string String to quote
     * @param string $delimiter Delimiter for regular expression
     *
     * @return string Quoted equivalent of $string
     */
    public static function regexp_quote($string, $delimiter = '/')
    {
        return preg_quote($string, $delimiter);
    }

    /**
     * @see https://www.php.net/preg_grep
     *
     * @param string $pattern Regular expression
     * @param string $input Input string
     *
     * @return array
     */
    public static function regexp_grep($pattern, $input)
    {
        return preg_grep($pattern . 'u', $input);
    }

    /**
     * @see https://www.php.net/preg_match
     *
     * @param string $pattern Regular expression
     * @param string $subject String to apply regular expression to
     *
     * @return int
     */
    public static function regexp_match($pattern, $subject)
    {
        return preg_match($pattern . 'u', $subject);
    }

    /**
     * @see https://www.php.net/preg_match_get
     *
     * @param string $pattern Regular expression
     * @param string $subject String to apply regular expression to
     * @param array $matches Reference to receive matches
     *
     * @return int|boolean Returns 1 if the pattern matches given subject, 0 if it does not, or FALSE if an error occurred.
     */
    public static function regexp_match_get($pattern, $subject, &$matches)
    {
        return preg_match($pattern . 'u', $subject, $matches);
    }

    /**
     * @see https://www.php.net/preg_match_all
     *
     * @param string $pattern Regular expression
     * @param string $subject String to apply regular expression to
     * @param array $matches Reference to receive matches
     *
     * @return int|boolean Returns number of full matches of given subject, or FALSE if an error occurred.
     */
    public static function regexp_match_all($pattern, $subject, &$matches)
    {
        return preg_match_all($pattern . 'u', $subject, $matches);
    }

    /**
     * @see https://www.php.net/preg_replace
     *
     * @param string $pattern Regular expression
     * @param string $replacement String to replace matches in $subject with
     * @param string $subject String to apply regular expression to
     * @param int $limit Number of replacements to perform, maximum, or -1 for no limit.
     */
    public static function regexp_replace($pattern, $replacement, $subject, $limit = -1)
    {
        return preg_replace($pattern . 'u', $replacement, $subject, $limit);
    }

    /**
     * @see https://www.php.net/preg_replace_callback
     *
     * @param string $pattern Regular expression
     * @param callable $callback PHP callback to generate content to replace matches with
     * @param string $subject String to apply regular expression to
     * @param int $limit Number of replacements to perform, maximum, or -1 for no limit.
     */
    public static function regexp_replace_callback($pattern, $callback, $subject, $limit = -1)
    {
        return preg_replace_callback($pattern . 'u', $callback, $subject, $limit);
    }

    /**
     * @see https://www.php.net/preg_split
     *
     * @param string $pattern Regular expression
     * @param string $subject String to apply regular expression to
     * @param int $limit Number of times to match; -1 for unlimited
     *
     * @return array Resulting string segments
     */
    public static function regexp_split($pattern, $subject, $limit = -1)
    {
        return preg_split($pattern . 'u', $subject, $limit);
    }

    /**
     * @see https://www.php.net/mime_content_type
     *
     * @param string $filename Filename to test.
     * @param string $suggestedExtension Suggested file extension (used for common misconfigurations)
     *
     * @return string Detected MIME type
     */
    public static function mime_content_type($filename, $suggestedExtension = '')
    {
        $result = null;

        if (function_exists('finfo_open')) {
            $fi = & Registry::get('fileInfo', true, null);
            if ($fi === null) {
                $fi = finfo_open(FILEINFO_MIME, Config::getVar('finfo', 'mime_database_path'));
            }
            if ($fi !== false) {
                $result = strtok(finfo_file($fi, $filename), ' ;');
            }
        }

        if (!$result && function_exists('mime_content_type')) {
            $result = mime_content_type($filename);
            // mime_content_type appears to return a charset
            // (erroneously?) in recent versions of PHP5
            if (($i = strpos($result, ';')) !== false) {
                $result = trim(substr($result, 0, $i));
            }
        }

        if (!$result) {
            // Fall back on an external "file" tool
            $f = escapeshellarg($filename);
            $result = trim(`file --brief --mime $f`);
            // Make sure we just return the mime type.
            if (($i = strpos($result, ';')) !== false) {
                $result = trim(substr($result, 0, $i));
            }
        }

        // Check ambiguous mimetypes against extension
        $exploded = explode('.', $filename);
        $ext = array_pop($exploded);
        if ($suggestedExtension) {
            $ext = $suggestedExtension;
        }

        $ambiguities = self::getAmbiguousExtensionsMap();
        if (isset($ambiguities[strtolower($ext . ':' . $result)])) {
            $result = $ambiguities[strtolower($ext . ':' . $result)];
        }

        return $result;
    }

    /**
     * @return string[]
     * @brief overrides for ambiguous mime types returned by finfo
     * SUGGESTED_EXTENSION:DETECTED_MIME_TYPE => OVERRIDE_MIME_TYPE
     */
    public static function getAmbiguousExtensionsMap()
    {
        return [
            'html:text/xml' => 'text/html',
            'css:text/x-c' => 'text/css',
            'css:text/plain' => 'text/css',
            'csv:text/plain' => 'text/csv',
            'xlsx:application/zip' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'xltx:application/zip' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.template',
            'potx:application/zip' => 'application/vnd.openxmlformats-officedocument.presentationml.template',
            'ppsx:application/zip' => 'application/vnd.openxmlformats-officedocument.presentationml.slideshow',
            'pptx:application/zip' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
            'sldx:application/zip' => 'application/vnd.openxmlformats-officedocument.presentationml.slide',
            'docm:application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'application/vnd.ms-word.document.macroEnabled.12',
            'docx:application/zip' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'dotx:application/zip' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.template',
            'wma:video/x-ms-asf' => 'audio/x-ms-wma',
            'wmv:video/x-ms-asf' => 'video/x-ms-wmv',
        ];
    }

    /**
     * Strip unsafe HTML from the input text. Covers XSS attacks like scripts,
     * onclick(...) attributes, javascript: urls, and special characters.
     *
     * @param string $input input string
     *
     * @return string
     */
    public static function stripUnsafeHtml($input)
    {
        static $purifier;
        if (!isset($purifier)) {
            $config = \HTMLPurifier_Config::createDefault();
            $config->set('Core.Encoding', 'utf-8');
            $config->set('HTML.Doctype', 'HTML 4.01 Transitional');
            $config->set('HTML.Allowed', Config::getVar('security', 'allowed_html'));
            $config->set('Cache.SerializerPath', 'cache');
            $purifier = new \HTMLPurifier($config);
        }
        return $purifier->purify($input);
    }

    /**
     * Convert limited HTML into a string.
     *
     * @param string $html
     *
     * @return string
     */
    public static function html2text($html)
    {
        $html = self::regexp_replace('/<[\/]?p>/', "\n", $html);
        $html = self::regexp_replace('/<li>/', '&bull; ', $html);
        $html = self::regexp_replace('/<\/li>/', "\n", $html);
        $html = self::regexp_replace('/<br[ ]?[\/]?>/', "\n", $html);
        $html = html_entity_decode(strip_tags($html), ENT_COMPAT, 'UTF-8');
        return $html;
    }

    /**
     * Joins two title string fragments (in $fields) either with a
     * space or a colon.
     *
     * @param array $fields
     *
     * @return string the joined string
     */
    public static function concatTitleFields($fields)
    {
        // Set the characters that will avoid the use of
        // a semicolon between title and subtitle.
        $avoidColonChars = ['?', '!', '/', '&'];

        // if the first field ends in a character in $avoidColonChars,
        // concat with a space, otherwise use a colon.
        // Check for any of these characters in
        // the last position of current full title value.
        if (in_array(substr($fields[0], -1, 1), $avoidColonChars)) {
            $fullTitle = join(' ', $fields);
        } else {
            $fullTitle = join(': ', $fields);
        }

        return $fullTitle;
    }

    /**
     * Transform "handler-class" to "HandlerClass"
     * and "my-op" to "myOp".
     *
     * @param string $string input string
     * @param int $type which kind of camel case?
     *
     * @return string the string in camel case
     */
    public static function camelize($string, $type = self::CAMEL_CASE_HEAD_UP)
    {
        assert($type == static::CAMEL_CASE_HEAD_UP || $type == static::CAMEL_CASE_HEAD_DOWN);

        // Transform "handler-class" to "HandlerClass" and "my-op" to "MyOp"
        $string = implode(array_map('ucfirst_codesafe', explode('-', $string)));

        // Transform "MyOp" to "myOp"
        if ($type == static::CAMEL_CASE_HEAD_DOWN) {
            $string = strtolower_codesafe(substr($string, 0, 1)) . substr($string, 1);
        }

        return $string;
    }

    /**
     * Transform "HandlerClass" to "handler-class"
     * and "myOp" to "my-op".
     *
     * @param string $string
     *
     * @return string
     */
    public static function uncamelize($string)
    {
        assert(!empty($string));

        // Transform "myOp" to "MyOp"
        $string = ucfirst_codesafe($string);

        // Insert hyphens between words and return the string in lowercase
        $words = [];
        self::regexp_match_all('/[A-Z][a-z0-9]*/', $string, $words);
        assert(isset($words[0]) && !empty($words[0]) && strlen(implode('', $words[0])) == strlen($string));
        return strtolower_codesafe(implode('-', $words[0]));
    }

    /**
     * Get a letter $steps places after 'A'
     *
     * @param int $steps
     *
     * @return string Letter
     */
    public static function enumerateAlphabetically($steps)
    {
        return chr(ord('A') + $steps);
    }

    /**
     * Create a new UUID (version 4)
     *
     * @return string
     */
    public static function generateUUID()
    {
        mt_srand((float)microtime() * 10000);
        $charid = strtoupper(md5(uniqid(rand(), true)));
        $hyphen = '-';
        $uuid = substr($charid, 0, 8) . $hyphen
                . substr($charid, 8, 4) . $hyphen
                . '4' . substr($charid, 13, 3) . $hyphen
                . strtoupper(dechex(hexdec(ord(substr($charid, 16, 1))) % 4 + 8)) . substr($charid, 17, 3) . $hyphen
                . substr($charid, 20, 12);
        return $uuid;
    }

    /**
     * Matches each symbol of PHP strftime format string
     * to jQuery Datepicker widget date format.
     *
     * @param string $phpFormat
     *
     * @return string
     */
    public static function dateformatPHP2JQueryDatepicker($phpFormat)
    {
        $symbols = [
            // Day
            'a' => 'D',  // date() format 'D'
            'A' => 'DD', // date() format 'DD'
            'd' => 'dd', // date() format 'd'
            'e' => 'd',  // date() format 'j'
            'j' => 'oo', // date() format none
            'u' => '',   // date() format 'N'
            'w' => '',   // date() format 'w'

            // Week
            'U' => '',   // date() format none
            'V' => '',   // date() format none
            'W' => '',   // date() format 'W'

            // Month
            'b' => 'M',  // date() format 'M'
            'h' => 'M',  // date() format 'M'
            'B' => 'MM', // date() format 'F'
            'm' => 'mm', // date() format 'm'

            // Year
            'C' => '',   // date() format none
            'g' => 'y',  // date() format none
            'G' => 'yy', // date() format 'o'
            'y' => 'y',  // date() format 'y'
            'Y' => 'yy', // date() format 'Y'

            // Time
            'H' => '',   // date() format 'H'
            'k' => '',   // date() format none
            'I' => '',   // date() format 'h'
            'l' => '',   // date() format 'g'
            'P' => '',   // date() format 'a'
            'p' => '',   // date() format 'A'
            'M' => '',   // date() format 'i'
            'S' => '',   // date() format 's'
            's' => '',   // date() format 'u'

            // Timezone
            'z' => '',   // date() format 'O'
            'Z' => '',   // date() format 'T'

            // Full Date/Time
            'r' => '',   // date() format none
            'R' => '',   // date() format none
            'X' => '',   // date() format none
            'D' => '',   // date() format none
            'F' => '',   // date() format none
            'x' => '',   // date() format none
            'c' => '',   // date() format none

            // Other
            '%' => ''
        ];

        $datepickerFormat = '';
        $escaping = false;

        for ($i = 0; $i < strlen($phpFormat); $i++) {
            $char = $phpFormat[$i];
            if ($char === '\\') {
                $i++;
                $datepickerFormat .= $escaping ? $phpFormat[$i] : '\'' . $phpFormat[$i];

                $escaping = true;
            } else {
                if ($escaping) {
                    $datepickerFormat .= "'";
                    $escaping = false;
                }

                $datepickerFormat .= $symbols[$char] ?? $char;
            }
        }

        return $datepickerFormat;
    }
}

if (!PKP_STRICT_MODE) {
    class_alias('\PKP\core\PKPString', '\PKPString');
}

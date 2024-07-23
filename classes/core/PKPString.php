<?php

/**
 * @file classes/core/PKPString.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PKPString
 *
 * @ingroup core
 *
 * @brief String manipulation wrapper class.
 *
 */

namespace PKP\core;

use HTMLPurifier;
use HTMLPurifier_Config;
use PKP\config\Config;

class PKPString
{
    /** @var int Camel case for class names */
    public const CAMEL_CASE_HEAD_UP = 1;

    /** @var int Camel case for method names */
    public const CAMEL_CASE_HEAD_DOWN = 2;

    /**
     * Perform initialization required for the string wrapper library.
     */
    public static function initialize(): void
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
    public static function hasMBString(): bool
    {
        static $hasMBString;
        if (isset($hasMBString)) {
            return $hasMBString;
        }

        $hasMBString = extension_loaded('mbstring') &&
            function_exists('mb_strlen') &&
            function_exists('mb_strpos') &&
            function_exists('mb_strrpos') &&
            function_exists('mb_substr') &&
            function_exists('mb_strtolower') &&
            function_exists('mb_strtoupper') &&
            function_exists('mb_substr_count') &&
            function_exists('mb_send_mail');

        return $hasMBString;
    }

    /**
     * @see https://www.php.net/mime_content_type
     *
     * @param $suggestedExtension Suggested file extension (used for common misconfigurations)
     */
    public static function mime_content_type(string $filename, string $suggestedExtension = ''): string
    {
        $result = null;

        if (function_exists('finfo_open')) {
            $fi = &Registry::get('fileInfo', true, null);
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
     * @brief overrides for ambiguous mime types returned by finfo
     * SUGGESTED_EXTENSION:DETECTED_MIME_TYPE => OVERRIDE_MIME_TYPE
     */
    public static function getAmbiguousExtensionsMap(): array
    {
        return [
            'html:text/xml' => 'text/html',
            'css:text/x-c' => 'text/css',
            'css:text/plain' => 'text/css',
            'csv:text/plain' => 'text/csv',
            'js:text/plain' => 'text/javascript',
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
     * @param string|null   $input      input string
     * @param string        $configKey  The config section key['allowed_html', 'allowed_title_html']
     *
     */
    public static function stripUnsafeHtml(?string $input, string $configKey = 'allowed_html'): string
    {
        if ($input === null) {
            return '';
        }

        static $purifier;
        if (!isset($purifier)) {
            $config = HTMLPurifier_Config::createDefault();
            $config->set('Core.Encoding', 'utf-8');
            $config->set('HTML.Doctype', 'HTML 4.01 Transitional');
            $config->set('HTML.Allowed', Config::getVar('security', $configKey));
            $config->set('Cache.SerializerPath', 'cache');
            $purifier = new HTMLPurifier($config);
        }
        return $purifier->purify((string) $input);
    }

    /**
     * Convert limited HTML into a string.
     *
     * @param string $html
     *
     */
    public static function html2text($html): string
    {
        $html = preg_replace('/<[\/]?p>/u', "\n", $html);
        $html = preg_replace('/<li>/u', '&bull; ', $html);
        $html = preg_replace('/<\/li>/u', "\n", $html);
        $html = preg_replace('/<br[ ]?[\/]?>/u', "\n", $html);
        $html = html_entity_decode(strip_tags($html), ENT_COMPAT, 'UTF-8');
        return $html;
    }

    /**
     * Joins two title string fragments (in $fields) either with a
     * space or a colon.
     */
    public static function concatTitleFields(array $fields): string
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
     * Transform "handler-class" to "HandlerClass" and "my-op" to "myOp".
     */
    public static function camelize(string $string, int $type = self::CAMEL_CASE_HEAD_UP): string
    {
        assert($type == static::CAMEL_CASE_HEAD_UP || $type == static::CAMEL_CASE_HEAD_DOWN);

        // Transform "handler-class" to "HandlerClass" and "my-op" to "MyOp"
        $string = implode(array_map(ucfirst(...), explode('-', $string)));

        switch ($type) {
            case static::CAMEL_CASE_HEAD_DOWN:
                // Transform "MyOp" to "myOp"
                $string = strtolower(substr($string, 0, 1)) . substr($string, 1);
                break;
            case self::CAMEL_CASE_HEAD_UP:
                break;
            default: throw new \Exception('Invalid camelization type specified!');
        }

        return $string;
    }

    /**
     * Transform "HandlerClass" to "handler-class" and "myOp" to "my-op".
     */
    public static function uncamelize(string $string): string
    {
        // Transform "myOp" to "MyOp"
        $string = ucfirst($string);

        // Insert hyphens between words and return the string in lowercase
        $words = [];
        preg_match_all('/[A-Z][a-z0-9]*/u', $string, $words);
        return strtolower(implode('-', $words[0]));
    }

    /**
     * Create a new UUID (version 4)
     */
    public static function generateUUID(): string
    {
        $charid = strtoupper(md5(uniqid(random_int(0, PHP_INT_MAX), true)));
        $hyphen = '-';
        $uuid = substr($charid, 0, 8) . $hyphen
                . substr($charid, 8, 4) . $hyphen
                . '4' . substr($charid, 13, 3) . $hyphen
                . strtoupper(dechex(hexdec(ord(substr($charid, 16, 1))) % 4 + 8)) . substr($charid, 17, 3) . $hyphen
                . substr($charid, 20, 12);
        return $uuid;
    }

    /**
     * Get a mapping from strftime to DateTime::format formatting equivalents.
     * Old format: https://www.php.net/manual/en/function.strftime.php
     * New format: https://www.php.net/manual/en/datetime.format.php
     *
     * Introduced in 3.4.0; remove this function (and calls to it) after this is distributed
     * in an LTS release.
     */
    public static function getStrftimeConversion(): array
    {
        return [
            '%%' => '%', '%h' => 'M', '%d' => 'd', '%a' => 'D',
            '%e' => 'j', '%A' => 'l', '%u' => 'N', '%w' => 'w',
            '%U' => 'W', '%B' => 'F', '%m' => 'm', '%b' => 'M',
            '%Y' => 'Y', '%y' => 'y', '%P' => 'a', '%p' => 'A',
            '%l' => 'g', '%k' => 'G', '%I' => 'h', '%H' => 'H',
            '%M' => 'i', '%S' => 's', '%Z' => 'T',
        ];
    }

    /**
     * Convert any strftime-based datetime formatting into DateTime::format equivalent.
     * Passes through any strings that are already in the new format without modification.
     * Old format: https://www.php.net/manual/en/function.strftime.php
     * New format: https://www.php.net/manual/en/datetime.format.php
     *
     * Introduced in 3.4.0; remove this function (and calls to it) after this is distributed
     * in an LTS release.
     */
    public static function convertStrftimeFormat(string $format): string
    {
        // Following the lead of Smarty's date_format modifier, check the
        // format string for "%" characters. If found, attempt to convert.
        // We don't expect date/time formats to contain other uses of %.
        if (strstr($format, '%')) {
            if (Config::getVar('debug', 'deprecation_warnings')) {
                throw new \Exception('Deprecated use of strftime-based date format.');
            }
            $format = strtr($format, self::getStrftimeConversion());
        }
        return $format;
    }

    /**
     * Matches each symbol of PHP date format string to jQuery Datepicker widget date format.
     */
    public static function dateformatPHP2JQueryDatepicker(string $phpFormat): string
    {
        return str_replace(
            ['d',  'j', 'l',  'm',  'n', 'F',  'Y'],
            ['dd', 'd', 'DD', 'mm', 'm', 'MM', 'yy'],
            $phpFormat
        );
    }

    /**
     * Get the word count of a string
     */
    public static function getWordCount(string $str): int
    {
        return count(preg_split('/\s+/', trim(str_replace('&nbsp;', ' ', strip_tags($str)))));
    }
}

if (!PKP_STRICT_MODE) {
    class_alias('\PKP\core\PKPString', '\PKPString');
}

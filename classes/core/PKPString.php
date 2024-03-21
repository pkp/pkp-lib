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

use Carbon\Carbon;
use DateTimeInterface;
use Exception;
use Illuminate\Support\Str;
use IntlDateFormatter;
use PKP\config\Config;
use PKP\facades\Locale;
use Symfony\Component\HtmlSanitizer\HtmlSanitizer;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerConfig;

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
     * @param string $filename Filename to test.
     * @param string $suggestedExtension Suggested file extension (used for common misconfigurations)
     *
     * @return string Detected MIME type
     */
    public static function mime_content_type($filename, $suggestedExtension = '')
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
     * @return string[]
     *
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

        static $caches;

        if (!isset($caches[$configKey])) {

            $config = (new HtmlSanitizerConfig())
                ->allowLinkSchemes(['https', 'http', 'mailto'])
                ->allowMediaSchemes(['https', 'http']);

            $allowedTagToAttributeMap = Str::of(Config::getVar('security', $configKey))
                ->explode(',')
                ->mapWithKeys(function (string $allowedTagWithAttr) {

                    // Extract the tag itself (e.g. div, p, a ...)
                    preg_match('/\[[^][]+]\K|\w+/', $allowedTagWithAttr, $matches);
                    $allowedTag = collect($matches)->first();

                    // Extract the attributes associated with tag (e.g. class, href ...)
                    preg_match("/\[([^\]]*)\]/", $allowedTagWithAttr, $matches);
                    $allowedAttributes = collect($matches)->last();

                    if($allowedTag) {
                        return [
                            $allowedTag => Str::of($allowedAttributes)
                                ->explode('|')
                                ->filter()
                                ->toArray()
                        ];
                    }

                    return [];
                })
                ->each(function (array $attributes, string $tag) use (&$config) {
                    $config = $config->allowElement($tag, $attributes);
                });

            $caches[$configKey] = [
                'allowedTagToAttributeMap' => $allowedTagToAttributeMap,
                'sanitizer' => new HtmlSanitizer($config),
            ];
        }

        // need to apply html_entity_decode as sanitizer apply htmlentities internally for special chars
        return html_entity_decode(
            $caches[$configKey]['sanitizer']->sanitize(
                strip_tags($input, $caches[$configKey]['allowedTagToAttributeMap']->keys()->toArray())
            )
        );
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
        preg_match_all('/[A-Z][a-z0-9]*/u', $string, $words);
        assert(isset($words[0]) && !empty($words[0]) && strlen(implode('', $words[0])) == strlen($string));
        return strtolower_codesafe(implode('-', $words[0]));
    }

    /**
     * Create a new UUID (version 4)
     *
     * @return string
     */
    public static function generateUUID()
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
    private static function convertStrftimeFormat(string $format): string
    {
        // Following the lead of Smarty's date_format modifier, check the
        // format string for "%" characters. If found, attempt to convert.
        // We don't expect date/time formats to contain other uses of %.
        if (strstr($format, '%')) {
            if (Config::getVar('debug', 'deprecation_warnings')) {
                trigger_error('Deprecated use of strftime-based date format.');
            }
            $format = strtr($format, self::getStrftimeConversion());
        }
        return $format;
    }

    /**
     * Matches each symbol of PHP date format string
     * to jQuery Datepicker widget date format.
     *
     * @param string $phpFormat
     *
     * @return string
     */
    public static function dateformatPHP2JQueryDatepicker($phpFormat)
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

    /**
     * Convert php date format to ICU date format 
     */
    private static function phpToIcuFormat(string $phpFormat): string 
    {
        $mapping = [
            // Day
            'd' => 'dd',   // Day of the month, 2 digits with leading zeros
            'D' => 'EEE',  // A textual representation of a day, three letters
            'j' => 'd',    // Day of the month without leading zeros
            'l' => 'EEEE', // A full textual representation of the day of the week
            'N' => 'e',    // ISO-8601 numeric representation of the day of the week
            'S' => '',     // English ordinal suffix for the day of the month, 2 characters, no ICU equivalent
            'w' => 'e',    // Numeric representation of the day of the week
            'z' => 'D',    // The day of the year (starting from 0)
            // Week
            'W' => 'w',    // ISO-8601 week number of year
            // Month
            'F' => 'MMMM', // A full textual representation of a month
            'm' => 'MM',   // Numeric representation of a month, with leading zeros
            'M' => 'MMM',  // A short textual representation of a month, three letters
            'n' => 'M',    // Numeric representation of a month, without leading zeros
            't' => '',     // Number of days in the given month, no direct ICU equivalent
            // Year
            'L' => '',     // Whether it's a leap year, no direct ICU equivalent
            'o' => 'Y',    // ISO-8601 week-numbering year
            'Y' => 'yyyy', // A full numeric representation of a year, 4 digits
            'y' => 'yy',   // A two digit representation of a year
            // Time
            'a' => 'a',    // Lowercase Ante meridiem and Post meridiem, am or pm
            'A' => 'a',    // Uppercase Ante meridiem and Post meridiem, AM or PM
            'B' => '',     // Swatch Internet time, no ICU equivalent
            'g' => 'h',    // 12-hour format of an hour without leading zeros
            'G' => 'H',    // 24-hour format of an hour without leading zeros
            'h' => 'hh',   // 12-hour format of an hour with leading zeros
            'H' => 'HH',   // 24-hour format of an hour with leading zeros
            'i' => 'mm',   // Minutes with leading zeros
            's' => 'ss',   // Seconds with leading zeros
            'v' => '',     // Milliseconds, no direct ICU equivalent
            // Timezone
            'e' => 'zzzz', // Timezone identifier
            'I' => '',     // Whether or not the date is in daylight saving time, no ICU equivalent
            'O' => 'xx',   // Difference to Greenwich time (GMT) without colon between hours and minutes
            'P' => 'xxx',  // Difference to Greenwich time (GMT) with colon between hours and minutes
            'T' => 'z',    // Timezone abbreviation
            'Z' => 'X',    // Timezone offset in seconds
            // Full Date/Time
            'c' => 'yyyy-MM-dd\'T\'HH:mm:ssXXX', // ISO 8601 date
            'r' => 'r',    // RFC 2822 formatted date
            'U' => 'U',    // Seconds since the Unix Epoch (January 1 1970 00:00:00 GMT)
        ];

        $icuFormat = strtr($phpFormat, $mapping);
        
        return $icuFormat;
    }

    /**
     * Add @calendar suffix to format for special cases
     */
    private static function getLocaleWithCalendar($locale) 
    {
        // Mapping of locales to their default calendar systems
        $localeCalendars = [
            'th' => '@calendar=buddhist', // Thai
            'zh_CN' => '@calendar=chinese', // Chinese (Simplified)
            'zh_TW' => '@calendar=roc', // Taiwanese
            'am' => '@calendar=ethiopic', // Amharic
            'ar_SA' => '@calendar=islamic-umalqura', // Arabic (Saudi Arabia)
            'he' => '@calendar=hebrew', // Hebrew
            'fa' => '@calendar=persian', // Persian
            'ja_JP' => '@calendar=japanese', // Japanese
        ];

        if (array_key_exists($locale, $localeCalendars)) {
            $locale = $locale . $localeCalendars[$locale];
        }

        return $locale;
    }

    /**
     * Convert any datetime object to a localized string taking into account the 
     * datetime format.
     * 
     * @throws \Exception General exception for potential errors during the date format
     */
    public static function getLocalizedDate(string|int|DateTimeInterface|null $date, string $format, ?string $locale = null): ?string
    {
        if (empty($date)) {
            return null;
        }

        $format = self::convertStrftimeFormat($format);
        $icuFormat = self::phpToIcuFormat($format);

        $locale = $locale ?? Locale::getLocale();

        $localeWithCalendar = self::getLocaleWithCalendar($locale);

        try {
            if (!($date instanceof Carbon)) {
                $date = Carbon::parse($date);
            }

            $formatter = new IntlDateFormatter(
                $localeWithCalendar,
                IntlDateFormatter::FULL,
                IntlDateFormatter::FULL,
                $date->getTimezone(),
                // This is overridden by the locale string $localeWithCalendar if a calendar (@calendar=) is specified
                IntlDateFormatter::GREGORIAN, 
                $icuFormat
            );

            return $formatter->format($date->getTimestamp());
        } catch (Exception $e) {
            throw $e;
        }
    }
}

if (!PKP_STRICT_MODE) {
    class_alias('\PKP\core\PKPString', '\PKPString');
}

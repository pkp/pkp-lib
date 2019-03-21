<?php

/**
 * @file classes/core/PKPString.inc.php
 *
 * Copyright (c) 2014-2018 Simon Fraser University
 * Copyright (c) 2000-2018 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class PKPString
 * @ingroup core
 *
 * @brief String manipulation wrapper class.
 *
 */


/*
 * Perl-compatibile regular expression (PCRE) constants:
 * These are defined application-wide for consistency
 */

/*
 * RFC-2396 URIs
 *
 * Thanks to the PEAR Validation package (Tomas V.V.Cox <cox@idecnet.com>,
 * Pierre-Alain Joye <pajoye@php.net>, Amir Mohammad Saied <amir@php.net>)
 *
 * Originally published under the "New BSD License"
 * http://www.opensource.org/licenses/bsd-license.php
 */
define('PCRE_URI', '(?:([a-z][-+.a-z0-9]*):)?' .						// Scheme
		   '(?://' .
		   '(?:((?:%[0-9a-f]{2}|[-a-z0-9_.!~*\'();:\&=+$,])*)@)?' .			// User
		   '(?:((?:[a-z0-9](?:[-a-z0-9]*[a-z0-9])?\.)*[a-z](?:[a-z0-9]+)?\.?)' .	// Hostname
		   '|([0-9]{1,3}(?:\.[0-9]{1,3}){3}))' .					// IP Address
		   '(?::([0-9]*))?)' .								// Port
		   '((?:/(?:%[0-9a-f]{2}|[-a-z0-9_.!~*\'():@\&=+$,;])*)*/?)?' .			// Path
		   '(?:\?([^#]*))?' .								// Query String
		   '(?:\#((?:%[0-9a-f]{2}|[-a-z0-9_.!~*\'();/?:@\&=+$,])*))?');			// Fragment

// Two different types of camel case: one for class names and one for method names
define ('CAMEL_CASE_HEAD_UP', 0x01);
define ('CAMEL_CASE_HEAD_DOWN', 0x02);

class PKPString {
	/**
	 * Perform initialization required for the string wrapper library.
	 * @return null
	 */
	static function init() {
		$clientCharset = strtolower_codesafe(Config::getVar('i18n', 'client_charset'));

		// Check if mbstring is installed
		if (self::hasMBString() && !defined('ENABLE_MBSTRING')) {
			// mbstring routines are available
			define('ENABLE_MBSTRING', true);

			// Set up required ini settings for mbstring
			// FIXME Do any other mbstring settings need to be set?
			mb_internal_encoding($clientCharset);
			mb_substitute_character('63');		// question mark
		}

		// Define modifier to be used in regexp_* routines
		// FIXME Should non-UTF-8 encodings be supported with mbstring?
		if (!defined('PCRE_UTF8')) {
			if ($clientCharset == 'utf-8' && self::hasPCREUTF8()) {
				define('PCRE_UTF8', 'u');
			} else {
				define('PCRE_UTF8', '');
			}
		}
	}

	/**
	 * Check if server has the mbstring library.
	 * @return boolean Returns true iff the server supports mbstring functions.
	 */
	static function hasMBString() {
		static $hasMBString;
		if (isset($hasMBString)) return $hasMBString;

		// If string overloading is active, it will break many of the
		// native implementations. mbstring.func_overload must be set
		// to 0, 1 or 4 in php.ini (string overloading disabled).
		if (ini_get('mbstring.func_overload') && defined('MB_OVERLOAD_STRING')) {
			$hasMBString = false;
		} else {
			$hasMBString = (
				extension_loaded('mbstring') &&
				function_exists('mb_strlen') &&
				function_exists('mb_strpos') &&
				function_exists('mb_strrpos') &&
				function_exists('mb_substr') &&
				function_exists('mb_strtolower') &&
				function_exists('mb_strtoupper') &&
				function_exists('mb_substr_count') &&
				function_exists('mb_send_mail')
			);
		}
		return $hasMBString;
	}

	/**
	 * Check if server supports the PCRE_UTF8 modifier.
	 * @return boolean True iff the server supports the PCRE_UTF8 modifier.
	 */
	static function hasPCREUTF8() {
		// The PCRE_UTF8 modifier is only supported on PHP >= 4.1.0 (*nix) or PHP >= 4.2.3 (win32)
		// Evil check to see if PCRE_UTF8 is supported
		if (@preg_match('//u', '')) {
			return true;
		} else {
			return false;
		}
	}

	//
	// Wrappers for basic string manipulation routines.
	//

	/**
	 * @see http://ca.php.net/manual/en/function.strlen.php
	 * @param $string string Input string
	 * @return int String length
	 */
	static function strlen($string) {
		return Stringy\Stringy::create($string)->length();
	}

	/**
	 * @see http://ca.php.net/manual/en/function.strpos.php
	 * @param $haystack string Input haystack to search
	 * @param $needle string Input needle to search for
	 * @param $offset int Offset at which to begin searching
	 * @return int Position of needle within haystack
	 */
	static function strpos($haystack, $needle, $offset = 0) {
		return Stringy\Stringy::create($haystack)->indexOf($needle, $offset);
	}

	/**
	 * @see http://ca.php.net/manual/en/function.strrpos.php
	 * @param $haystack string Haystack to search
	 * @param $needle string Needle to search haystack for
	 */
	static function strrpos($haystack, $needle) {
		return Stringy\Stringy::create($haystack)->indexOfLast($needle);
	}

	/**
	 * @see http://ca.php.net/manual/en/function.substr.php
	 * @param $string string Subject to extract substring from
	 * @param $start int Position to start from
	 * @param $length int Length to extract, or false for entire string from start position
	 * @return string Substring of $string
	 */
	static function substr($string, $start, $length = null) {
		return Stringy\Stringy::create($string)->substr($start, $length);
	}

	/**
	 * @see http://ca.php.net/manual/en/function.strtolower.php
	 * @param $string string Input string
	 * @return string Lower case version of input string
	 */
	static function strtolower($string) {
		return Stringy\Stringy::create($string)->toLowerCase();
	}

	/**
	 * @see http://ca.php.net/manual/en/function.strtoupper.php
	 * @param $string string Input string
	 * @return string Upper case version of input string
	 */
	static function strtoupper($string) {
		return Stringy\Stringy::create($string)->toUpperCase();
	}

	/**
	 * @see http://ca.php.net/manual/en/function.ucfirst.php
	 * @param $string string Input string
	 * @return string ucfirst version of input string
	 */
	static function ucfirst($string) {
		return Stringy\Stringy::create($string)->upperCaseFirst();
	}

	/**
	 * @see http://ca.php.net/manual/en/function.substr_count.php
	 * @param $haystack string Input string to search
	 * @param $needle string String to search within $haystack for
	 * @return int Count of number of times $needle appeared in $haystack
	 */
	static function substr_count($haystack, $needle) {
		return Stringy\Stringy::create($haystack)->countSubstring($needle);
	}

	/**
	 * @see http://ca.php.net/manual/en/function.encode_mime_header.php
	 * @param $string string Input MIME header to encode.
	 * @return string Encoded MIME header.
	 */
	static function encode_mime_header($string) {
		if (defined('ENABLE_MBSTRING')) {
			return mb_encode_mimeheader($string, mb_internal_encoding(), 'B', MAIL_EOL);
		}  else {
			return $string;
		}
	}

	//
	// Wrappers for PCRE-compatible regular expression routines.
	// See the php.net documentation for usage.
	//

	/**
	 * @see http://ca.php.net/manual/en/function.regexp_quote.php
	 * @param $string string String to quote
	 * @param $delimiter string Delimiter for regular expression
	 * @return string Quoted equivalent of $string
	 */
	static function regexp_quote($string, $delimiter = '/') {
		return preg_quote($string, $delimiter);
	}

	/**
	 * @see http://ca.php.net/manual/en/function.regexp_grep.php
	 * @param $pattern string Regular expression
	 * @param $input string Input string
	 * @return array
	 */
	static function regexp_grep($pattern, $input) {
		return preg_grep($pattern . PCRE_UTF8, $input);
	}

	/**
	 * @see http://ca.php.net/manual/en/function.regexp_match.php
	 * @param $pattern string Regular expression
	 * @param $subject string String to apply regular expression to
	 * @return int
	 */
	static function regexp_match($pattern, $subject) {
		return preg_match($pattern . PCRE_UTF8, $subject);
	}

	/**
	 * @see http://ca.php.net/manual/en/function.regexp_match_get.php
	 * @param $pattern string Regular expression
	 * @param $subject string String to apply regular expression to
	 * @param $matches array Reference to receive matches
	 * @return int|boolean Returns 1 if the pattern matches given subject, 0 if it does not, or FALSE if an error occurred.
	 */
	static function regexp_match_get($pattern, $subject, &$matches) {
		return preg_match($pattern . PCRE_UTF8, $subject, $matches);
	}

	/**
	 * @see http://ca.php.net/manual/en/function.regexp_match_all.php
	 * @param $pattern string Regular expression
	 * @param $subject string String to apply regular expression to
	 * @param $matches array Reference to receive matches
	 * @return int|boolean Returns number of full matches of given subject, or FALSE if an error occurred.
	 */
	static function regexp_match_all($pattern, $subject, &$matches) {
		return preg_match_all($pattern . PCRE_UTF8, $subject, $matches);
	}

	/**
	 * @see http://ca.php.net/manual/en/function.regexp_replace.php
	 * @param $pattern string Regular expression
	 * @param $replacement string String to replace matches in $subject with
	 * @param $subject string String to apply regular expression to
	 * @param $limit int Number of replacements to perform, maximum, or -1 for no limit.
	 * @return mixed
	 */
	static function regexp_replace($pattern, $replacement, $subject, $limit = -1) {
		return preg_replace($pattern . PCRE_UTF8, $replacement, $subject, $limit);
	}

	/**
	 * @see http://ca.php.net/manual/en/function.regexp_replace_callback.php
	 * @param $pattern string Regular expression
	 * @param $callback callback PHP callback to generate content to replace matches with
	 * @param $subject string String to apply regular expression to
	 * @param $limit int Number of replacements to perform, maximum, or -1 for no limit.
	 * @return mixed
	 */
	static function regexp_replace_callback($pattern, $callback, $subject, $limit = -1) {
		return preg_replace_callback($pattern . PCRE_UTF8, $callback, $subject, $limit);
	}

	/**
	 * @see http://ca.php.net/manual/en/function.regexp_split.php
	 * @param $pattern string Regular expression
	 * @param $subject string String to apply regular expression to
	 * @param $limit int Number of times to match; -1 for unlimited
	 * @return array Resulting string segments
	 */
	static function regexp_split($pattern, $subject, $limit = -1) {
		return preg_split($pattern . PCRE_UTF8, $subject, $limit);
	}

	/**
	 * @see http://ca.php.net/manual/en/function.mime_content_type.php
	 * @param $filename string Filename to test.
	 * @param $suggestedExtension string Suggested file extension (used for common misconfigurations)
	 * @return string Detected MIME type
	 */
	static function mime_content_type($filename, $suggestedExtension = '') {
		$result = null;

		if (function_exists('finfo_open')) {
			$fi =& Registry::get('fileInfo', true, null);
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
		$exploded = explode('.',$filename);
		$ext = array_pop($exploded);
		if ($suggestedExtension) {
			$ext = $suggestedExtension;
		}
		// SUGGESTED_EXTENSION:DETECTED_MIME_TYPE => OVERRIDE_MIME_TYPE
		$ambiguities = array(
			'css:text/x-c' => 'text/css',
			'css:text/plain' => 'text/css',
			'xlsx:application/zip' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
			'xltx:application/zip' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.template',
			'potx:application/zip' => 'application/vnd.openxmlformats-officedocument.presentationml.template',
			'ppsx:application/zip' => 'application/vnd.openxmlformats-officedocument.presentationml.slideshow',
			'pptx:application/zip' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
			'sldx:application/zip' => 'application/vnd.openxmlformats-officedocument.presentationml.slide',
			'docx:application/zip' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
			'dotx:application/zip' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.template',
		);
		if (isset($ambiguities[strtolower($ext.':'.$result)])) {
			$result = $ambiguities[strtolower($ext.':'.$result)];
		}

		return $result;
	}

	/**
	 * Strip unsafe HTML from the input text. Covers XSS attacks like scripts,
	 * onclick(...) attributes, javascript: urls, and special characters.
	 * @param $input string input string
	 * @return string
	 */
	static function stripUnsafeHtml($input) {
		static $purifier;
		if (!isset($purifier)) {
			$config = HTMLPurifier_Config::createDefault();
			$config->set('Core.Encoding', Config::getVar('i18n', 'client_charset'));
			$config->set('HTML.Doctype', 'HTML 4.01 Transitional');
			$config->set('HTML.Allowed', Config::getVar('security', 'allowed_html'));
			$config->set('Cache.SerializerPath', 'cache');
			$purifier = new HTMLPurifier($config);
		}
		return $purifier->purify($input);
	}

	/**
	 * Convert limited HTML into a string.
	 * @param $html string
	 * @return string
	 */
	static function html2text($html) {
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
	 * @param $fields array
	 * @return string the joined string
	 */
	static function concatTitleFields($fields) {
		// Set the characters that will avoid the use of
		// a semicolon between title and subtitle.
		$avoidColonChars = array('?', '!', '/', '&');

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
	 * @param $string input string
	 * @param $type which kind of camel case?
	 * @return string the string in camel case
	 */
	static function camelize($string, $type = CAMEL_CASE_HEAD_UP) {
		assert($type == CAMEL_CASE_HEAD_UP || $type == CAMEL_CASE_HEAD_DOWN);

		// Transform "handler-class" to "HandlerClass" and "my-op" to "MyOp"
		$string = str_replace(' ', '', ucwords(str_replace('-', ' ', $string)));

		// Transform "MyOp" to "myOp"
		if ($type == CAMEL_CASE_HEAD_DOWN) {
			// lcfirst() is PHP>5.3, so use workaround
			$string = strtolower(substr($string, 0, 1)).substr($string, 1);
		}

		return $string;
	}

	/**
	 * Transform "HandlerClass" to "handler-class"
	 * and "myOp" to "my-op".
	 * @param $string
	 * @return string
	 */
	static function uncamelize($string) {
		assert(!empty($string));

		// Transform "myOp" to "MyOp"
		$string = ucfirst($string);

		// Insert hyphens between words and return the string in lowercase
		$words = array();
		self::regexp_match_all('/[A-Z][a-z0-9]*/', $string, $words);
		assert(isset($words[0]) && !empty($words[0]) && strlen(implode('', $words[0])) == strlen($string));
		return strtolower(implode('-', $words[0]));
	}

	/**
	 * Get a letter $steps places after 'A'
	 * @param $steps int
	 * @return string Letter
	 */
	static function enumerateAlphabetically($steps) {
		return chr(ord('A') + $steps);
	}

	/**
	 * Create a new UUID (version 4)
	 * @return string
	 */
	static function generateUUID() {
		mt_srand((double)microtime()*10000);
		$charid = strtoupper(md5(uniqid(rand(), true)));
		$hyphen = '-';
		$uuid = substr($charid, 0, 8).$hyphen
				.substr($charid, 8, 4).$hyphen
				.'4'.substr($charid,13, 3).$hyphen
				.strtoupper(dechex(hexdec(ord(substr($charid,16,1))) % 4 + 8)).substr($charid,17, 3).$hyphen
				.substr($charid,20,12);
		return $uuid;
	}

	/**
	 * Matches each symbol of PHP strftime format string
	 * to jQuery Datepicker widget date format.
	 * @param $phpFormat string
	 * @return string
	 */
	static function dateformatPHP2JQueryDatepicker($phpFormat) {
		$symbols = array(
			// Day
			'a' => 'D',	// date() format 'D'
			'A' => 'DD',	// date() format 'DD'
			'd' => 'dd',	// date() format 'd'
			'e' => 'd',	// date() format 'j'
			'j' => 'oo',	// date() format none
			'u' => '',		// date() format 'N'
			'w' => '',		// date() format 'w'

			// Week
			'U' => '',		// date() format none
			'V' => '',		// date() format none
			'W' => '',		// date() format 'W'

			// Month
			'b' => 'M',	// date() format 'M'
			'h' => 'M',	// date() format 'M'
			'B' => 'MM',	// date() format 'F'
			'm' => 'mm',	// date() format 'm'

			// Year
			'C' => '',		// date() format none
			'g' => 'y',	// date() format none
			'G' => 'yy',	// date() format 'o'
			'y' => 'y',	// date() format 'y'
			'Y' => 'yy',	// date() format 'Y'

			// Time
			'H' => '',		// date() format 'H'
			'k' => '',		// date() format none
			'I' => '',		// date() format 'h'
			'l' => '',		// date() format 'g'
			'P' => '',		// date() format 'a'
			'p' => '',		// date() format 'A'
			'M' => '',		// date() format 'i'
			'S' => '',		// date() format 's'
			's' => '',		// date() format 'u'

			// Timezone
			'z' => '',		// date() format 'O'
			'Z' => '',		// date() format 'T'

			// Full Date/Time
			'r' => '',		// date() format none
			'R' => '',		// date() format none
			'X' => '',		// date() format none
			'D' => '',		// date() format none
			'F' => '',		// date() format none
			'x' => '',		// date() format none
			'c' => '',		// date() format none

			// Other
			'%' => ''
		);

		$datepickerFormat = "";
		$escaping = false;

		for ($i = 0; $i < strlen($phpFormat); $i++) {
			$char = $phpFormat[$i];
			if($char === '\\') {
				$i++;
				$datepickerFormat .= $escaping ? $phpFormat[$i] : '\'' . $phpFormat[$i];

				$escaping = true;
			} else {
				if($escaping) {
					$datepickerFormat .= "'";
					$escaping = false;
				}

				$datepickerFormat .= isset($symbols[$char]) ? $symbols[$char] : $char;

			}
		}

		return $datepickerFormat;
	}
}

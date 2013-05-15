<?php

/**
 * @defgroup core
 */

/**
 * @file classes/core/Core.inc.php
 *
 * Copyright (c) 2000-2013 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class Core
 * @ingroup core
 *
 * @brief Class containing system-wide functions.
 */


define('USER_AGENTS_FILE', Core::getBaseDir() . DIRECTORY_SEPARATOR . 'lib' . DIRECTORY_SEPARATOR . 'pkp' . DIRECTORY_SEPARATOR . 'registry' . DIRECTORY_SEPARATOR . 'botAgents.txt');

class Core {

	/** @var array The regular expressions that will find a bot user agent */
	static $botRegexps = array();

	/**
	 * Get the path to the base installation directory.
	 * @return string
	 */
	function getBaseDir() {
		static $baseDir;

		if (!isset($baseDir)) {
			// Need to change if the index file moves
			$baseDir = dirname(INDEX_FILE_LOCATION);
		}

		return $baseDir;
	}

	/**
	 * Sanitize a variable.
	 * Removes leading and trailing whitespace, normalizes all characters to UTF-8.
	 * @param $var string
	 * @return string
	 */
	function cleanVar($var) {
		// only normalize strings that are not UTF-8 already, and when the system is using UTF-8
		if ( Config::getVar('i18n', 'charset_normalization') == 'On' && strtolower_codesafe(Config::getVar('i18n', 'client_charset')) == 'utf-8' && !String::utf8_is_valid($var) ) {

			$var = String::utf8_normalize($var);

			// convert HTML entities into valid UTF-8 characters (do not transcode)
			if (checkPhpVersion('5.0.0')) {
				$var = html_entity_decode($var, ENT_COMPAT, 'UTF-8');
			} else {
				$var = String::html2utf($var);
			}

			// strip any invalid UTF-8 sequences
			$var = String::utf8_bad_strip($var);

			// re-encode special HTML characters
			if (checkPhpVersion('5.2.3')) {
				$var = htmlspecialchars($var, ENT_NOQUOTES, 'UTF-8', false);
			} else {
				$var = htmlspecialchars($var, ENT_NOQUOTES, 'UTF-8');
			}
		}

		// strip any invalid ASCII control characters
		$var = String::utf8_strip_ascii_ctrl($var);

		return trim($var);
	}

	/**
	 * Sanitize a value to be used in a file path.
	 * Removes any characters except alphanumeric characters, underscores, and dashes.
	 * @param $var string
	 * @return string
	 */
	function cleanFileVar($var) {
		return String::regexp_replace('/[^\w\-]/', '', $var);
	}

	/**
	 * Return the current date in ISO (YYYY-MM-DD HH:MM:SS) format.
	 * @param $ts int optional, use specified timestamp instead of current time
	 * @return string
	 */
	function getCurrentDate($ts = null) {
		return date('Y-m-d H:i:s', isset($ts) ? $ts : time());
	}

	/**
	 * Return *nix timestamp with microseconds (in units of seconds).
	 * @return float
	 */
	function microtime() {
		list($usec, $sec) = explode(' ', microtime());
		return (float)$sec + (float)$usec;
	}

	/**
	 * Get the operating system of the server.
	 * @return string
	 */
	function serverPHPOS() {
		return PHP_OS;
	}

	/**
	 * Get the version of PHP running on the server.
	 * @return string
	 */
	function serverPHPVersion() {
		return phpversion();
	}

	/**
	 * Check if the server platform is Windows.
	 * @return boolean
	 */
	function isWindows() {
		return strtolower_codesafe(substr(Core::serverPHPOS(), 0, 3)) == 'win';
	}

	/**
	 * Check the passed user agent for a bot.
	 * @param $userAgent string
	 * @param $botRegexpsFile string An alternative file with regular
	 * expressions to find bots inside user agent strings.
	 * @return boolean
	 */
	function isUserAgentBot($userAgent, $botRegexpsFile = USER_AGENTS_FILE) {
		static $botRegexps;

		if (!isset($botRegexps[$botRegexpsFile])) {
			$cacheManager =& CacheManager::getManager();
			$cache =& $cacheManager->getCache('core', $botRegexpsFile, array('Core', '_botFileListCacheMiss'), CACHE_TYPE_FILE);
			$botRegexps[$botRegexpsFile] = $cache->getContents($botRegexpsFile);
		}

		foreach ($botRegexps[$botRegexpsFile] as $regexp) {
			if (String::regexp_match($regexp, $userAgent)) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Bot list file cache miss fallback.
	 * @param $cache FileCache
	 * @return array:
	 */
	function _botFileListCacheMiss(&$cache) {
		$id = $cache->getCacheId();
		$botRegexps = array_filter(file($id),
			array('Core', '_filterBotRegexps'));

		$cache->setEntireCache($botRegexps);
		return $botRegexps;
	}

	/**
	 * Filter the regular expressions to find bots, adding
	 * delimiters if necessary.
	 * @param $regexp string
	 */
	function _filterBotRegexps(&$regexp) {
		$delimiter = '/';
		$regexp = trim($regexp);
		if (!empty($regexp) && $regexp[0] != '#') {
			if(strpos($regexp, $delimiter) !== 0) {
				// Make sure delimiters are in place.
				$regexp = $delimiter . $regexp . $delimiter;
			}
			return true;
		} else {
			return false;
		}
	}
}

?>

<?php

/**
 * @file TimeZone.inc.php
 *
 * Copyright (c) 2000-2008 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class TimeZone
 * @ingroup i18n
 *
 * @brief Time zone management class.
 * Provides methods for determining local times and dates
 */

// $Id$


define('TZ_REGISTRY_FILE', 'lib/pkp/xml/timeZones.xml');
define('TZ_DATE_FORMAT_DEFAULT', '%c');
define('TZ_DATE_FORMAT_DATEONLY', '%x');
define('TZ_DATE_FORMAT_TIMEONLY', '%X');
define('TZ_DEFAULT', 'Etc/GMT');

class TimeZone {
	/**
	 * Constructor.
	 */
	function TimeZone() {
	}

	function formatLocalTime($format = null, $gmtStamp = null, $timeZone = null) {

		// Default to locale settings
		if(!isset($format))
			$format = TZ_DATE_FORMAT_DEFAULT;

		// Default to 'right now' timestamp in GMT
		if(!isset($gmtStamp))
			$gmtStamp = time();

		// Ensure the time zone string is sane
		if(isset($timeZone) && !TimeZone::isValidTimeZone($timeZone))
			return null;

		// Default to user time zone if possible
		if(!isset($timeZone)) {
			$user =& Request::getUser();
			if($user)
				$timeZone = $user->getTimeZone();
		}

		// Fall back on server time zone if none was supplied
		if(!isset($timeZone))
			$timeZone = TimeZone::getDefaultTimeZone();

		if(function_exists('date_default_timezone_set')) {

			// Use PHP5 functions if they exist
			$oldTimeZone = date_default_timezone_get();
			date_default_timezone_set($timeZone);
			$date = date($format, $gmtStamp);
			date_default_timezone_set($timeZone);

		} else {

			// Fall back on PHP4
			$oldTimeZone = getenv('TZ');
			putenv('TZ=' . $timeZone);
			$date = strftime($format, $gmtStamp);
			putenv('TZ=' . $oldTimeZone);
		}

		return $date;
	}

	function isValidTimeZone($timeZone) {
		$tzFlat = TimeZone::_getTZData();
		if(!isset($tzFlat[$timeZone])) {
			// A nonsensical (or unknown) server time zone was provided.
			return false;
		}

		return true;
	}

	function getTimeZones() {
		$tzFlat = TimeZone::_getTZData();
		return $tzFlat;
	}

	function getDefaultTimeZone() {
		static $tzServer;

		// If configuration specifies the server timezone, use it.
		if(!isset($tzServer))
			$tzServer = Config::getVar('i18n', 'default_timezone');

		// Otherwise, intuit timezone as best we can
		if(!isset($tzServer))
				if(function_exists('date_default_timezone_get'))
					$tzServer = date_default_timezone_get();

		// Ensure the zone we picked up is valid and usable
		if(isset($tzServer) && !TimeZone::isValidTimeZone($tzServer)) {
			// A nonsensical (or unknown) server time zone was provided.
			// Invalidate it.
			$tzServer = null;
		}

		// Fall back on a known-good default
		if(!isset($tzServer))
			$tzServer = TZ_DEFAULT;

		return $tzServer;
	}

	function _getTZData() {
		$tzFlat =& Registry::get('tzFlat', true, null);

		if ($tzFlat === null) {
			$tzFlat = array();

			// Load registry file
			$xmlDao = new XMLDAO();
			$tzRaw = $xmlDao->parseStruct(TZ_REGISTRY_FILE, array("entry"));

			// Build tzFlat by breaking tzRaw into path components.

			// FIXME: this only happens once, and eases parsing of locale files,
			// but perhaps is best done when the XML is generated.

			foreach($tzRaw['entry'] as $value) {
				$key = $value['attributes']['key'];
				$name = $value['attributes']['name'];

				// tzFlat is simple. Just add an association.
				$tzFlat[$key] = $key;
			}
		}
		return $tzFlat;
	}
}

?>

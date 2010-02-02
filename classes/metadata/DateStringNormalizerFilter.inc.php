<?php

/**
 * @file classes/metadata/DateStringNormalizerFilter.inc.php
 *
 * Copyright (c) 2000-2010 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class DateStringNormalizerFilter
 * @ingroup metadata
 *
 * @brief Filter that normalizes a date string to
 *  YYYY[-MM[-DD]].
 */

// $Id$

import('filter.Filter');

class DateStringNormalizerFilter extends Filter {
	//
	// Implement template methods from Filter
	//
	/**
	 * @see Filter::supports()
	 * @param $input mixed
	 * @return boolean
	 */
	function supports(&$input) {
		return is_string($input);
	}

	/**
	 * @see Filter::isValid()
	 * @param $output mixed
	 * @return boolean
	 */
	function isValid(&$output) {
		// Check whether the output is correctly formatted
		return (boolean)String::regexp_match("/\d{4}(-\d{2}(-\d{2})?)?/", $output);
	}

	/**
	 * Normalize incoming date string.
	 * @see Filter::process()
	 * @param $input string
	 * @return string
	 */
	function &process(&$input) {
		// FIXME: We have to i18nize this when expanding citation parsing to other languages
		static $monthNames = array(
			'Jan' => '01', 'Feb' => '02', 'Mar' => '03', 'Apr' => '04', 'May' => '05', 'Jun' => '06',
			'Jul' => '07', 'Aug' => '08', 'Sep' => '09', 'Oct' => '10', 'Nov' => '11', 'Dec' => '12'
		);

		$normalizedDate = null;
		if (String::regexp_match_get("/(?P<year>\d{4})\s*(?P<month>[a-z]\w+)?\s*(?P<day>\d+)?/i", $input, $parsedDate) ){
			if (isset($parsedDate['year'])) {
				$normalizedDate = $parsedDate['year'];

				if (isset($parsedDate['month'])
						&& isset($monthNames[substr($parsedDate['month'], 0, 3)])) {
					// Convert the month name to a two digit numeric month representation
					// before adding it to the normalized date string.
					$normalizedDate .= '-'.$monthNames[substr($parsedDate['month'], 0, 3)];

					if (isset($parsedDate['day'])) $normalizedDate .= '-'.str_pad($parsedDate['day'], 2, '0', STR_PAD_LEFT);
				}
			}
		}

		return $normalizedDate;
	}
}
?>
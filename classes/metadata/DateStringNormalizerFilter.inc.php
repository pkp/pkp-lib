<?php

/**
 * @file classes/metadata/DateStringNormalizerFilter.inc.php
 *
 * Copyright (c) 2000-2012 John Willinsky
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
	 * @param $output mixed
	 * @return boolean
	 */
	function supports(&$input, &$output) {
		// Check input type
		if(!is_string($input)) return false;

		// Check output type
		if(is_null($output)) return true;
		if(!is_string($output)) return false;
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

		$dateExpressions = array(
			'/(?P<year>\d{4})-(?P<month>\d{2})-(?P<day>\d{2})/',
			'/(?P<year>\d{4})\s*(?P<monthName>[a-z]\w+)?\s*(?P<day>\d+)?/i'
		);
		$normalizedDate = null;
		foreach($dateExpressions as $dateExpression) {
			if (String::regexp_match_get($dateExpression, $input, $parsedDate) ){
				if (isset($parsedDate['year'])) {
					$normalizedDate = $parsedDate['year'];

					$month = '';
					if (isset($parsedDate['monthName'])) {
						$monthName = substr($parsedDate['monthName'], 0, 3);
						if (isset($monthNames[$monthName])) {
							// Convert the month name to a two digit numeric month representation
							// before adding it to the normalized date string.
							$month = $monthNames[$monthName];
						}
					}

					if (isset($parsedDate['month'])) {
						$monthInt = (integer)$parsedDate['month'];
						if ($monthInt >=1 && $monthInt <= 12)
							$month = str_pad((string)$monthInt, 2, '0', STR_PAD_LEFT);
					}

					if (!empty($month)) {
						$normalizedDate .= '-'.$month;
						if (isset($parsedDate['day'])) $normalizedDate .= '-'.str_pad($parsedDate['day'], 2, '0', STR_PAD_LEFT);
					}
				}
				if (!empty($normalizedDate)) break;
			}
		}

		return $normalizedDate;
	}
}
?>
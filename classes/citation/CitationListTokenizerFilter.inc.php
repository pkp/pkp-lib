<?php

/**
 * @file classes/citation/CitationListTokenizerFilter.inc.php
 *
 * Copyright (c) 2000-2012 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class CitationListTokenizerFilter
 * @ingroup classes_citation
 *
 * @brief Class that takes an unformatted list of citations
 *  and returns an array of raw citation strings.
 */

// $Id$

import('filter.Filter');

class CitationListTokenizerFilter extends Filter {
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
		// Input validation
		if (!is_string($input)) return false;

		// Output validation
		if (is_null($output)) return true;
		if (!is_array($output)) return false;
		foreach($output as $citationString) {
			if (!is_string($citationString)) return false;
		}
		return true;
	}

	/**
	 * @see Filter::process()
	 * @param $input string
	 * @return mixed array
	 */
	function &process(&$input) {
		// The default implementation assumes that raw citations are
		// separated with line endings.
		// 1) Remove empty lines
		$input = String::regexp_replace('/[\r\n]+/s', "\n", $input);
		// 2) Break up at line endings
		$output = explode("\n", $input);
		// TODO: Implement more complex treatment, e.g. filtering of
		// number strings at the beginning of each string, etc.
		return $output;
	}
}
?>
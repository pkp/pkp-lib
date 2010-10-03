<?php

/**
 * @file classes/citation/CitationListTokenizerFilter.inc.php
 *
 * Copyright (c) 2000-2010 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class CitationListTokenizerFilter
 * @ingroup classes_citation
 *
 * @brief Class that takes an unformatted list of citations
 *  and returns an array of raw citation strings.
 */

import('lib.pkp.classes.filter.Filter');

class CitationListTokenizerFilter extends Filter {
	/**
	 * Constructor
	 */
	function CitationListTokenizerFilter() {
		$this->setDisplayName('Split a reference list into separate citations');

		parent::Filter('primitive::string', 'primitive::string[]');
	}

	//
	// Implement template methods from Filter
	//
	/**
	 * @see Filter::process()
	 * @param $input string
	 * @return mixed array
	 */
	function &process(&$input) {
		// The default implementation assumes that raw citations are
		// separated with line endings.
		// 1) Remove empty lines and normalize line endings
		$input = String::regexp_replace('/[\r\n]+/s', "\n", $input);
		// 2) Break up at line endings
		$citations = explode("\n", $input);
		// 3) Remove numbers from the beginning of each citation
		foreach($citations as $index => $citation) {
			$citations[$index] = String::regexp_replace('/^\s*[\[#]?[0-9]+[.)\]]?\s*/', '', $citation);
		}

		return $citations;
	}
}
?>
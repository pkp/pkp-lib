<?php

/**
 * @file classes/citation/CitationListTokenizerFilter.inc.php
 *
 * Copyright (c) 2000-2011 John Willinsky
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
		$this->setDisplayName('Citation Tokenizer');

		parent::Filter();
	}

	//
	// Implement template methods from Filter
	//
	/**
	 * @see Filter::getSupportedTransformation()
	 */
	function getSupportedTransformation() {
		return array('primitive::string', 'primitive::string[]');
	}

	/**
	 * @see Filter::getClassName()
	 */
	function getClassName() {
		return 'lib.pkp.classes.citation.CitationListTokenizerFilter';
	}

	/**
	 * @see Filter::process()
	 * @param $input string
	 * @return mixed array
	 */
	function &process(&$input) {
		// The default implementation assumes that raw citations are
		// separated with line endings.
		// 1) Remove empty lines and normalize line endings.
		$input = String::regexp_replace('/[\r\n]+/s', "\n", $input);
		// 2) Remove trailing/leading line breaks.
		$input = trim($input, "\n");
		// 3) Break up at line endings.
		if (empty($input)) {
			$citations = array();
		} else {
			$citations = explode("\n", $input);
		}
		// 4) Remove numbers from the beginning of each citation.
		foreach($citations as $index => $citation) {
			$citations[$index] = String::regexp_replace('/^\s*[\[#]?[0-9]+[.)\]]?\s*/', '', $citation);
		}

		return $citations;
	}
}
?>
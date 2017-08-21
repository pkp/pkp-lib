<?php

/**
 * @file classes/citation/CitationListTokenizerFilter.inc.php
 *
 * Copyright (c) 2013-2017 Simon Fraser University
 * Copyright (c) 2000-2016 John Willinsky
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
		// separated with a blank line.

		// Normalize line endings.
		$lines = array();
		String::regexp_match_all('/(*ANY)^(.*)$/m', $input, $lines);
		$input = join("\n", $lines[0]);

		// Make blank lines truely blank
		$input = String::regexp_replace('/^\s+$/m', '', $input);

		// Remove trailing/leading line breaks overall.
		$input = trim($input, "\n");

		// Normalize line seperation
		$input = String::regexp_replace('/\n{2,}/', "\n\n", $input);
		if (String::strpos($input, "\n\n") === FALSE) {
			$lines = array();
			String::regexp_match_all('/^(.*)$/m', $input, $lines);
			$input = join("\n\n", $lines[0]);
		}

		// Check for multiline citations
		$separationExists = false;
		$indentationExists = false;
		$unindentedExists = false;
		$lines = explode("\n", $input);
		foreach ($lines as $line) {
			if ($line == '') {
				$separationExists = true;
			} else {
				if (String::strpos($line, "\t") === 0 || String::strpos($line, ' ') === 0) {
					$indentationExists = true;
				} else {
					$unindentedExists = true;
				}
			}
		}
		if ($separationExists && $indentationExists && $unindentedExists) {
			$input = '';
			foreach ($lines as $line) {
				if (String::strpos($line, "\t") === 0 || String::strpos($line, ' ') === 0) {
					$line = ltrim($line);
					$input = rtrim($input, "\n");
					$input .= $line."\n";
				} else {
					$input .= $line."\n";
				}
			}
		}

		// Break up at line endings.
		if (empty($input)) {
			$citations = array();
		} else {
			$citations = explode("\n\n", $input);
		}

		// Remove numbers from the beginning of each citation.
		foreach($citations as $index => $citation) {
			$citations[$index] = String::regexp_replace('/^\s*[\[#]?[0-9]+[.)\]]?\s*/', '', $citation);
		}

		return $citations;
	}
}
?>

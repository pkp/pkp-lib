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
		if (strpos($input, "\r") !== FALSE) {
			// Windows formatting to *nix
			$input = str_replace("\r\n", "\n", $input);
			// Are returns begin used as line endings?
			$input = str_replace("\r", "\n", $input);
		}

		// Make blank lines truely blank
		$input = preg_replace('/^\s+$/m', '', $input);

		// Remove trailing/leading line breaks overall.
		$input = trim($input, "\n");

		// Normalize line seperation
		$input = String::regexp_replace('/\n{2,}/s', "\n\n", $input);
		if (strpos($input, "\n\n") === FALSE) {
			$input = str_replace("\n", "\n\n", $input);
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
				if (substr($line, 0, 1) === "\t" || substr($line, 0, 1) === ' ') {
					$indentationExists = true;
				} else {
					$unindentedExists = true;
				}
			}
		}
		if ($separationExists && $indentationExists && $unindentedExists) {
			$input = '';
			foreach ($lines as $line) {
				if (substr($line, 0, 1) === "\t" || substr($line, 0, 1) === ' ') {
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

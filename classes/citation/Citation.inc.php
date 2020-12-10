<?php

/**
 * @defgroup citation Citation
 */

/**
 * @file classes/citation/Citation.inc.php
 *
 * Copyright (c) 2014-2020 Simon Fraser University
 * Copyright (c) 2000-2020 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class Citation
 * @ingroup citation
 *
 * @brief Class representing a citation (bibliographic reference)
 */

import('lib.pkp.classes.core.DataObject');

class Citation extends DataObject {
	/**
	 * Constructor.
	 * @param $rawCitation string an unparsed citation string
	 */
	function __construct($rawCitation = null) {
		parent::__construct();
		$this->setRawCitation($rawCitation);
	}

	//
	// Getters and Setters
	//

	/**
	 * Replace URLs through HTML links, if the citation does not already contain HTML links
	 * @return string
	 */
	function getCitationWithLinks() {
		$citation = $this->getRawCitation();
		if (stripos($citation, '<a href=') === false) {
			$citation = preg_replace_callback(
				'#(http|https|ftp)://[\d\w\.-]+\.[\w\.]{2,6}[^\s\]\[\<\>]*/?#',
				function($matches) {
					$trailingDot = in_array($char = substr($matches[0], -1), array('.', ','));
					$url = rtrim($matches[0], '.,');
					return "<a href=\"$url\">$url</a>" . ($trailingDot?$char:'');
				},
				$citation
			);
		}
		return $citation;
	}

	/**
	 * Get the rawCitation
	 * @return string
	 */
	function getRawCitation() {
		return $this->getData('rawCitation');
	}

	/**
	 * Set the rawCitation
	 * @param $rawCitation string
	 */
	function setRawCitation($rawCitation) {
		$rawCitation = $this->_cleanCitationString($rawCitation);
		$this->setData('rawCitation', $rawCitation);
	}

	/**
	 * Get the sequence number
	 * @return integer
	 */
	function getSequence() {
		return $this->getData('seq');
	}

	/**
	 * Set the sequence number
	 * @param $seq integer
	 */
	function setSequence($seq) {
		$this->setData('seq', $seq);
	}

	//
	// Private methods
	//
	/**
	 * Take a citation string and clean/normalize it
	 * @param $citationString string
	 * @return string
	 */
	function _cleanCitationString($citationString) {
		// 1) Strip slashes and whitespace
		$citationString = trim(stripslashes($citationString));

		// 2) Normalize whitespace
		$citationString = PKPString::regexp_replace('/[\s]+/', ' ', $citationString);

		return $citationString;
	}
}

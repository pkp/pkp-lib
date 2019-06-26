<?php

/**
 * @defgroup citation Citation
 */

/**
 * @file classes/citation/Citation.inc.php
 *
 * Copyright (c) 2014-2019 Simon Fraser University
 * Copyright (c) 2000-2019 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
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
		$this->setRawCitation($rawCitation); // this will set state to CITATION_RAW
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
			$citation = preg_replace(
				'#((https?|ftp)://(\S*?\.\S*?))(([\s)\[\]{},;"\':<>])?(\.)?(\s|$))#i',
				'<a href="$1">$1</a>$4',
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

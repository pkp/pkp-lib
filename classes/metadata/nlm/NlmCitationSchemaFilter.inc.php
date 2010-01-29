<?php

/**
 * @file classes/citation/NlmCitationSchemaFilter.inc.php
 *
 * Copyright (c) 2000-2010 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class NlmCitationSchemaFilter
 * @ingroup metadata_nlm
 *
 * @brief Abstract base class for all filters that transform
 *  NLM citation metadata descriptions.
 */

// $Id$

import('filter.Filter');

class NlmCitationSchemaFilter extends Filter {
	//
	// Implement template methods from Filter
	//
	/**
	 * @see Filter::supports()
	 * @param $input mixed
	 * @return boolean
	 */
	function supports(&$input) {
		return $this->isNlmCitationDescription($input);
	}

	//
	// Protected helper methods
	//
	/**
	 * Checks whether a given input is a nlm citation description
	 * @param $input mixed
	 * @return boolean
	 */
	function isNlmCitationDescription(&$input) {
		if (!is_a($input, 'MetadataDescription')) return false;
		$metadataSchema =& $input->getMetadataSchema();
		if ($metadataSchema->getName() != 'nlm-3.0-element-citation') return false;
		return true;
	}
}
?>
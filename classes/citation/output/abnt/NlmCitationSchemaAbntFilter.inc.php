<?php

/**
 * @defgroup citation_output_abnt
 */

/**
 * @file citation/output/abnt/NlmCitationSchemaAbntFilter.inc.php
 *
 * Copyright (c) 2000-2012 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class NlmCitationSchemaAbntFilter
 * @ingroup citation_output_abnt
 *
 * @brief Filter that transforms NLM citation metadata descriptions into
 *  ABNT citation output.
 */

// $Id$

import('metadata.nlm.NlmCitationSchemaCitationOutputFormatFilter');

class NlmCitationSchemaAbntFilter extends NlmCitationSchemaCitationOutputFormatFilter {
	/**
	 * Constructor
	 * @param $request PKPRequest
	 */
	function NlmCitationSchemaAbntFilter(&$request) {
		parent::NlmCitationSchemaCitationOutputFormatFilter($request);
	}

	//
	// Implement abstract template methods from NlmCitationSchemaCitationOutputFormatFilter
	//
	/**
	 * @see NlmCitationSchemaCitationOutputFormatFilter::getBasePath()
	 * @return string
	 */
	function getBasePath() {
		return dirname(__FILE__);
	}
}
?>
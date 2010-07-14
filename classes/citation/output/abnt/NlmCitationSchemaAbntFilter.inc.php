<?php

/**
 * @defgroup citation_output_abnt
 */

/**
 * @file citation/output/abnt/NlmCitationSchemaAbntFilter.inc.php
 *
 * Copyright (c) 2000-2010 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class NlmCitationSchemaAbntFilter
 * @ingroup citation_output_abnt
 *
 * @brief Filter that transforms NLM citation metadata descriptions into
 *  ABNT citation output.
 */


import('lib.pkp.classes.metadata.nlm.NlmCitationSchemaCitationOutputFormatFilter');

class NlmCitationSchemaAbntFilter extends NlmCitationSchemaCitationOutputFormatFilter {
	/**
	 * Constructor
	 * @param $request PKPRequest
	 */
	function NlmCitationSchemaAbntFilter(&$request = null) {
		$this->setDisplayName('ABNT Citation Output');

		parent::NlmCitationSchemaCitationOutputFormatFilter($request);
	}


	//
	// Implement template methods from Filter
	//
	/**
	 * @see Filter::getClassName()
	 */
	function getClassName() {
		return 'lib.pkp.classes.citation.output.abnt.NlmCitationSchemaAbntFilter';
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
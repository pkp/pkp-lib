<?php

/**
 * @defgroup citation_output_vancouver
 */

/**
 * @file citation/output/vancouver/NlmCitationSchemaVancouverFilter.inc.php
 *
 * Copyright (c) 2000-2010 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class NlmCitationSchemaVancouverFilter
 * @ingroup citation_output_vancouver
 *
 * @brief Filter that transforms NLM citation metadata descriptions into
 *  Vancouver citation output.
 */


import('lib.pkp.classes.metadata.nlm.NlmCitationSchemaCitationOutputFormatFilter');

class NlmCitationSchemaVancouverFilter extends NlmCitationSchemaCitationOutputFormatFilter {
	/**
	 * Constructor
	 * @param $request PKPRequest
	 */
	function NlmCitationSchemaVancouverFilter(&$request = null) {
		$this->setDisplayName('Vancouver Citation Output');

		parent::NlmCitationSchemaCitationOutputFormatFilter($request);
	}


	//
	// Implement template methods from Filter
	//
	/**
	 * @see Filter::getClassName()
	 */
	function getClassName() {
		return 'lib.pkp.classes.citation.output.vancouver.NlmCitationSchemaVancouverFilter';
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
<?php

/**
 * @defgroup citation_output_mla
 */

/**
 * @file citation/output/mla/NlmCitationSchemaMlaFilter.inc.php
 *
 * Copyright (c) 2000-2011 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class NlmCitationSchemaMlaFilter
 * @ingroup citation_output_mla
 *
 * @brief Filter that transforms NLM citation metadata descriptions into
 *  MLA citation output.
 */


import('lib.pkp.classes.metadata.nlm.NlmCitationSchemaCitationOutputFormatFilter');

class NlmCitationSchemaMlaFilter extends NlmCitationSchemaCitationOutputFormatFilter {
	/**
	 * Constructor
	 * @param $request PKPRequest
	 */
	function NlmCitationSchemaMlaFilter() {
		$this->setDisplayName('MLA Citation Output');

		parent::NlmCitationSchemaCitationOutputFormatFilter();
	}


	//
	// Implement template methods from Filter
	//
	/**
	 * @see Filter::getClassName()
	 */
	function getClassName() {
		return 'lib.pkp.classes.citation.output.mla.NlmCitationSchemaMlaFilter';
	}


	//
	// Implement abstract template methods from TemplateBasedFilter
	//
	/**
	 * @see TemplateBasedFilter::getBasePath()
	 */
	function getBasePath() {
		return dirname(__FILE__);
	}
}
?>
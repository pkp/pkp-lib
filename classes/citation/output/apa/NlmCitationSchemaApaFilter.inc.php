<?php

/**
 * @defgroup citation_output_apa
 */

/**
 * @file citation/output/apa/NlmCitationSchemaApaFilter.inc.php
 *
 * Copyright (c) 2000-2012 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class NlmCitationSchemaApaFilter
 * @ingroup citation_output_apa
 *
 * @brief Filter that transforms NLM citation metadata descriptions into
 *  APA citation output.
 */

import('lib.pkp.classes.metadata.nlm.NlmCitationSchemaCitationOutputFormatFilter');

class NlmCitationSchemaApaFilter extends NlmCitationSchemaCitationOutputFormatFilter {
	/**
	 * Constructor
	 * @param $request PKPRequest
	 */
	function NlmCitationSchemaApaFilter() {
		$this->setDisplayName('APA Citation Output');

		parent::NlmCitationSchemaCitationOutputFormatFilter();
	}

	//
	// Implement template methods from Filter
	//
	/**
	 * @see Filter::getClassName()
	 */
	function getClassName() {
		return 'lib.pkp.classes.citation.output.apa.NlmCitationSchemaApaFilter';
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
<?php

/**
 * @defgroup citation_output_apa
 */

/**
 * @file citation/output/apa/NlmCitationSchemaApaFilter.inc.php
 *
 * Copyright (c) 2000-2010 John Willinsky
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
	function NlmCitationSchemaApaFilter(&$request = null) {
		$this->setDisplayName('APA Citation Output');

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
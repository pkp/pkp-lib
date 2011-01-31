<?php

/**
 * @defgroup citation_output_abnt
 */

/**
 * @file citation/output/abnt/NlmCitationSchemaAbntFilter.inc.php
 *
 * Copyright (c) 2000-2011 John Willinsky
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
	function NlmCitationSchemaAbntFilter() {
		$this->setDisplayName('ABNT Citation Output');
		// FIXME: Implement conference proceedings support for ABNT.
		$this->setSupportedPublicationTypes(array(
			NLM_PUBLICATION_TYPE_BOOK, NLM_PUBLICATION_TYPE_JOURNAL
		));

		parent::NlmCitationSchemaCitationOutputFormatFilter();
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
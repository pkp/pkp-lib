<?php

/**
 * @defgroup citation_output_abnt
 */

/**
 * @file citation/output/abnt/Nlm30CitationSchemaAbntFilter.inc.php
 *
 * Copyright (c) 2000-2010 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class Nlm30CitationSchemaAbntFilter
 * @ingroup citation_output_abnt
 *
 * @brief Filter that transforms NLM citation metadata descriptions into
 *  ABNT citation output.
 */


import('lib.pkp.plugins.metadata.nlm30.filter.Nlm30CitationSchemaCitationOutputFormatFilter');

class Nlm30CitationSchemaAbntFilter extends Nlm30CitationSchemaCitationOutputFormatFilter {
	/**
	 * Constructor
	 * @param $request PKPRequest
	 */
	function Nlm30CitationSchemaAbntFilter() {
		$this->setDisplayName('ABNT Citation Output');
		// FIXME: Implement conference proceedings support for ABNT.
		$this->setSupportedPublicationTypes(array(
			NLM_PUBLICATION_TYPE_BOOK, NLM_PUBLICATION_TYPE_JOURNAL
		));

		parent::Nlm30CitationSchemaCitationOutputFormatFilter();
	}


	//
	// Implement template methods from Filter
	//
	/**
	 * @see Filter::getClassName()
	 */
	function getClassName() {
		return 'lib.pkp.classes.citation.output.abnt.Nlm30CitationSchemaAbntFilter';
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
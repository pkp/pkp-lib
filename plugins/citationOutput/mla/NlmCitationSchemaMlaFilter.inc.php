<?php

/**
 * @defgroup citation_output_mla
 */

/**
 * @file citation/output/mla/Nlm30CitationSchemaMlaFilter.inc.php
 *
 * Copyright (c) 2000-2010 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class Nlm30CitationSchemaMlaFilter
 * @ingroup citation_output_mla
 *
 * @brief Filter that transforms NLM citation metadata descriptions into
 *  MLA citation output.
 */


import('lib.pkp.plugins.metadata.nlm30.filter.Nlm30CitationSchemaCitationOutputFormatFilter');

class Nlm30CitationSchemaMlaFilter extends Nlm30CitationSchemaCitationOutputFormatFilter {
	/**
	 * Constructor
	 * @param $request PKPRequest
	 */
	function Nlm30CitationSchemaMlaFilter() {
		$this->setDisplayName('MLA Citation Output');

		parent::Nlm30CitationSchemaCitationOutputFormatFilter();
	}


	//
	// Implement template methods from Filter
	//
	/**
	 * @see Filter::getClassName()
	 */
	function getClassName() {
		return 'lib.pkp.classes.citation.output.mla.Nlm30CitationSchemaMlaFilter';
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
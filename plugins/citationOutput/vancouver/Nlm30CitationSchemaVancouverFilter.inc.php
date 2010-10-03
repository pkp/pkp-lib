<?php

/**
 * @defgroup citation_output_vancouver
 */

/**
 * @file citation/output/vancouver/Nlm30CitationSchemaVancouverFilter.inc.php
 *
 * Copyright (c) 2000-2010 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class Nlm30CitationSchemaVancouverFilter
 * @ingroup citation_output_vancouver
 *
 * @brief Filter that transforms NLM citation metadata descriptions into
 *  Vancouver citation output.
 */


import('lib.pkp.plugins.metadata.nlm30.filter.Nlm30CitationSchemaCitationOutputFormatFilter');

class Nlm30CitationSchemaVancouverFilter extends Nlm30CitationSchemaCitationOutputFormatFilter {
	/**
	 * Constructor
	 * @param $request PKPRequest
	 */
	function Nlm30CitationSchemaVancouverFilter() {
		$this->setDisplayName('Vancouver Citation Output');

		parent::Nlm30CitationSchemaCitationOutputFormatFilter();
	}


	//
	// Implement template methods from Filter
	//
	/**
	 * @see Filter::getClassName()
	 */
	function getClassName() {
		return 'lib.pkp.classes.citation.output.vancouver.Nlm30CitationSchemaVancouverFilter';
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
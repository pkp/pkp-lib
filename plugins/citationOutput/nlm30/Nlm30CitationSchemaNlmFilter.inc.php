<?php

/**
 * @defgroup citation_output_nlm
 */

/**
 * @file citation/output/nlm30/Nlm30CitationSchemaNlm30Filter.inc.php
 *
 * Copyright (c) 2000-2010 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class Nlm30CitationSchemaNlm30Filter
 * @ingroup citation_output_nlm
 *
 * @brief Filter that transforms NLM citation metadata descriptions into
 *  NLM XML citation output.
 */


import('lib.pkp.plugins.metadata.nlm30.filter.Nlm30CitationSchemaCitationOutputFormatFilter');

class Nlm30CitationSchemaNlm30Filter extends Nlm30CitationSchemaCitationOutputFormatFilter {
	/**
	 * Constructor
	 * @param $request PKPRequest
	 */
	function Nlm30CitationSchemaNlm30Filter() {
		$this->setDisplayName('NLM XML Citation Output');

		parent::Nlm30CitationSchemaCitationOutputFormatFilter();
	}


	//
	// Implement template methods from Filter
	//
	/**
	 * @see Filter::getClassName()
	 */
	function getClassName() {
		return 'lib.pkp.classes.citation.output.nlm.Nlm30CitationSchemaNlm30Filter';
	}

	/**
	 * @see Filter::getSupportedTransformation()
	 */
	function getSupportedTransformation() {
		return array(
			'metadata::lib.pkp.plugins.metadata.nlm30.schema.Nlm30CitationSchema(CITATION)',
			'xml::*' // FIXME: Add NLM citation + name validation (requires partial NLM DTD, XSD or RelaxNG).
		);
	}


	//
	// Implement abstract template methods from TemplateBasedFilter
	//
	/**
	 * @see TemplateBasedFilter::addTemplateVars()
	 */
	function addTemplateVars(&$templateMgr, &$input, &$request, &$locale) {
		// Assign the full meta-data description.
		$templateMgr->assign_by_ref('metadataDescription', $input);

		parent::addTemplateVars($templateMgr, $input, $request, $locale);
	}

	/**
	 * @see TemplateBasedFilter::getBasePath()
	 */
	function getBasePath() {
		return dirname(__FILE__);
	}
}
?>
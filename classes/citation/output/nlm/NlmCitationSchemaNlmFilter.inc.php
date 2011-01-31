<?php

/**
 * @defgroup citation_output_nlm
 */

/**
 * @file citation/output/nlm/NlmCitationSchemaNlmFilter.inc.php
 *
 * Copyright (c) 2000-2011 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class NlmCitationSchemaNlmFilter
 * @ingroup citation_output_nlm
 *
 * @brief Filter that transforms NLM citation metadata descriptions into
 *  NLM XML citation output.
 */


import('lib.pkp.classes.metadata.nlm.NlmCitationSchemaCitationOutputFormatFilter');

class NlmCitationSchemaNlmFilter extends NlmCitationSchemaCitationOutputFormatFilter {
	/**
	 * Constructor
	 * @param $request PKPRequest
	 */
	function NlmCitationSchemaNlmFilter() {
		$this->setDisplayName('NLM XML Citation Output');

		parent::NlmCitationSchemaCitationOutputFormatFilter();
	}


	//
	// Implement template methods from Filter
	//
	/**
	 * @see Filter::getClassName()
	 */
	function getClassName() {
		return 'lib.pkp.classes.citation.output.nlm.NlmCitationSchemaNlmFilter';
	}

	/**
	 * @see Filter::getSupportedTransformation()
	 */
	function getSupportedTransformation() {
		return array(
			'metadata::lib.pkp.classes.metadata.nlm.NlmCitationSchema(CITATION)',
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
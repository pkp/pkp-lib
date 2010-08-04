<?php

/**
 * @defgroup importexport_nlm
 */

/**
 * @file classes/importexport/nlm/NlmXmlExportFilter.inc.php
 *
 * Copyright (c) 2000-2010 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class NlmXmlExportFilter
 * @ingroup importexport_nlm
 *
 * @brief Class that exports an NLM XML document.
 *
 * FIXME: This class currently only generates partial (citation) NLM XML output.
 * Full NLM journal publishing tag set support will be added as soon as we
 * have migrated document parsing from L8X to the PKP library.
 */

import('lib.pkp.classes.filter.TemplateBasedFilter');

class NlmXmlExportFilter extends TemplateBasedFilter {
	/**
	 * Constructor
	 */
	function NlmXmlExportFilter() {
		parent::TemplateBasedFilter();
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

	/**
	 * @see Filter::getSupportedTransformation()
	 */
	function getSupportedTransformation() {
		// We take a set of XML-rendered NLM citation elements and
		// combine them into a full NLM reference list.
		return array(
			'xml::*[]', // FIXME: This will probably have to be changed to a composite object (e.g. a MetadataDescription) when we support full NLM documents.
			'xml::*' // FIXME: Add NLM Journal Publishing tag set validation as soon as we implement the full tag set.
		);
	}


	//
	// Implement template methods from TemplateBasedFilter
	//
	/**
	 * @see TemplateBasedFilter::getTemplateName()
	 */
	function getTemplateName() {
		return 'nlm.tpl';
	}

	/**
	 * @see TemplateBasedFilter::addTemplateVars()
	 */
	function addTemplateVars(&$templateMgr, &$input, &$request, &$locale) {
		$templateMgr->assign_by_ref('citationNodes', $input);
	}

	/**
	 * @see TemplateBasedFilter::getBasePath()
	 */
	function getBasePath() {
		return dirname(__FILE__);
	}
}
?>
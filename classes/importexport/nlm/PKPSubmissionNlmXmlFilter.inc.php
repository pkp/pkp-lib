<?php

/**
 * @defgroup importexport_nlm
 */

/**
 * @file classes/importexport/nlm/PKPSubmissionNlmXmlFilter.inc.php
 *
 * Copyright (c) 2000-2011 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class PKPSubmissionNlmXmlFilter
 * @ingroup importexport_nlm
 *
 * @brief Class that converts a submission to an NLM Journal Publishing
 * Tag Set 3.0 XML document.
 *
 * FIXME: This class currently only generates partial (citation) NLM XML output.
 * Full NLM journal publishing tag set support will be added as soon as we
 * have migrated document parsing from L8X to the PKP library.
 */


import('lib.pkp.classes.citation.output.TemplateBasedReferencesListFilter');

class PKPSubmissionNlmXmlFilter extends TemplateBasedReferencesListFilter {
	/**
	 * Constructor
	 */
	function PKPSubmissionNlmXmlFilter() {
		$this->setDisplayName('NLM Journal Publishing V3.0 ref-list');

		parent::TemplateBasedReferencesListFilter();
	}


	//
	// Implement template methods from Filter
	//
	/**
	 * @see Filter::getClassName()
	 */
	function getClassName() {
		return 'lib.pkp.classes.importexport.nlm.PKPSubmissionNlmXmlFilter';
	}

	/**
	 * @see Filter::getSupportedTransformation()
	 */
	function getSupportedTransformation() {
		// We take a submission object and render
		// it into NLM XML output.
		return array(
			'class::lib.pkp.classes.submission.Submission',
			'xml::*' // FIXME: Add NLM Journal Publishing tag set validation as soon as we implement the full tag set.
		);
	}


	//
	// Implement template methods from TemplateBasedReferencesListFilter
	//
	/**
	 * @see TemplateBasedReferencesListFilter::getCitationOutputFilterInstance()
	 */
	function &getCitationOutputFilterInstance() {
		import('lib.pkp.classes.citation.output.nlm.NlmCitationSchemaNlmFilter');
		$nlmCitationOutputFilter = new NlmCitationSchemaNlmFilter();
		return $nlmCitationOutputFilter;
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
	 * @see TemplateBasedFilter::getBasePath()
	 */
	function getBasePath() {
		return dirname(__FILE__);
	}
}
?>
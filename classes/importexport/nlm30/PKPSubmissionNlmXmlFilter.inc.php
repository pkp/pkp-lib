<?php

/**
 * @defgroup importexport_nlm
 */

/**
 * @file classes/importexport/nlm30/PKPSubmissionNlm30XmlFilter.inc.php
 *
 * Copyright (c) 2000-2010 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class PKPSubmissionNlm30XmlFilter
 * @ingroup importexport_nlm
 *
 * @brief Class that converts a submission to an NLM Journal Publishing
 * Tag Set 3.0 XML document.
 *
 * FIXME: This class currently only generates partial (citation) NLM XML output.
 * Full NLM journal publishing tag set support still has to be added, see #5648
 * and the L8X development roadmap.
 */


import('lib.pkp.plugins.metadata.nlm30.filter.TemplateBasedReferencesListFilter');

class PKPSubmissionNlm30XmlFilter extends TemplateBasedReferencesListFilter {
	/**
	 * Constructor
	 */
	function PKPSubmissionNlm30XmlFilter() {
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
		return 'lib.pkp.classes.importexport.nlm.PKPSubmissionNlm30XmlFilter';
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
		import('lib.pkp.plugins.citationOutput.nlm.Nlm30CitationSchemaNlm30Filter');
		$nlm30CitationOutputFilter = new Nlm30CitationSchemaNlm30Filter();
		return $nlm30CitationOutputFilter;
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
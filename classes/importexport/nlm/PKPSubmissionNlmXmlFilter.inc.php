<?php

/**
 * @defgroup importexport_nlm
 */

/**
 * @file classes/importexport/nlm/PKPSubmissionNlmXmlFilter.inc.php
 *
 * Copyright (c) 2000-2010 John Willinsky
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

import('lib.pkp.classes.filter.TemplateBasedFilter');

class PKPSubmissionNlmXmlFilter extends TemplateBasedFilter {
	/**
	 * Constructor
	 */
	function PKPSubmissionNlmXmlFilter() {
		parent::TemplateBasedFilter();
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
	 * @param $templateMgr TemplateManager
	 * @param $submission Submission
	 * @param $request Request
	 * @param $locale Locale
	 */
	function addTemplateVars(&$templateMgr, &$submission, &$request, &$locale) {
		// Retrieve assoc type and id of the submission.
		$assocId = $submission->getId();
		$assocType = $submission->getAssocType();

		// Retrieve citations for this assoc object.
		$citationDao =& DAORegistry::getDAO('CitationDAO');
		$citationResults =& $citationDao->getObjectsByAssocId($assocType, $assocId);
		$citations =& $citationResults->toAssociativeArray('seq');

		// Create NLM citation mark-up for these citations.
		import('lib.pkp.classes.metadata.nlm.NlmCitationSchema');
		$nlmCitationSchema = new NlmCitationSchema();
		import('lib.pkp.classes.citation.output.nlm.NlmCitationSchemaNlmFilter');
		$nlmCitationFilter = new NlmCitationSchemaNlmFilter();
		$citationsMarkup = array();
		foreach($citations as $seq => $citation) {
			$citationMetadata =& $citation->extractMetadata($nlmCitationSchema);
			$citationsMarkup[$seq] = $nlmCitationFilter->execute($citationMetadata);
		}

		// Add citation mark-up and submission to template.
		$templateMgr->assign_by_ref('citationsMarkup', $citationsMarkup);
		$templateMgr->assign_by_ref('submission', $submission);
	}

	/**
	 * @see TemplateBasedFilter::getBasePath()
	 */
	function getBasePath() {
		return dirname(__FILE__);
	}
}
?>
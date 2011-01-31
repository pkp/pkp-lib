<?php

/**
 * @defgroup citation_output
 */

/**
 * @file classes/citation/output/PlainTextReferencesListFilter.inc.php
 *
 * Copyright (c) 2000-2011 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class PlainTextReferencesListFilter
 * @ingroup importexport_nlm
 *
 * @brief Class that converts a submission to a plain text references list
 *  based on the configured ordering type and citation output filter.
 */


import('lib.pkp.classes.citation.output.TemplateBasedReferencesListFilter');
import('lib.pkp.classes.citation.output.PlainTextReferencesList');

class PlainTextReferencesListFilter extends TemplateBasedReferencesListFilter {
	/**
	 * Constructor
	 * @param $displayName string
	 * @param $citationOutputFilterName string
	 * @param $ordering integer one of the REFERENCES_LIST_ORDERING_* constants
	 */
	function PlainTextReferencesListFilter($displayName = null, $citationOutputFilterName = null, $ordering = null) {
		import('lib.pkp.classes.filter.FilterSetting');
		$this->addSetting(new FilterSetting('citationOutputFilterName', null, null));
		import('lib.pkp.classes.filter.SetFilterSetting');
		$this->addSetting(new SetFilterSetting('ordering', null, null,
				array(REFERENCES_LIST_ORDERING_ALPHABETICAL, REFERENCES_LIST_ORDERING_NUMERICAL)));

		// Configure the filter.
		if (!is_null($displayName)) $this->setDisplayName($displayName);
		if (!is_null($citationOutputFilterName)) $this->setData('citationOutputFilterName', $citationOutputFilterName);
		if (!is_null($ordering)) $this->setData('ordering', $ordering);

		parent::TemplateBasedReferencesListFilter();
	}


	//
	// Implement template methods from Filter
	//
	/**
	 * @see Filter::getClassName()
	 */
	function getClassName() {
		return 'lib.pkp.classes.citation.output.PlainTextReferencesListFilter';
	}

	/**
	 * @see Filter::getSupportedTransformation()
	 */
	function getSupportedTransformation() {
		return array(
			'class::lib.pkp.classes.submission.Submission',
			'class::lib.pkp.classes.citation.PlainTextReferencesList'
		);
	}

	/**
	 * @see Filter::process()
	 */
	function &process(&$input) {
		$output =& parent::process($input);
		$referencesList = new PlainTextReferencesList($output, $this->getData('ordering'));
		return $referencesList;
	}


	//
	// Implement template methods from TemplateBasedReferencesListFilter
	//
	/**
	 * @see TemplateBasedReferencesListFilter::getCitationOutputFilterInstance()
	 */
	function &getCitationOutputFilterInstance() {
		$citationOutputFilterName = $this->getData('citationOutputFilterName');
		$nlmCitationOutputFilter =& instantiate($citationOutputFilterName, 'NlmCitationSchemaCitationOutputFormatFilter');
		return $nlmCitationOutputFilter;
	}


	//
	// Implement template methods from TemplateBasedFilter
	//
	/**
	 * @see TemplateBasedFilter::addTemplateVars()
	 */
	function addTemplateVars(&$templateMgr, &$submission, &$request, &$locale) {
		parent::addTemplateVars($templateMgr, $submission, $request, $locale);

		// Add the ordering type to the template.
		$templateMgr->assign('ordering', $this->getData('ordering'));
	}

	/**
	 * @see TemplateBasedFilter::getTemplateName()
	 */
	function getTemplateName() {
		return 'references-list.tpl';
	}

	/**
	 * @see TemplateBasedFilter::getBasePath()
	 */
	function getBasePath() {
		return dirname(__FILE__);
	}
}
?>
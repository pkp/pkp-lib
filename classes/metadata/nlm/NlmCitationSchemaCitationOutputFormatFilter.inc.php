<?php

/**
 * @file classes/metadata/nlm/NlmCitationSchemaCitationOutputFormatFilter.inc.php
 *
 * Copyright (c) 2000-2012 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class NlmCitationSchemaCitationOutputFormatFilter
 * @ingroup classes_metadata_nlm
 *
 * @brief Abstract base class for all filters that transform
 *  NLM citation metadata descriptions into citation output formats
 *  via smarty template.
 */

import('lib.pkp.classes.filter.TemplateBasedFilter');

// This is a brand name so doesn't have to be translated...
define('GOOGLE_SCHOLAR_TAG', '[Google Scholar]');

class NlmCitationSchemaCitationOutputFormatFilter extends TemplateBasedFilter {
	/** @var The publication types supported by this output filter. */
	var $_supportedPublicationTypes;

	/**
	 * Constructor
	 */
	function NlmCitationSchemaCitationOutputFormatFilter() {
		parent::TemplateBasedFilter();
	}


	//
	// Setters and Getters
	//
	/**
	 * Set the supported publication types.
	 * @param $supportedPublicationTypes array
	 */
	function setSupportedPublicationTypes($supportedPublicationTypes) {
		$this->_supportedPublicationTypes = $supportedPublicationTypes;
	}

	/**
	 * Get the supported publication types.
	 * @return array
	 */
	function getSupportedPublicationTypes() {
		if (is_null($this->_supportedPublicationTypes)) {
			// Set default supported publication types.
			$this->_supportedPublicationTypes = array(
				NLM_PUBLICATION_TYPE_BOOK, NLM_PUBLICATION_TYPE_JOURNAL, NLM_PUBLICATION_TYPE_CONFPROC
			);
		}
		return $this->_supportedPublicationTypes;
	}


	//
	// Implement template methods from Filter
	//
	/**
	 * @see Filter::getSupportedTransformation()
	 */
	function getSupportedTransformation() {
		return array(
			'metadata::lib.pkp.classes.metadata.nlm.NlmCitationSchema(CITATION)',
			'primitive::string'
		);
	}

	/**
	 * @see Filter::process()
	 * @param $input MetadataDescription the NLM meta-data description
	 *  to be transformed
	 * @return string the rendered citation output
	 */
	function process(&$input) {
		// Check whether the incoming publication type is supported by this
		// output filter.
		$supportedPublicationTypes = $this->getSupportedPublicationTypes();
		$inputPublicationType = $input->getStatement('[@publication-type]');
		if (!in_array($inputPublicationType, $supportedPublicationTypes)) {
			$this->addError(__('submission.citations.filter.unsupportedPublicationType'));
			$emptyResult = '';
			return $emptyResult;
		}

		return parent::process($input);
	}


	//
	// Implement template methods from TemplateBasedFilter
	//
	/**
	 * Get the citation template
	 * @return string
	 */
	function getTemplateName() {
		return 'nlm-citation.tpl';
	}

	/**
	 * @see TemplateBasedFilter::addTemplateVars()
	 * @param $templateMgr TemplateManager
	 * @param $input MetadataDescription the NLM meta-data description
	 *  to be transformed
	 * @param $request Request
	 * @param $locale Locale
	 */
	function addTemplateVars(&$templateMgr, &$input, &$request, &$locale) {
		// Loop over the statements in the schema and add them
		// to the template
		$propertyNames =& $input->getPropertyNames();
		$setProperties = array();
		foreach($propertyNames as $propertyName) {
			$templateVariable = $input->getNamespacedPropertyId($propertyName);
			if ($input->hasStatement($propertyName)) {
				$propertyLocale = $input->getProperty($propertyName)->getTranslated() ? $locale : null;
				$templateMgr->assign_by_ref($templateVariable, $input->getStatement($propertyName, $propertyLocale));
			} else {
				// Delete potential leftovers from previous calls
				$templateMgr->clear_assign($templateVariable);
			}
		}
	}
}
?>
<?php

/**
 * @file classes/metadata/nlm/NlmCitationSchemaCitationOutputFormatFilter.inc.php
 *
 * Copyright (c) 2000-2010 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class NlmCitationSchemaCitationOutputFormatFilter
 * @ingroup classes_metadata_nlm
 *
 * @brief Abstract base class for all filters that transform
 *  NLM citation metadata descriptions into citation output formats
 *  via smarty template.
 */

import('lib.pkp.classes.filter.Filter');

// This is a brand name so doesn't have to be translated...
define('GOOGLE_SCHOLAR_TAG', '[Google Scholar]');

class NlmCitationSchemaCitationOutputFormatFilter extends Filter {
	/** @var The publication types supported by this output filter. */
	var $_supportedPublicationTypes;

	/**
	 * Constructor
	 * @param $request PKPRequest
	 */
	function NlmCitationSchemaCitationOutputFormatFilter() {
		parent::Filter();
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
	// Abstract template methods
	//
	/**
	 * Return the base path of the filter so that we
	 * can find the filter templates.
	 *
	 * @return string
	 */
	function getBasePath() {
		// Must be implemented by sub-classes.
		assert(false);
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
	 * @param $input MetadataDescription NLM citation description
	 * @return string formatted citation output
	 */
	function &process(&$input) {
		// Check whether the incoming publication type is supported by this
		// output filter.
		$supportedPublicationTypes = $this->getSupportedPublicationTypes();
		$inputPublicationType = $input->getStatement('[@publication-type]');
		if (!in_array($inputPublicationType, $supportedPublicationTypes)) {
			$this->addError(Locale::translate('submission.citations.filter.unsupportedPublicationType'));
			$emptyResult = '';
			return $emptyResult;
		}

		// Initialize view
		$locale = Locale::getLocale();
		$application =& PKPApplication::getApplication();
		$request =& $application->getRequest();
		$templateMgr =& TemplateManager::getManager($request);

		// Add the filter's directory as additional template dir so that
		// citation output format templates can include sub-templates in
		// the same folder.
		$templateMgr->template_dir[] = $this->getBasePath();

		// Loop over the statements in the schema and add them
		// to the template
		$propertyNames =& $input->getPropertyNames();
		foreach($propertyNames as $propertyName) {
			$templateVariable = $input->getNamespacedPropertyId($propertyName);
			if ($input->hasProperty($propertyName)) {
				$propertyLocale = $input->getProperty($propertyName)->getTranslated() ? $locale : null;
				$templateMgr->assign_by_ref($templateVariable, $input->getStatement($propertyName, $propertyLocale));
			} else {
				// Delete potential leftovers from previous calls
				$templateMgr->clear_assign($templateVariable);
			}
		}

		// Let the template engine render the citation
		$templateName = $this->_getCitationTemplate();
		$output = $templateMgr->fetch($templateName);

		// Remove the additional template dir
		array_pop($templateMgr->template_dir);

		return $output;
	}

	//
	// Private helper methods
	//
	/**
	 * Get the citation template
	 * @return string
	 */
	function _getCitationTemplate() {
		$basePath = $this->getBasePath();
		return 'file:'.$basePath.DIRECTORY_SEPARATOR.'nlm-citation.tpl';
	}
}
?>
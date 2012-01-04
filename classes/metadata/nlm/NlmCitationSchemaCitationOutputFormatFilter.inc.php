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

// $Id$

import('filter.Filter');

class NlmCitationSchemaCitationOutputFormatFilter extends Filter {
	/** @var PKPRequest */
	var $_request;

	/**
	 * Constructor
	 * @param $request PKPRequest
	 */
	function NlmCitationSchemaCitationOutputFormatFilter(&$request) {
		parent::Filter();

		// Load additional translations
		$locale = AppLocale::getLocale();
		$basePath = $this->getBasePath();
		$localeFile = $basePath.DIRECTORY_SEPARATOR.'locale'.DIRECTORY_SEPARATOR.$locale.DIRECTORY_SEPARATOR.'locale.xml';
		AppLocale::registerLocaleFile($locale, $localeFile);

		// Save the request for later use
		$this->_request =& $request;
	}

	//
	// Abstract template methods to be implemented by subclasses
	//
	/**
	 * Return the base path of the citation filter
	 * @return string
	 */
	function getBasePath() {
		assert(false);
	}

	//
	// Implement template methods from Filter
	//
	/**
	 * @see Filter::supports()
	 * @param $input mixed
	 * @param $output mixed
	 * @return boolean
	 */
	function supports(&$input, &$output) {
		// Check the input type
		if (!is_a($input, 'MetadataDescription')) return false;
		$metadataSchema =& $input->getMetadataSchema();
		if ($metadataSchema->getName() != 'nlm-3.0-element-citation') return false;

		// Check the output type
		if (is_null($output)) return true;
		return is_string($output);
	}

	/**
	 * @see Filter::process()
	 * @param $input MetadataDescription NLM citation description
	 * @return string formatted citation output
	 */
	function &process(&$input) {
		// Initialize view
		$locale = AppLocale::getLocale();
		$templateMgr =& TemplateManager::getManager($this->_request);

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
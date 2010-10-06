<?php

/**
 * @file classes/citation/TemplateBasedReferencesListFilter.inc.php
 *
 * Copyright (c) 2000-2010 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class TemplateBasedReferencesListFilter
 * @ingroup classes_citation
 *
 * @brief Abstract base class for filters that create a references
 *  list for a submission.
 */


import('lib.pkp.classes.filter.TemplateBasedFilter');

class TemplateBasedReferencesListFilter extends TemplateBasedFilter {
	/**
	 * Constructor
	 * @param $filterGroup FilterGroup
	 */
	function TemplateBasedReferencesListFilter(&$filterGroup) {
		// Add the persistable filter settings.
		import('lib.pkp.classes.filter.FilterSetting');
		$this->addSetting(new FilterSetting('citationOutputFilterName', null, null));
		$this->addSetting(new FilterSetting('metadataSchemaName', null, null));

		parent::TemplateBasedFilter($filterGroup);
	}


	//
	// Getters and Setters
	//
	/**
	 * Get the metadata schema being used to extract
	 * data from the citations.
	 * @return MetadataSchema
	 */
	function &getMetadataSchema() {
		$metadataSchemaName = $this->getData('metadataSchemaName');
		assert(!is_null($metadataSchemaName));
		$metadataSchema =& instantiate($metadataSchemaName, 'MetadataSchema');
		return $metadataSchema;
	}

	/**
	 * Retrieve the citation output filter that will be
	 * used to transform citations.
	 * @return TemplateBasedFilter
	 */
	function &getCitationOutputFilterInstance() {
		$citationOutputFilterName = $this->getData('citationOutputFilterName');
		assert(!is_null($citationOutputFilterName));
		$citationOutputFilter =& instantiate($citationOutputFilterName, 'TemplateBasedFilter');
		return $citationOutputFilter;
	}


	//
	// Implement template methods from TemplateBasedFilter
	//
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

		// Retrieve approved citations for this assoc object.
		$citationDao =& DAORegistry::getDAO('CitationDAO');
		$citationResults =& $citationDao->getObjectsByAssocId($assocType, $assocId, CITATION_APPROVED);
		$citations =& $citationResults->toAssociativeArray('seq');

		// Create citation output for these citations.
		$metadataSchema =& $this->getMetadataSchema();
		assert(is_a($metadataSchema, 'MetadataSchema'));
		$citationOutputFilter = $this->getCitationOutputFilterInstance();
		$citationsOutput = array();
		foreach($citations as $seq => $citation) {
			$citationMetadata =& $citation->extractMetadata($metadataSchema);
			$citationsOutput[$seq] = $citationOutputFilter->execute($citationMetadata);
		}

		// Add citation mark-up and submission to template.
		$templateMgr->assign_by_ref('citationsOutput', $citationsOutput);
		$templateMgr->assign_by_ref('submission', $submission);
	}
}
?>
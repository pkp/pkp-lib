<?php
/**
 * @file classes/citation/output/TemplateBasedReferencesListFilter.inc.php
 *
 * Copyright (c) 2000-2012 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class TemplateBasedReferencesListFilter
 * @ingroup citation_output
 *
 * @brief Abstract base class for filters that create a references
 *  list for a submission.
 */


import('lib.pkp.classes.filter.TemplateBasedFilter');

class TemplateBasedReferencesListFilter extends TemplateBasedFilter {
	/**
	 * Constructor
	 */
	function TemplateBasedReferencesListFilter() {
		parent::TemplateBasedFilter();
	}


	//
	// Template methods to be implemented by sub-classes.
	//
	/**
	 * Retrieve the citation output filter that will be
	 * used to transform citations.
	 * @return NlmCitationSchemaCitationOutputFormatFilter
	 */
	function &getCitationOutputFilterInstance() {
		// Must be implemented by sub-classes.
		assert(false);
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
		import('lib.pkp.classes.metadata.nlm.NlmCitationSchema');
		$nlmCitationSchema = new NlmCitationSchema();
		$citationOutputFilter = $this->getCitationOutputFilterInstance();
		$citationsOutput = array();
		foreach($citations as $seq => $citation) {
			$citationMetadata =& $citation->extractMetadata($nlmCitationSchema);
			$citationsOutput[$seq] = $citationOutputFilter->execute($citationMetadata);
		}

		// Add citation mark-up and submission to template.
		$templateMgr->assign_by_ref('citationsOutput', $citationsOutput);
		$templateMgr->assign_by_ref('submission', $submission);
	}
}
?>
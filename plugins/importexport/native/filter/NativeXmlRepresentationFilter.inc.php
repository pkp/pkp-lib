<?php

/**
 * @file plugins/importexport/native/filter/NativeXmlRepresentationFilter.inc.php
 *
 * Copyright (c) 2014-2016 Simon Fraser University Library
 * Copyright (c) 2000-2016 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class NativeXmlRepresentationFilter
 * @ingroup plugins_importexport_native
 *
 * @brief Base class that converts a Native XML document to a set of authors
 */

import('lib.pkp.plugins.importexport.native.filter.NativeImportFilter');

class NativeXmlRepresentationFilter extends NativeImportFilter {
	/**
	 * Constructor
	 * @param $filterGroup FilterGroup
	 */
	function NativeXmlRepresentationFilter($filterGroup) {
		$this->setDisplayName('Native XML representation import');
		parent::NativeImportFilter($filterGroup);
	}

	//
	// Implement template methods from PersistableFilter
	//
	/**
	 * @copydoc PersistableFilter::getClassName()
	 */
	function getClassName() {
		return 'lib.pkp.plugins.importexport.native.filter.NativeXmlRepresentationFilter';
	}


	/**
	 * Handle a submission element
	 * @param $node DOMElement
	 * @return array Array of Representation objects
	 */
	function handleElement($node) {
		$deployment = $this->getDeployment();
		$context = $deployment->getContext();
		$submission = $deployment->getSubmission();
		assert(is_a($submission, 'Submission'));

		// Create the data object
		$representationDao  = Application::getRepresentationDAO();
		$representation = $representationDao->newDataObject();
		$representation->setSubmissionId($submission->getId());

		// Handle metadata in subelements.  Look for the 'name' and 'seq' elements.
		// All other elements are handled by subclasses.
		for ($n = $node->firstChild; $n !== null; $n=$n->nextSibling) if (is_a($n, 'DOMElement')) switch($n->tagName) {
			case 'name': $representation->setName($n->textContent, $n->getAttribute('locale')); break;
			case 'seq': $representation->setSeq($n->textContent); break;
			case 'remote': $representation->setRemoteURL($n->getAttribute('src')); break;

		}

		return $representation; // database insert is handled by sub class.
	}
}

?>

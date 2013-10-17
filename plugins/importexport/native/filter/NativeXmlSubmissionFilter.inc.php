<?php

/**
 * @file plugins/importexport/native/filter/NativeXmlSubmissionFilter.inc.php
 *
 * Copyright (c) 2000-2013 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class NativeXmlSubmissionFilter
 * @ingroup plugins_importexport_native
 *
 * @brief Base class that converts a Native XML document to a set of submissions
 */

import('lib.pkp.plugins.importexport.native.filter.NativeImportFilter');

class NativeXmlSubmissionFilter extends NativeImportFilter {
	/**
	 * Constructor
	 * @param $filterGroup FilterGroup
	 */
	function NativeXmlSubmissionFilter($filterGroup) {
		$this->setDisplayName('Native XML submission import');
		parent::NativeImportFilter($filterGroup);
	}


	//
	// Implement template methods from PersistableFilter
	//
	/**
	 * @copydoc PersistableFilter::getClassName()
	 */
	function getClassName() {
		return 'lib.pkp.plugins.importexport.native.filter.NativeXmlSubmissionFilter';
	}


	//
	// Implement template methods from NativeImportFilter
	//
	/**
	 * Return the plural element name
	 * @return string
	 */
	function getPluralElementName() {
		$deployment = $this->getDeployment();
		return $deployment->getSubmissionsNodeName();
	}

	/**
	 * Get the singular element name
	 * @return string
	 */
	function getSingularElementName() {
		$deployment = $this->getDeployment();
		return $deployment->getSubmissionNodeName();
	}

	/**
	 * Handle a singular element import.
	 * @param $node DOMElement
	 */
	function handleElement($node) {
		$deployment = $this->getDeployment();
		$context = $deployment->getContext();
		$user = $deployment->getUser();

		// Create and insert the submission (ID needed for other entities)
		$submissionDao = Application::getSubmissionDAO();
		$submission = $submissionDao->newDataObject();
		$submission->setContextId($context->getId());
		$submission->setUserId($user->getId());
		$submission->setStatus(STATUS_QUEUED);
		$submission->setSubmissionProgress(0);
		$submissionDao->insertObject($submission);
		$deployment->setSubmission($submission);

		$filterDao = DAORegistry::getDAO('FilterDAO');

		$setterMappings = $this->getLocalizedSubmissionSetterMappings();
		for ($n = $node->firstChild; $n !== null; $n=$n->nextSibling) if (is_a($n, 'DOMElement')) {
			// If applicable, call a setter for localized content
			if (isset($setterMappings[$n->tagName])) {
				$setterFunction = $setterMappings[$n->tagName];
				list($locale, $value) = $this->parseLocalizedContent($n);
				$submission->$setterFunction($value, $locale);
			} else switch ($n->tagName) {
				// Otherwise, delegate to specific parsing code
				case 'id':
					$this->parseIdentifier($n, $submission);
					break;
				case 'author':
					$importFilters = $filterDao->getObjectsByGroup('native-xml=>author');
					assert(count($importFilters)==1); // Assert only a single unserialization filter
					$importFilter = array_shift($importFilters);
					$importFilter->setDeployment($this->getDeployment());
					$authorDoc = new DOMDocument();
					$authorDoc->appendChild($authorDoc->importNode($n, true));
					$importFilter->execute($authorDoc);
					break;
			}
		}

		return $submission;
	}

	//
	// Element parsing
	//
	/**
	 * Parse an identifier node and set up the submission object accordingly
	 * @param $element DOMElement
	 * @param $submission Submission
	 */
	function parseIdentifier($element, $submission) {
		switch ($element->getAttribute('type')) {
			case 'internal':
				// Currently internal IDs are discarded; new IDs
				// are generated and assigned.
				break;
			case 'public':
				$submission->setStoredPubId('publisher-id', $element->textContent);
				break;
			default:
				$submission->setStoredPubId($element->getAttribute('type'), $element->textContent);
		}
	}

	/**
	 * Parse a localized element
	 * @param $element DOMElement
	 * @return array Array("locale_KEY", "Localized Text")
	 */
	function parseLocalizedContent($element) {
		assert($element->hasAttribute('locale'));
		return array($element->getAttribute('locale'), $element->textContent);
	}

	/**
	 * Get node name to setter function mapping for localized data.
	 * @return array
	 */
	function getLocalizedSubmissionSetterMappings() {
		return array(
			'title' => 'setTitle',
			'prefix' => 'setPrefix',
			'subtitle' => 'setSubtitle',
			'abstract' => 'setAbstract',
			'subject_class' => 'setSubjectClass',
			'coverage_geo' => 'setCoverageGeo',
			'coverage_chron' => 'setCoverageChron',
			'coverage_sample' => 'setCoverageSample',
			'source' => 'setSource',
			'rights' => 'setRights',
		);
	}
}

?>

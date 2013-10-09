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

import('lib.pkp.plugins.importexport.native.filter.NativeImportExportFilter');
import('lib.pkp.classes.xml.XMLCustomWriter');

class NativeXmlSubmissionFilter extends NativeImportExportFilter {
	/**
	 * Constructor
	 * $filterGroup FilterGroup
	 */
	function NativeXmlSubmissionFilter($filterGroup) {
		$this->setDisplayName('Native XML import');
		parent::NativeImportExportFilter($filterGroup);
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
	// Implement template methods from Filter
	//
	/**
	 * @see Filter::process()
	 * @param $document DOMDocument|string
	 * @return array Array of imported documents
	 */
	function &process(&$document) {
		// If necessary, convert $document to a DOMDocument.
		if (is_string($document)) {
			$xmlString = $document;
			$document = new DOMDocument();
			$document->loadXml($xmlString);
		}
		assert(is_a($document, 'DOMDocument'));

		$deployment = $this->getDeployment();
		$submissions = array();
		if ($document->documentElement->tagName == $deployment->getSubmissionsNodeName()) {
			// Multiple document import
			for ($n = $document->documentElement->firstChild; $n !== null; $n=$n->nextSibling) {
				if (!is_a($n, 'DOMElement')) continue;
				$submissions[] = $this->handleSubmissionElement($n);
			}
		} else {
			// Single document import
			$submissions[] = $this->handleSubmissionElement($document->documentElement);
		}

		return $submissions;
	}

	/**
	 * Handle a submission element
	 * @param $submissionElement DOMElement
	 * @return Submission
	 */
	function handleSubmissionElement($submissionElement) {
		$deployment = $this->getDeployment();
		assert($submissionElement->tagName == $deployment->getSubmissionNodeName());

		$submissionDao = Application::getSubmissionDAO();
		$submission = $submissionDao->newDataObject();

		$setterMappings = $this->getLocalizedSubmissionSetterMappings();
		for ($n = $submissionElement->firstChild; $n !== null; $n=$n->nextSibling) if (is_a($n, 'DOMElement')) {
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
		// FIXME: Parse identifier node
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

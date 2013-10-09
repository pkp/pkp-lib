<?php
/**
 * @defgroup plugins_metadata_native_filter Native XML to submission filter base
 */

/**
 * @file plugins/metadata/native/filter/NativeXmlSubmissionFilter.inc.php
 *
 * Copyright (c) 2000-2013 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class NativeXmlSubmissionFilter
 * @ingroup plugins_importexport_native_filter
 *
 * @brief Base class that converts a Native XML document to a set of submissions
 */

import('lib.pkp.classes.filter.PersistableFilter');
import('lib.pkp.classes.xml.XMLCustomWriter');

class NativeXmlSubmissionFilter extends PersistableFilter {
	/**
	 * Constructor
	 * $filterGroup FilterGroup
	 */
	function NativeXmlSubmissionFilter($filterGroup) {
		$this->setDisplayName('Native XML import');
		parent::PersistableFilter($filterGroup);
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

		$submissions = array();
		if ($document->documentElement->tagName == $this->getSubmissionsNodeName()) {
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
		assert($submissionElement->tagName == $this->getSubmissionNodeName());

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

	//
	// Identifying information
	//
	/**
	 * Get the submission node name
	 * @return string
	 */
	function getSubmissionNodeName() {
		return 'submission';
	}

	/**
	 * Get the submissions node name
	 * @return string
	 */
	function getSubmissionsNodeName() {
		return 'submissions';
	}
}

?>

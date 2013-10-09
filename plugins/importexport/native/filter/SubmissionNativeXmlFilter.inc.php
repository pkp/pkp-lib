<?php
/**
 * @defgroup plugins_metadata_native_filter Native XML submission filter base
 */

/**
 * @file plugins/metadata/native/filter/SubmissionNativeXmlFilter.inc.php
 *
 * Copyright (c) 2000-2013 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class SubmissionNativeXmlFilter
 * @ingroup plugins_importexport_native_filter
 *
 * @brief Base class that converts a Submission to a Native XML document.
 */

import('lib.pkp.classes.filter.PersistableFilter');
import('lib.pkp.classes.xml.XMLCustomWriter');

class SubmissionNativeXmlFilter extends PersistableFilter {
	/**
	 * Constructor
	 * $filterGroup FilterGroup
	 */
	function SubmissionNativeXmlFilter($filterGroup) {
		$this->setDisplayName('Native XML');
		parent::PersistableFilter($filterGroup);
	}


	//
	// Implement template methods from PersistableFilter
	//
	/**
	 * @copydoc PersistableFilter::getClassName()
	 */
	function getClassName() {
		return 'lib.pkp.plugins.importexport.native.filter.SubmissionNativeXmlFilter';
	}


	//
	// Implement template methods from Filter
	//
	/**
	 * @see Filter::process()
	 * @param $submission Submission
	 */
	function &process(&$submission) {
		// Create the XML document
		$doc = new DOMDocument('1.0');

		$doc->appendChild($this->createSubmissionNode($doc, $submission));
		return $doc;
	}

	//
	// Submission conversion functions
	//
	function createSubmissionNode($doc, $submission) {
		// Create the root node and namespace information
		$submissionNode = $doc->createElementNS($this->getNamespace(), $this->getSubmissionNodeName());
		$submissionNode->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:xsi', 'http://www.w3.org/2001/XMLSchema-instance');
		$submissionNode->setAttribute('xsi:schemaLocation', $this->getNamespace() . ' ' . $this->getSchemaFilename());
		$submissionNode->setAttribute('locale', $submission->getLocale());
		// FIXME: language attribute (from old DTD). Necessary? Data migration needed?
		// FIXME: public_id attribute (from old DTD). Necessary? Move to <id> element below?

		$this->addIdentifiers($doc, $submissionNode, $submission);
		$this->addMetadata($doc, $submissionNode, $submission);

		return $submissionNode;
	}

	function addIdentifiers($doc, $submissionNode, $submission) {
		$submissionNode->appendChild($doc->createElementNS($this->getNamespace(), 'id', $submission->getId()));
		// FIXME: Other identifiers
	}

	function addMetadata($doc, $submissionNode, $submission) {
		$this->createLocalizedNodes($doc, $submissionNode, 'title', $submission->getTitle(null));
		$this->createLocalizedNodes($doc, $submissionNode, 'prefix', $submission->getPrefix(null));
		$this->createLocalizedNodes($doc, $submissionNode, 'subtitle', $submission->getSubtitle(null));
		$this->createLocalizedNodes($doc, $submissionNode, 'abstract', $submission->getAbstract(null));
		$this->createLocalizedNodes($doc, $submissionNode, 'subject_class', $submission->getSubjectClass(null));
		$this->createLocalizedNodes($doc, $submissionNode, 'coverage_geo', $submission->getCoverageGeo(null));
		$this->createLocalizedNodes($doc, $submissionNode, 'coverage_chron', $submission->getCoverageChron(null));
		$this->createLocalizedNodes($doc, $submissionNode, 'coverage_sample', $submission->getCoverageSample(null));
		$this->createLocalizedNodes($doc, $submissionNode, 'type', $submission->getType(null));
		$this->createLocalizedNodes($doc, $submissionNode, 'source', $submission->getSource(null));
		$this->createLocalizedNodes($doc, $submissionNode, 'rights', $submission->getRights(null));
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


	/**
	 * Get the namespace URN
	 * @return string
	 */
	function getNamespace() {
		return 'http://pkp.sfu.ca';
	}

	/**
	 * Get the schema filename.
	 * @return string
	 */
	function getSchemaFilename() {
		return 'pkp-native.xsd';
	}

	//
	// Helper functions
	//
	/**
	 * Create a set of child nodes of parentNode containing the
	 * localeKey => value data representing translated content.
	 * @param $doc DOMDocument
	 * @param $parentNode DOMNode
	 * @param $name string Node name
	 * @param $values array Array of locale key => value mappings
	 */
	function createLocalizedNodes($doc, $parentNode, $name, $values) {
		foreach ($values as $locale => $value) {
			if ($value === '') continue; // Skip empty values
			$parentNode->appendChild($node = $doc->createElementNS($this->getNamespace(), $name, $value));
			$node->setAttribute('locale', $locale);
		}
	}
}

?>

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
		$xml = $doc->saveXML();
		return $xml;
	}

	//
	// Submission conversion functions
	//
	function createSubmissionNode($doc, $submission) {
		// Create the root node and namespace information
		$submissionNode = $doc->createElement($this->getSubmissionNodeName());
		$submissionNode->setAttribute('xmlns', $this->getNamespace());
		$submissionNode->setAttribute('xmlns:xsi', 'http://www.w3.org/2001/XMLSchema-instance');
		$submissionNode->setAttribute('xsi:schemaLocation', $this->getNamespace() . ' ' . $this->getSchemaFilename());
		$submissionNode->setAttribute('locale', $submission->getLocale());
		// FIXME: language attribute (from old DTD). Necessary? Data migration needed?
		// FIXME: public_id attribute (from old DTD). Necessary? Move to <id> element below?

		$this->addIdentifiers($doc, $submissionNode, $submission);
		$this->addMetadata($doc, $submissionNode, $submission);

		return $submissionNode;
	}

	function addIdentifiers($doc, $submissionNode, $submission) {
		$submissionNode->appendChild($doc->createElement('id', $submission->getId()));
		// FIXME: Other identifiers
	}

	function addMetadata($doc, $submissionNode, $submission) {
		// Titles
		foreach ($submission->getTitle(null) as $locale => $value) {
			$submissionNode->appendChild($node = $doc->createElement('title', $value));
			$node->setAttribute('locale', $locale);
		}

		// Abstracts
		foreach ($submission->getAbstract(null) as $locale => $value) {
			$submissionNode->appendChild($node = $doc->createElement('abstract', $value));
			$node->setAttribute('locale', $locale);
		}
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
}

?>

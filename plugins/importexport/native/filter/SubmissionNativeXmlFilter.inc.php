<?php

/**
 * @file plugins/importexport/native/filter/SubmissionNativeXmlFilter.inc.php
 *
 * Copyright (c) 2000-2013 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class SubmissionNativeXmlFilter
 * @ingroup plugins_importexport_native
 *
 * @brief Base class that converts a set of submissions to a Native XML document
 */

import('lib.pkp.plugins.importexport.native.filter.NativeImportExportFilter');

class SubmissionNativeXmlFilter extends NativeImportExportFilter {
	/**
	 * Constructor
	 * $filterGroup FilterGroup
	 */
	function SubmissionNativeXmlFilter($filterGroup) {
		$this->setDisplayName('Native XML export');
		parent::NativeImportExportFilter($filterGroup);
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
	 * @param $submissions array Array of submissions
	 * @return DOMDocument
	 */
	function &process(&$submissions) {
		// Create the XML document
		$doc = new DOMDocument('1.0');
		$deployment = $this->getDeployment();

		if (count($submissions)==1) {
			// Only one submission specified; create root node
			$rootNode = $this->createSubmissionNode($doc, $submissions[0]);
		} else {
			// Multiple submissions; wrap in a <submissions> element
			$rootNode = $doc->createElementNS($deployment->getNamespace(), $deployment->getSubmissionsNodeName());
			foreach ($submissions as $submission) {
				$rootNode->appendChild($this->createSubmissionNode($doc, $submission));
			}
		}
		$doc->appendChild($rootNode);
		$rootNode->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:xsi', 'http://www.w3.org/2001/XMLSchema-instance');
		$rootNode->setAttribute('xsi:schemaLocation', $deployment->getNamespace() . ' ' . $deployment->getSchemaFilename());

		return $doc;
	}

	//
	// Submission conversion functions
	//
	function createSubmissionNode($doc, $submission) {
		// Create the root node and namespace information
		$deployment = $this->getDeployment();
		$submissionNode = $doc->createElementNS($deployment->getNamespace(), $deployment->getSubmissionNodeName());
		$submissionNode->setAttribute('locale', $submission->getLocale());
		// FIXME: language attribute (from old DTD). Necessary? Data migration needed?
		// FIXME: public_id attribute (from old DTD). Necessary? Move to <id> element below?

		$this->addIdentifiers($doc, $submissionNode, $submission);
		$this->addMetadata($doc, $submissionNode, $submission);

		return $submissionNode;
	}

	function addIdentifiers($doc, $submissionNode, $submission) {
		$deployment = $this->getDeployment();
		$submissionNode->appendChild($doc->createElementNS($deployment->getNamespace(), 'id', $submission->getId()));
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
		$deployment = $this->getDeployment();
		foreach ($values as $locale => $value) {
			if ($value === '') continue; // Skip empty values
			$parentNode->appendChild($node = $doc->createElementNS($deployment->getNamespace(), $name, $value));
			$node->setAttribute('locale', $locale);
		}
	}
}

?>

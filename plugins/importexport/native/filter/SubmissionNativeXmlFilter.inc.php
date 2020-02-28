<?php

/**
 * @file plugins/importexport/native/filter/SubmissionNativeXmlFilter.inc.php
 *
 * Copyright (c) 2014-2020 Simon Fraser University
 * Copyright (c) 2000-2020 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class SubmissionNativeXmlFilter
 * @ingroup plugins_importexport_native
 *
 * @brief Base class that converts a set of submissions to a Native XML document
 */

import('lib.pkp.plugins.importexport.native.filter.NativeExportFilter');

class SubmissionNativeXmlFilter extends NativeExportFilter {

	var $_includeSubmissionsNode;

	/**
	 * Constructor
	 * @param $filterGroup FilterGroup
	 */
	function __construct($filterGroup) {
		$this->setDisplayName('Native XML submission export');
		parent::__construct($filterGroup);
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
		$doc->preserveWhiteSpace = false;
		$doc->formatOutput = true;
		$deployment = $this->getDeployment();

		if (count($submissions)==1 && !$this->getIncludeSubmissionsNode()) {
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
	/**
	 * Create and return a submission node.
	 * @param $doc DOMDocument
	 * @param $submission Submission
	 * @return DOMElement
	 */
	function createSubmissionNode($doc, $submission) {
		// Create the root node and attributes
		$deployment = $this->getDeployment();
		$deployment->setSubmission($submission);
		$submissionNode = $doc->createElementNS($deployment->getNamespace(), $deployment->getSubmissionNodeName());

		$submissionNode->setAttribute('date_submitted', strftime('%Y-%m-%d', strtotime($submission->getData('dateSubmitted'))));
		$submissionNode->setAttribute('status', $submission->getData('status'));
		$submissionNode->setAttribute('submission_progress', $submission->getData('submissionProgress'));
		$submissionNode->setAttribute('current_publication_id', $submission->getData('currentPublicationId'));

		$workflowStageDao = DAORegistry::getDAO('WorkflowStageDAO'); /** @var $workflowStageDao WorkflowStageDAO */
		$submissionNode->setAttribute('stage', WorkflowStageDAO::getPathFromId($submission->getData('stageId')));

		// FIXME: language attribute (from old DTD). Necessary? Data migration needed?

		$this->addIdentifiers($doc, $submissionNode, $submission);
		$this->addFiles($doc, $submissionNode, $submission);
		$this->addPublications($doc, $submissionNode, $submission);

		return $submissionNode;
	}

	/**
	 * Create and add identifier nodes to a submission node.
	 * @param $doc DOMDocument
	 * @param $submissionNode DOMElement
	 * @param $submission Submission
	 */
	function addIdentifiers($doc, $submissionNode, $submission) {
		$deployment = $this->getDeployment();

		// Add internal ID
		$submissionNode->appendChild($node = $doc->createElementNS($deployment->getNamespace(), 'id', $submission->getId()));
		$node->setAttribute('type', 'internal');
		$node->setAttribute('advice', 'ignore');

		// // Add pub IDs by plugin
		// $pubIdPlugins = PluginRegistry::loadCategory('pubIds', true, $deployment->getContext()->getId());
		// foreach ($pubIdPlugins as $pubIdPlugin) {
		// 	$this->addPubIdentifier($doc, $submissionNode, $submission, $pubIdPlugin);
		// }
	}

	/**
	 * Add submission controlled vocabulary to its DOM element.
	 * @param $doc DOMDocument
	 * @param $submissionNode DOMElement
	 * @param $controlledVocabulariesNodeName string Parent node name
	 * @param $controlledVocabularyNodeName string Item node name
	 * @param $controlledVocabulary array Associative array (locale => array of items)
	 */
	function addControlledVocabulary($doc, $submissionNode, $controlledVocabulariesNodeName, $controlledVocabularyNodeName, $controlledVocabulary) {
		$deployment = $this->getDeployment();
		$locales = array_keys($controlledVocabulary);
		foreach ($locales as $locale) {
			if (!empty($controlledVocabulary[$locale])) {
				$controlledVocabulariesNode = $doc->createElementNS($deployment->getNamespace(), $controlledVocabulariesNodeName);
				$controlledVocabulariesNode->setAttribute('locale', $locale);
				foreach ($controlledVocabulary[$locale] as $controlledVocabularyItem) {
					$controlledVocabulariesNode->appendChild($node = $doc->createElementNS($deployment->getNamespace(), $controlledVocabularyNodeName, htmlspecialchars($controlledVocabularyItem, ENT_COMPAT, 'UTF-8')));
				}
				$submissionNode->appendChild($controlledVocabulariesNode);
			}
		}
	}

	/**
	 * Add the submission files to its DOM element.
	 * @param $doc DOMDocument
	 * @param $submissionNode DOMElement
	 * @param $submission Submission
	 */
	function addFiles($doc, $submissionNode, $submission) {
		$filterDao = DAORegistry::getDAO('FilterDAO'); /* @var $filterDao FilterDAO */
		$submissionFileDao = DAORegistry::getDAO('SubmissionFileDAO'); /* @var $submissionFileDao SubmissionFileDAO */
		$submissionFiles = $submissionFileDao->getBySubmissionId($submission->getId());

		// Submission files will come back from the file export filter
		// with one revision each, wrapped in a submission_file node:
		// <submission_file ...>
		//  <revision ...>...</revision>
		// </submission_file>
		// Reformat them into groups by submission_file, i.e.:
		// <submission_file ...>
		//  <revision ...>...</revision>
		//  <revision ...>...</revision>
		// </submission_file>
		$submissionFileNodesByFileId = array();
		foreach ($submissionFiles as $submissionFile) {
			$nativeExportFilters = $filterDao->getObjectsByGroup(get_class($submissionFile) . '=>native-xml');
			assert(count($nativeExportFilters)==1); // Assert only a single serialization filter
			$exportFilter = array_shift($nativeExportFilters);
			$exportFilter->setDeployment($this->getDeployment());

			$exportFilter->setOpts($this->opts);
			$submissionFileDoc = $exportFilter->execute($submissionFile);
			$fileId = $submissionFileDoc->documentElement->getAttribute('id');
			if (!isset($submissionFileNodesByFileId[$fileId])) {
				$clone = $doc->importNode($submissionFileDoc->documentElement, true);
				$submissionNode->appendChild($clone);
				$submissionFileNodesByFileId[$fileId] = $clone;
			} else {
				$submissionFileNode = $submissionFileNodesByFileId[$fileId];
				// Look for a <revision> element
				$revisionNode = null;
				foreach ($submissionFileDoc->documentElement->childNodes as $childNode) {
					if (!is_a($childNode, 'DOMElement')) continue;
					if ($childNode->tagName == 'revision') $revisionNode = $childNode;
				}
				assert(is_a($revisionNode, 'DOMElement'));
				$clone = $doc->importNode($revisionNode, true);
				$firstRevisionChild = $submissionFileNode->firstChild;
				$submissionFileNode->insertBefore($clone, $firstRevisionChild);
			}
		}
	}

	/**
	 * Add the submission files to its DOM element.
	 * @param $doc DOMDocument
	 * @param $submissionNode DOMElement
	 * @param $submission Submission
	 */
	function addPublications($doc, $submissionNode, $submission) {
		$filterDao = DAORegistry::getDAO('FilterDAO'); /** @var $filterDao FilterDAO */
		$nativeExportFilters = $filterDao->getObjectsByGroup('publication=>native-xml');
		assert(count($nativeExportFilters)==1); // Assert only a single serialization filter
		$exportFilter = array_shift($nativeExportFilters);
		$exportFilter->setDeployment($this->getDeployment());

		$publications = (array) $submission->getData('publications');
		foreach ($publications as $publication) {
			$publicationDoc = $exportFilter->execute($publication);
			if ($publicationDoc->documentElement instanceof DOMElement) {
				$clone = $doc->importNode($publicationDoc->documentElement, true);
				$submissionNode->appendChild($clone);
			}
		}
	}


	//
	// Abstract methods for subclasses to implement
	//

	/**
	 * Sets a flag to always include the <submissions> node, even if there
	 * may only be one submission.
	 * @param boolean $includeSubmissionsNode
	 */
	function setIncludeSubmissionsNode($includeSubmissionsNode) {
		$this->_includeSubmissionsNode = $includeSubmissionsNode;
	}

	/**
	 * Returnes whether to always include the <submissions> node, even if there
	 * may only be one submission.
	 * @return boolean $includeSubmissionsNode
	 */
	function getIncludeSubmissionsNode() {
		return $this->_includeSubmissionsNode;
	}
}



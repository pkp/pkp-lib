<?php

/**
 * @file plugins/importexport/native/filter/NativeXmlSubmissionFilter.inc.php
 *
 * Copyright (c) 2014-2016 Simon Fraser University Library
 * Copyright (c) 2000-2016 John Willinsky
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
		$submission->setStatus(STATUS_QUEUED);
		$submission->setLocale($node->getAttribute('locale'));
		$submission->setSubmissionProgress(0);
		$workflowStageDao = DAORegistry::getDAO('WorkflowStageDAO');
		$submission->setStageId(WorkflowStageDAO::getIdFromPath($node->getAttribute('stage')));
		$submissionDao->insertObject($submission);
		$deployment->setSubmission($submission);

		// Handle any additional attributes etc.
		$submission = $this->populateObject($submission, $node);

		for ($n = $node->firstChild; $n !== null; $n=$n->nextSibling) {
			if (is_a($n, 'DOMElement')) {
				$this->handleChildElement($n, $submission);
			}
		}
		$submissionDao->updateObject($submission); // Persist setters
		return $submission;
	}

	/**
	 * Populate the submission object from the node
	 * @param $submission Submission
	 * @param $node DOMElement
	 * @return Submission
	 */
	function populateObject($submission, $node) {
		$submissionDao = Application::getSubmissionDAO();
		if ($dateSubmitted = $node->getAttribute('date_submitted')) {
			$submission->setDateSubmitted(strtotime($dateSubmitted));
		}
		$submissionDao->updateObject($submission);
		// If the date_published was set, add a published submission
		if ($datePublished = $node->getAttribute('date_published')) {
			$publishedSubmissionDao = $this->getPublishedSubmissionDAO();
			$publishedSubmission = $publishedSubmissionDao->newDataObject();
			$publishedSubmission->setId($submission->getId());
			$publishedSubmission->setDatePublished(strtotime($datePublished));
			$insertMethod = $this->getPublishedSubmissionInsertMethod();
			$publishedSubmission = $this->populatePublishedSubmission($publishedSubmission, $node);
			$publishedSubmissionDao->$insertMethod($publishedSubmission);
			// Reload from DB now that some fields may have changed
			$submission = $submissionDao->getById($submission->getId());
		}
		return $submission;
	}

	/**
	 * Handle an element whose parent is the submission element.
	 * @param $n DOMElement
	 * @param $submission Submission
	 */
	function handleChildElement($n, $submission) {
		$setterMappings = $this->_getLocalizedSubmissionSetterMappings();
		if (isset($setterMappings[$n->tagName])) {
			// If applicable, call a setter for localized content
			$setterFunction = $setterMappings[$n->tagName];
			list($locale, $value) = $this->parseLocalizedContent($n);
			$submission->$setterFunction($value, $locale);
		} else switch ($n->tagName) {
			// Otherwise, delegate to specific parsing code
			case 'id':
				$this->parseIdentifier($n, $submission);
				break;
			case 'authors':
				$this->parseAuthors($n, $submission);
				break;
			case 'submission_file':
				$this->parseSubmissionFile($n, $submission);
				break;
			case 'comments_to_editor':
				$submission->setCommentsToEditor($n->textContent);
				break;
			default:
				fatalError('Unknown element ' . $n->tagName);
		}
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
				// "update" advice not supported yet.
				$advice = $element->getAttribute('advice');
				assert(!$advice || $advice == 'ignore');
				break;
			case 'public':
				$submission->setStoredPubId('publisher-id', $element->textContent);
				break;
			default:
				$submission->setStoredPubId($element->getAttribute('type'), $element->textContent);
		}
	}

	/**
	 * Parse an authors element
	 * @param $node DOMElement
	 * @param $submission Submission
	 */
	function parseAuthors($node, $submission) {
		for ($n = $node->firstChild; $n !== null; $n=$n->nextSibling) {
			if (is_a($n, 'DOMElement')) {
				assert($n->tagName == 'author');
				$this->parseAuthor($n, $submission);
			}
		}
	}

	/**
	 * Parse an author and add it to the submission.
	 * @param $n DOMElement
	 * @param $submission Submission
	 */
	function parseAuthor($n, $submission) {
		$filterDao = DAORegistry::getDAO('FilterDAO');
		$importFilters = $filterDao->getObjectsByGroup('native-xml=>author');
		assert(count($importFilters)==1); // Assert only a single unserialization filter
		$importFilter = array_shift($importFilters);
		$importFilter->setDeployment($this->getDeployment());
		$authorDoc = new DOMDocument();
		$authorDoc->appendChild($authorDoc->importNode($n, true));
		return $importFilter->execute($authorDoc);
	}

	/**
	 * Parse a submission file and add it to the submission.
	 * @param $n DOMElement
	 * @param $submission Submission
	 */
	function parseSubmissionFile($n, $submission) {
		$importFilter = $this->getImportFilter($n->tagName);
		assert($importFilter); // There should be a filter

		$importFilter->setDeployment($this->getDeployment());
		$submissionFileDoc = new DOMDocument();
		$submissionFileDoc->appendChild($submissionFileDoc->importNode($n, true));
		return $importFilter->execute($submissionFileDoc);
	}

	//
	// Helper functions
	//
	/**
	 * Get node name to setter function mapping for localized data.
	 * @return array
	 */
	function _getLocalizedSubmissionSetterMappings() {
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

	/**
	 * Get the published submission DAO for this application.
	 * @return DAO
	 */
	function getPublishedSubmissionDAO() {
		assert(false); // Subclasses must override
	}

	/**
	 * Get the method name for inserting a published submission.
	 * @return string
	 */
	function getPublishedSubmissionInsertMethod() {
		assert(false); // Subclasses must override.
	}

	/**
	 * Get the representation export filter group name
	 * @return string
	 */
	function getRepresentationExportFilterGroupName() {
		assert(false); // Subclasses must override
	}

	/**
	 * Get the import filter for a given element.
	 * @param $elementName string Name of XML element
	 * @return Filter
	 */
	function getImportFilter($elementName) {
		assert(false); // Subclasses should override
	}

	/**
	 * Class-specific methods for published submissions.
	 * @param PublishedSubmission $submission
	 * @param DOMElement $node
	 * @return PublishedSubmission
	 */
	function populatePublishedSubmission($submission, $node) {
		assert(false); // Subclasses should override.
	}
}

?>

<?php

/**
 * @file plugins/importexport/native/filter/ReviewRoundNativeXmlFilter.inc.php
 *
 * Copyright (c) 2014-2018 Simon Fraser University
 * Copyright (c) 2000-2018 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class ReviewRoundNativeXmlFilter
 * @ingroup plugins_importexport_native
 *
 * @brief Base class that converts a set of review rounds to a Native XML document
 */

import('lib.pkp.plugins.importexport.native.filter.NativeExportFilter');

class ContextNativeXmlFilter extends NativeExportFilter {
	/**
	 * Constructor
	 * @param $filterGroup FilterGroup
	 */
	function __construct($filterGroup) {
		$this->setDisplayName('Native XML context export');
		parent::__construct($filterGroup);
	}


	//
	// Implement template methods from PersistableFilter
	//
	/**
	 * @copydoc PersistableFilter::getClassName()
	 */
	function getClassName() {
		return 'lib.pkp.plugins.importexport.native.filter.ContextNativeXmlFilter';
	}


	//
	// Implement template methods from Filter
	//
	/**
	 * @see Filter::process()
	 * @param $contexts array Context
	 * @return DOMDocument
	 */
	function &process(&$contexts) {
		// Create the XML document
		$doc = new DOMDocument('1.0');
		$doc->preserveWhiteSpace = false;
		$doc->formatOutput = true;
		$deployment = $this->getDeployment();

		// Wrap in a <contexts> element
		$rootNode = $doc->createElementNS($deployment->getNamespace(), 'contexts');
		foreach ($contexts as $context) {
			$rootNode->appendChild($this->createContextNode($doc, $context));
		}
		$doc->appendChild($rootNode);
		$rootNode->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:xsi', 'http://www.w3.org/2001/XMLSchema-instance');
		$rootNode->setAttribute('xsi:schemaLocation', $deployment->getNamespace() . ' ' . $deployment->getSchemaFilename());

		return $doc;
	}

	//
	// PKPAuthor conversion functions
	//
	/**
	 * Create and return an context node.
	 * @param $doc DOMDocument
	 * @param $context Context
	 * @return DOMElement
	 */
	function createContextNode($doc, $context) {
		$deployment = $this->getDeployment();

		// Create the context node
		$contextNode = $doc->createElementNS($deployment->getNamespace(), 'context');

		$contextNode->setAttribute('old_id', $context->getId());

		$this->addReviewForms($doc, $contextNode, $context);
		if ($deployment->_state == "article") {

		} else if ($deployment->_state == "issue") {

		}

		return $contextNode;
	}

	/**
	 * Add the ReviewForms for a content to its DOM element.
	 * @param $doc DOMDocument
	 * @param $contextNode DOMElement
	 * @param $context Context
	 */
	function addReviewForms($doc, $contextNode, $context) {
		$filterDao = DAORegistry::getDAO('FilterDAO');
		$nativeExportFilters = $filterDao->getObjectsByGroup('review-form=>native-xml');
		assert(count($nativeExportFilters)==1); // Assert only a single serialization filter
		$exportFilter = array_shift($nativeExportFilters);
		$exportFilter->setDeployment($this->getDeployment());

		/**
		 * @var $reviewFormDao ReviewFormDAO
		 */
		$reviewFormDao = DAORegistry::getDAO('ReviewFormDAO');
		$reviewForms = $reviewFormDao->getByAssocId(ASSOC_TYPE_JOURNAL, $context->getId());

		$reviewFormsDoc = $exportFilter->execute($reviewForms);
		if ($reviewFormsDoc->documentElement instanceof DOMElement) {
			$clone = $doc->importNode($reviewFormsDoc->documentElement, true);
			$contextNode->appendChild($clone);
		}

		return $reviewFormsDoc;
	}

	/**
	 * Add the ReviewForms for a content to its DOM element.
	 * @param $doc DOMDocument
	 * @param $contextNode DOMElement
	 * @param $context Context
	 */
	function addSubmissions($doc, $contextNode, $context) {
		$filterDao = DAORegistry::getDAO('FilterDAO');
		$nativeExportFilters = $filterDao->getObjectsByGroup('review-form=>native-xml');
		assert(count($nativeExportFilters)==1); // Assert only a single serialization filter
		$exportFilter = array_shift($nativeExportFilters);
		$exportFilter->setDeployment($this->getDeployment());

		/**
		 * @var $reviewFormDao ReviewFormDAO
		 */
		$reviewFormDao = DAORegistry::getDAO('ReviewFormDAO');
		$reviewForms = $reviewFormDao->getByAssocId(ASSOC_TYPE_JOURNAL, $context->getId());

		$reviewFormsDoc = $exportFilter->execute($reviewForms);
		if ($reviewFormsDoc->documentElement instanceof DOMElement) {
			$clone = $doc->importNode($reviewFormsDoc->documentElement, true);
			$contextNode->appendChild($clone);
		}

		return $reviewFormsDoc;
	}

	/**
	 * Add the ReviewForms for a content to its DOM element.
	 * @param $doc DOMDocument
	 * @param $contextNode DOMElement
	 * @param $context Context
	 */
	function addIssues($doc, $contextNode, $context) {
		$filterDao = DAORegistry::getDAO('FilterDAO');
		$nativeExportFilters = $filterDao->getObjectsByGroup('review-form=>native-xml');
		assert(count($nativeExportFilters)==1); // Assert only a single serialization filter
		$exportFilter = array_shift($nativeExportFilters);
		$exportFilter->setDeployment($this->getDeployment());

		/**
		 * @var $reviewFormDao ReviewFormDAO
		 */
		$reviewFormDao = DAORegistry::getDAO('ReviewFormDAO');
		$reviewForms = $reviewFormDao->getByAssocId(ASSOC_TYPE_JOURNAL, $context->getId());

		$reviewFormsDoc = $exportFilter->execute($reviewForms);
		if ($reviewFormsDoc->documentElement instanceof DOMElement) {
			$clone = $doc->importNode($reviewFormsDoc->documentElement, true);
			$contextNode->appendChild($clone);
		}

		return $reviewFormsDoc;
	}
}

?>

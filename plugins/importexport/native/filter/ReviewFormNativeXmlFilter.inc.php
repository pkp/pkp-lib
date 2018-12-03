<?php

/**
 * @file plugins/importexport/native/filter/ReviewFormNativeXmlFilter.inc.php
 *
 * Copyright (c) 2014-2018 Simon Fraser University
 * Copyright (c) 2000-2018 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class ReviewFormNativeXmlFilter
 * @ingroup plugins_importexport_native
 *
 * @brief Base class that converts a set of Review Forms into a Native XML document
 */

import('lib.pkp.plugins.importexport.native.filter.NativeExportFilter');

class ReviewFormNativeXmlFilter extends NativeExportFilter {
	/**
	 * Constructor
	 * @param $filterGroup FilterGroup
	 */
	function __construct($filterGroup) {
		$this->setDisplayName('Native XML review form export');
		parent::__construct($filterGroup);
	}


	//
	// Implement template methods from PersistableFilter
	//
	/**
	 * @copydoc PersistableFilter::getClassName()
	 */
	function getClassName() {
		return 'lib.pkp.plugins.importexport.native.filter.ReviewFormNativeXmlFilter';
	}


	//
	// Implement template methods from Filter
	//
	/**
	 * @see Filter::process()
	 * @param $reviewForms array of ReviewForm
	 * @return DOMDocument
	 */
	function &process(&$reviewForms) {
		// Create the XML document
		$doc = new DOMDocument('1.0');
		$doc->preserveWhiteSpace = false;
		$doc->formatOutput = true;
		$deployment = $this->getDeployment();

		// Wrap in a <reviewAssignments> element
		$rootNode = $doc->createElementNS($deployment->getNamespace(), 'reviewForms');

		foreach ($reviewForms as $reviewForm) {
			$rootNode->appendChild($this->createReviewFormNode($doc, $reviewForm));
		}

		$doc->appendChild($rootNode);
		$rootNode->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:xsi', 'http://www.w3.org/2001/XMLSchema-instance');
		$rootNode->setAttribute('xsi:schemaLocation', $deployment->getNamespace() . ' ' . $deployment->getSchemaFilename());

		return $doc;
	}

	//
	// Conversion functions
	//
	/**
	 * Create and return an reviewForm node.
	 * @param $doc DOMDocument
	 * @param $reviewForm ReviewForm
	 * @return DOMElement
	 */
	function createReviewFormNode($doc, $reviewForm) {
		$deployment = $this->getDeployment();
		$context = $deployment->getContext();

		// Create the reviewAssignment node
		$reviewFormNode = $doc->createElementNS($deployment->getNamespace(), 'reviewForm');

		$this->createLocalizedNodes($doc, $reviewFormNode, 'description', $reviewForm->getDescription(null));
		$this->createLocalizedNodes($doc, $reviewFormNode, 'title', $reviewForm->getTitle(null));

		if ($reviewForm->getActive()) {
			$reviewFormNode->setAttribute('is_active', 'true');
		}

		if ($reviewFormAssocType = $reviewForm->getAssocType()) {
			$reviewFormNode->setAttribute('assoc_type', $reviewFormAssocType);
		}

		if ($reviewFormAssocId = $reviewForm->getAssocId()) {
			$reviewFormNode->setAttribute('assoc_id', $reviewFormAssocId);
		}

		if ($reviewFormSeq = $reviewForm->getSequence()) {
			$reviewFormNode->setAttribute('seq', $reviewFormSeq);
		}

		$reviewFormNode->setAttribute('old_id', $reviewForm->getId());

		$this->addReviewFormElements($doc, $reviewFormNode, $reviewForm);

		return $reviewFormNode;
	}

	/**
	 * Add the ReviewFormElements for a review form to its DOM element.
	 * @param $doc DOMDocument
	 * @param $reviewFormNode DOMElement
	 * @param $reviewForm ReviewForm
	 */
	function addReviewFormElements($doc, $reviewFormNode, $reviewForm) {
		$childDao = DAORegistry::getDAO('ReviewFormElementDAO');

		$childElements = $childDao->getByReviewFormId($reviewForm->getId())->toArray();

		$reviewFormElementsNode = $this->processReviewFormElements($childElements, $reviewForm);
		if ($reviewFormElementsNode->documentElement instanceof DOMElement) {
			$clone = $doc->importNode($reviewFormElementsNode->documentElement, true);
			$reviewFormNode->appendChild($clone);
		}
	}

	/**
	 * Create ReviewFormElements Node
	 * @param $reviewFormElements array of ReviewFormElemet
	 * @param $reviewForm ReviewForm
	 * @return DOMDocument
	 */
	function processReviewFormElements($reviewFormElements, $reviewForm) {
		$doc = new DOMDocument('1.0');
		$doc->preserveWhiteSpace = false;
		$doc->formatOutput = true;
		$deployment = $this->getDeployment();

		$rootNode = $doc->createElementNS($deployment->getNamespace(), 'reviewFormElements');
		foreach ($reviewFormElements as $reviewFormElement) {
			$rootNode->appendChild($this->createReviewFormElementNode($doc, $reviewFormElement, $reviewForm));
		}
		$doc->appendChild($rootNode);
		$rootNode->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:xsi', 'http://www.w3.org/2001/XMLSchema-instance');
		$rootNode->setAttribute('xsi:schemaLocation', $deployment->getNamespace() . ' ' . $deployment->getSchemaFilename());

		return $doc;
	}

	/**
	 * Create reviewForm elements node.
	 * @param $doc DOMDocument
	 * @param $reviewFormElement ReviewFormElement
	 * @return DOMElement
	 */
	function createReviewFormElementNode($doc, $reviewFormElement) {
		$deployment = $this->getDeployment();
		$context = $deployment->getContext();

		// Create the reviewAssignment node
		$reviewFormElementNode = $doc->createElementNS($deployment->getNamespace(), 'reviewFormElement');

		$this->createLocalizedNodes($doc, $reviewFormElementNode, 'question', $reviewFormElement->getQuestion(null));
		$this->createLocalizedNodes($doc, $reviewFormElementNode, 'possibleResponses', $reviewFormElement->getPossibleResponses(null));

		if ($reviewFormElementSeq = $reviewFormElement->getSequence()) {
			$reviewFormElementNode->setAttribute('seq', $reviewFormElementSeq);
		}

		if ($reviewFormElementType = $reviewFormElement->getElementType()) {
			$reviewFormElementNode->setAttribute('element_type', $reviewFormElementType);
		}

		if ($reviewFormElement->getRequired()) {
			$reviewFormElementNode->setAttribute('required', 'true');
		}

		if ($reviewFormElement->getIncluded()) {
			$reviewFormElementNode->setAttribute('included', 'true');
		}

		$reviewFormElementNode->setAttribute('old_id', $reviewFormElement->getId());

		return $reviewFormElementNode;
	}
}

?>

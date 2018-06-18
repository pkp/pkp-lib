<?php

/**
 * @file plugins/importexport/native/filter/NativeXmlReviewAssignmentFilter.inc.php
 *
 * Copyright (c) 2014-2018 Simon Fraser University
 * Copyright (c) 2000-2018 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class NativeXmlReviewAssignmentFilter
 * @ingroup plugins_importexport_native
 *
 * @brief Base class that converts a Native XML document to a set of review assignments
 */

import('lib.pkp.plugins.importexport.native.filter.NativeImportFilter');

class NativeXmlReviewFormFilter extends NativeImportFilter {
	/**
	 * Constructor
	 * @param $filterGroup FilterGroup
	 */
	function __construct($filterGroup) {
		$this->setDisplayName('Native XML review form import');
		parent::__construct($filterGroup);
	}

	//
	// Implement template methods from NativeImportFilter
	//
	/**
	 * Return the plural element name
	 * @return string
	 */
	function getPluralElementName() {
		return 'reviewForms';
	}

	/**
	 * Get the singular element name
	 * @return string
	 */
	function getSingularElementName() {
		return 'reviewForm';
	}

	//
	// Implement template methods from PersistableFilter
	//
	/**
	 * @copydoc PersistableFilter::getClassName()
	 */
	function getClassName() {
		return 'lib.pkp.plugins.importexport.native.filter.NativeXmlReviewFormFilter';
	}


	/**
	 * Handle a submission element
	 * @param $node DOMElement
	 * @return array Array of PKPAuthor objects
	 */
	function handleElement($node) {
		$deployment = $this->getDeployment();
		$context = $deployment->getContext();
		//$submission = $deployment->getSubmission();
		//assert(is_a($submission, 'Submission'));

		//$reviewRoundId = $deployment->getProcessedObjectsIds(ASSOC_TYPE_REVIEW_ROUND);

		// Create the data object
		$reviewFormDao = DAORegistry::getDAO('ReviewFormDAO');
		/**
		 * @var $reviewForm ReviewForm
		 */
		$reviewForm = $reviewFormDao->newDataObject();

		if ($node->getAttribute('is_active') == 'true'){
			$reviewForm->setActive(1);
		}

		$reviewForm->setAssocType((int)$node->getAttribute('assoc_type'));
		$reviewForm->setAssocId((int)$node->getAttribute('assoc_id'));
		$reviewForm->setSequence((int)$node->getAttribute('seq'));

		$reviewFormDao->insertObject($reviewForm);

		$this->addProcessedObjectId(ASSOC_TYPE_REVIEW_FORM, $reviewForm->getId());

		// Handle subelements
		for ($n = $node->firstChild; $n !== null; $n=$n->nextSibling) {
			if (is_a($n, 'DOMElement')) {
				switch($n->tagName) {
					case 'title':
						list($locale, $value) = $this->parseLocalizedContent($n);
						$reviewForm->setTitle($value, $locale);
						$reviewFormDao->updateObject($reviewForm);
					case 'description':
						list($locale, $value) = $this->parseLocalizedContent($n);
						$reviewForm->setDescription($value, $locale);
						$reviewFormDao->updateObject($reviewForm);
					case 'reviewFormElements':
						$this->parseReviewFormElements($n, $reviewForm);
						break;
				}
			}
		}

		return $reviewForm;
	}

	/**
	 * Parse a review file element
	 * @param $node DOMElement
	 * @param $reviewForm ReviewForm
	 */
	function parseReviewFormElements($node, $reviewForm) {
		for ($n = $node->firstChild; $n !== null; $n=$n->nextSibling) {
			if (is_a($n, 'DOMElement')) {
				assert($n->tagName == 'reviewFormElement');
				$this->parseReviewFormElement($n, $reviewForm);
			}
		}
	}

	/**
	 * Parse a review file
	 * @param $n DOMElement
	 * @param $reviewForm ReviewForm
	 */
	function parseReviewFormElement($node, $reviewForm) {
		$deployment = $this->getDeployment();
		$context = $deployment->getContext();

		// Create the data object
		$reviewFormElementDao = DAORegistry::getDAO('ReviewFormElementDAO');
		/**
		 * @var $reviewFormElement ReviewFormElement
		 */
		$reviewFormElement = $reviewFormElementDao->newDataObject();

		if ($node->getAttribute('required') == 'true'){
			$reviewFormElement->setRequired(true);
		}

		if ($node->getAttribute('included') == 'true'){
			$reviewFormElement->setIncluded(true);
		}

		$reviewFormElement->setElementType($node->getAttribute('element_type'));
		$reviewFormElement->setSequence((int)$node->getAttribute('seq'));

		$reviewFormId = $deployment->getProcessedObjectsIds(ASSOC_TYPE_REVIEW_FORM);
		$reviewFormElement->setReviewFormId($reviewFormId);

		$reviewFormElementDao->insertObject($reviewFormElement);

		// Handle subelements
		for ($n = $node->firstChild; $n !== null; $n=$n->nextSibling) {
			if (is_a($n, 'DOMElement')) {
				switch($n->tagName) {
					case 'question':
						list($locale, $value) = $this->parseLocalizedContent($n);
						$reviewFormElement->setQuestion($value, $locale);
						$reviewFormElementDao->updateObject($reviewFormElement);
					case 'possibleResponses':
						list($locale, $value) = $this->parseLocalizedContent($n);
						$reviewFormElement->setPossibleResponses($value, $locale);
						$reviewFormElementDao->updateObject($reviewFormElement);
				}
			}
		}

		return $reviewFormElement;
	}
}

?>

<?php

/**
 * @defgroup reviewForm
 */

/**
 * @file classes/reviewForm/ReviewForm.inc.php
 *
 * Copyright (c) 2013-2014 Simon Fraser University Library
 * Copyright (c) 2000-2014 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class ReviewForm
 * @ingroup reviewForm
 * @see ReviewerFormDAO
 *
 * @brief Basic class describing a review form.
 *
 */

class ReviewForm extends DataObject {
	/**
	 * Constructor.
	 */
	function ReviewForm() {
		parent::DataObject();
	}

	/**
	 * Get localized title.
	 * @return string
	 */
	function getLocalizedTitle() {
		return $this->getLocalizedData('title');
	}

	/**
	 * Get localized description.
	 * @return string
	 */
	function getLocalizedDescription() {
		return $this->getLocalizedData('description');
	}

	//
	// Get/set methods
	//

	/**
	 * Get the associated type.
	 * @return int
	 */
	function getAssocType() {
		return $this->getData('assocType');
	}

	/**
	 * Set the associated type.
	 * @param $assocType int
	 */
	function setAssocType($assocType) {
		return $this->setData('assocType', $assocType);
	}

	/**
	 * Get the Id of the associated type.
	 * @return int
	 */
	function getAssocId() {
		return $this->getData('assocId');
	}

	/**
	 * Set the Id of the associated type.
	 * @param $assocId int
	 */
	function setAssocId($assocId) {
		return $this->setData('assocId', $assocId);
	}

	/**
	 * Get sequence of review form.
	 * @return float
	 */
	function getSequence() {
		return $this->getData('sequence');
	}

	/**
	 * Set sequence of review form.
	 * @param $sequence float
	 */
	function setSequence($sequence) {
		return $this->setData('sequence', $sequence);
	}

	/**
	 * Get active flag
	 * @return int
	 */
	function getActive() {
		return $this->getData('active');
	}

	/**
	 * Set active flag
	 * @param $active int
	 */
	function setActive($active) {
		return $this->setData('active', $active);
	}

	/**
	 * Get title.
	 * @param $locale string
	 * @return string
	 */
	function getTitle($locale) {
		return $this->getData('title', $locale);
	}

	/**
	 * Set title.
	 * @param $title string
	 * @param $locale string
	 */
	function setTitle($title, $locale) {
		return $this->setData('title', $title, $locale);
	}

	/**
	 * Get description.
	 * @param $locale string
	 * @return string
	 */
	function getDescription($locale) {
		return $this->getData('description', $locale);
	}

	/**
	 * Set description.
	 * @param $description string
	 * @param $locale string
	 */
	function setDescription($description, $locale) {
		return $this->setData('description', $description, $locale);
	}

	/** DEPRECATED **/

	function getReviewFormTitle() {
		if (Config::getVar('debug', 'deprecation_warnings')) trigger_error('Deprecated function.');
		return $this->getLocalizedTitle();
	}

	function getReviewFormDescription() {
		if (Config::getVar('debug', 'deprecation_warnings')) trigger_error('Deprecated function.');
		return $this->getLocalizedDescription();
	}

	/**
	 * Get the ID of the review form.
	 * @return int
	 */
	function getReviewFormId() {
		if (Config::getVar('debug', 'deprecation_warnings')) trigger_error('Deprecated function.');
		return $this->getId();
	}

	/**
	 * Set the ID of the review form.
	 * @param $reviewFormId int
	 */
	function setReviewFormId($reviewFormId) {
		if (Config::getVar('debug', 'deprecation_warnings')) trigger_error('Deprecated function.');
		return $this->setId($reviewFormId);
	}
}

?>

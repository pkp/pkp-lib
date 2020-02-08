<?php

/**
 * @file classes/context/Category.inc.php
 *
 * Copyright (c) 2014-2020 Simon Fraser University
 * Copyright (c) 2003-2020 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class Category
 * @ingroup context
 * @see CategoryDAO
 *
 * @brief Describes basic Category properties.
 */

class Category extends DataObject {
	/**
	 * Get ID of context.
	 * @return int
	 */
	function getContextId() {
		return $this->getData('contextId');
	}

	/**
	 * Set ID of context.
	 * @param $contextId int
	 */
	function setContextId($contextId) {
		return $this->setData('contextId', $contextId);
	}

	/**
	 * Get ID of parent category.
	 * @return int
	 */
	function getParentId() {
		return $this->getData('parentId');
	}

	/**
	 * Set ID of parent category.
	 * @param $parentId int
	 */
	function setParentId($parentId) {
		return $this->setData('parentId', $parentId);
	}

	/**
	 * Get sequence of category.
	 * @return int
	 */
	function getSequence() {
		return $this->getData('sequence');
	}

	/**
	 * Set sequence of category.
	 * @param $sequence int
	 */
	function setSequence($sequence) {
		return $this->setData('sequence', $sequence);
	}

	/**
	 * Get category path.
	 * @return string
	 */
	function getPath() {
		return $this->getData('path');
	}

	/**
	 * Set category path.
	 * @param $path string
	 */
	function setPath($path) {
		return $this->setData('path', $path);
	}

	/**
	 * Get localized title of the category.
	 * @return string
	 */
	function getLocalizedTitle() {
		return $this->getLocalizedData('title');
	}

	/**
	 * Get title of category.
	 * @param $locale string
	 * @return string
	 */
	function getTitle($locale) {
		return $this->getData('title', $locale);
	}

	/**
	 * Set title of category.
	 * @param $title string
	 * @param $locale string
	 */
	function setTitle($title, $locale) {
		return $this->setData('title', $title, $locale);
	}

	/**
	 * Get localized description of the category.
	 * @return string
	 */
	function getLocalizedDescription() {
		return $this->getLocalizedData('description');
	}

	/**
	 * Get description of category.
	 * @param $locale string
	 * @return string
	 */
	function getDescription($locale) {
		return $this->getData('description', $locale);
	}

	/**
	 * Set description of category.
	 * @param $description string
	 * @param $locale string
	 */
	function setDescription($description, $locale) {
		return $this->setData('description', $description, $locale);
	}

	/**
	 * Get the image.
	 * @return array
	 */
	function getImage() {
		return $this->getData('image');
	}

	/**
	 * Set the image.
	 * @param $image array
	 */
	function setImage($image) {
		return $this->setData('image', $image);
	}

	/**
	 * Get the option how the books in this category should be sorted,
	 * in the form: concat(sortBy, sortDir).
	 * @return string
	 */
	function getSortOption() {
		return $this->getData('sortOption');
	}

	/**
	 * Set the option how the books in this categpry should be sorted,
	 * in the form: concat(sortBy, sortDir).
	 * @param $sortOption string
	 */
	function setSortOption($sortOption) {
		return $this->setData('sortOption', $sortOption);
	}

}



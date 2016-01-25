<?php

/**
 * @file classes/context/FooterCategory.inc.php
 *
 * Copyright (c) 2014-2016 Simon Fraser University Library
 * Copyright (c) 2003-2016 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class FooterCategory
 * @ingroup context
 * @see FooterCategoryDAO
 *
 * @brief Describes basic FooterCategory properties.
 */

class FooterCategory extends DataObject {
	/**
	 * Constructor.
	 */
	function FooterCategory() {
		parent::DataObject();
	}

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
		$this->setData('contextId', $contextId);
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
		$this->setData('path', $path);
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
		$this->setData('title', $title, $locale);
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
		$this->setData('description', $description, $locale);
	}

	/**
	 * Retrieve the links in this category.
	 * @return array
	 */
	function getLinks() {
		$footerLinkDao = DAORegistry::getDAO('FooterLinkDAO');
		$footerLinks = $footerLinkDao->getByCategoryId($this->getId(), $this->getContextId());
		return $footerLinks->toArray();
	}
}

?>

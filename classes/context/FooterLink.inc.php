<?php

/**
 * @file classes/context/FooterLink.inc.php
 *
 * Copyright (c) 2014-2016 Simon Fraser University Library
 * Copyright (c) 2003-2016 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class FooterLink
 * @ingroup context
 * @see FooterLinkDAO
 *
 * @brief Describes basic FooterLink properties.
 */

class FooterLink extends DataObject {
	/**
	 * Constructor.
	 */
	function FooterLink() {
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
	 * Get ID of link's category.
	 * @return int
	 */
	function getCategoryId() {
		return $this->getData('footerCategoryId');
	}

	/**
	 * Set ID of link's category.
	 * @param $parentId int
	 */
	function setCategoryId($footerCategoryId) {
		$this->setData('footerCategoryId', $footerCategoryId);
	}

	/**
	 * Get localized url of the link.
	 * @return string
	 */
	function getLocalizedUrl() {
		return $this->getLocalizedData('url');
	}

	/**
	 * Get link URL.
	 * @return string
	 */
	function getUrl($locale) {
		return $this->getData('url', $locale);
	}

	/**
	 * Set link URL.
	 * @param $path string
	 * @param $locale string
	 */
	function setUrl($url, $locale) {
		$this->setData('url', $url, $locale);
	}

	/**
	 * Get localized title of the link.
	 * @return string
	 */
	function getLocalizedTitle() {
		return $this->getLocalizedData('title');
	}

	/**
	 * Get title of link.
	 * @param $locale string
	 * @return string
	 */
	function getTitle($locale) {
		return $this->getData('title', $locale);
	}

	/**
	 * Set title of link.
	 * @param $title string
	 * @param $locale string
	 */
	function setTitle($title, $locale) {
		$this->setData('title', $title, $locale);
	}
}

?>

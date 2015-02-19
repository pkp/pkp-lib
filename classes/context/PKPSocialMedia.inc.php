<?php

/**
 * @file classes/context/PKPSocialMedia.inc.php
 *
 * Copyright (c) 2014-2015 Simon Fraser University Library
 * Copyright (c) 2003-2015 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class PKPSocialMedia
 * @ingroup context
 * @see PKPSocialMediaDAO
 *
 * @brief Describes basic PKPSocialMedia properties.
 */

class PKPSocialMedia extends DataObject {
	/**
	 * Constructor.
	 */
	function PKPSocialMedia() {
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
		return $this->setData('contextId', $contextId);
	}

	/**
	 * Get media block code.
	 * @return string
	 */
	function getCode() {
		return $this->getData('code');
	}

	/**
	 * Set media block code.
	 * @param $path string
	 */
	function setCode($code) {
		return $this->setData('code', $code);
	}

	/**
	 * Get whether or not this should be included on a submission's catalog page.
	 * @return boolean
	 */
	function getIncludeInCatalog() {
		return $this->getData('includeInCatalog');
	}

	/**
	 * Set whether or not this should be included on a submission's catalog page.
	 * @param $path string
	 */
	function setIncludeInCatalog($includeInCatalog) {
		return $this->setData('includeInCatalog', $includeInCatalog);
	}

	/**
	 * Get localized platform name.
	 * @return string
	 */
	function getLocalizedPlatform() {
		return $this->getLocalizedData('platform');
	}

	/**
	 * Get media platform.
	 * @param $locale string
	 * @return string
	 */
	function getPlatform($locale) {
		return $this->getData('platform', $locale);
	}

	/**
	 * Set media platform.
	 * @param $title string
	 * @param $locale string
	 */
	function setPlatform($platform, $locale) {
		return $this->setData('platform', $platform, $locale);
	}

	/**
	 * Replace various variables in the code template with data
	 * relevant to the assigned submission.
	 */
	function replaceCodeVars() {
		// Subclasses should override as needed.
	}
}

?>

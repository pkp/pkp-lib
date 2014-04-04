<?php

/**
 * @file classes/context/PKPSection.inc.php
 *
 * Copyright (c) 2014 Simon Fraser University Library
 * Copyright (c) 2003-2014 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class PKPSection
 * @ingroup context
 *
 * @brief Describes basic section properties.
 */

class PKPSection extends DataObject {

	/**
	 * Constructor.
	 */
	function PKPSection() {
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
	 * Get path to section (in URL).
	 * @return string
	 */
	function getPath() {
		return $this->getData('path');
	}

	/**
	 * Set path to section (in URL).
	 * @param $path string
	 */
	function setPath($path) {
		return $this->setData('path', $path);
	}

	/**
	 * Get localized title of section.
	 * @return string
	 */
	function getLocalizedTitle() {
		return $this->getLocalizedData('title');
	}

	/**
	 * Get title of section.
	 * @param $locale string
	 * @return string
	 */
	function getTitle($locale) {
		return $this->getData('title', $locale);
	}

	/**
	 * Set title of section.
	 * @param $title string
	 * @param $locale string
	 */
	function setTitle($title, $locale) {
		return $this->setData('title', $title, $locale);
	}

	/**
	 * Return boolean indicating whether or not submissions are restricted to [sub]Editors.
	 * @return boolean
	 */
	function getEditorRestricted() {
		return $this->getData('editorRestricted');
	}

	/**
	 * Set whether or not submissions are restricted to [sub]Editors.
	 * @param $editorRestricted boolean
	 */
	function setEditorRestricted($editorRestricted) {
		return $this->setData('editorRestricted', $editorRestricted);
	}

	/**
	 * Get ID of primary review form.
	 * @return int
	 */
	function getReviewFormId() {
		return $this->getData('reviewFormId');
	}

	/**
	 * Set ID of primary review form.
	 * @param $reviewFormId int
	 */
	function setReviewFormId($reviewFormId) {
		return $this->setData('reviewFormId', $reviewFormId);
	}

	/**
	 * Get section main page views.
	 * @return int
	 */
	function getViews() {
		$application = Application::getApplication();
		return $application->getPrimaryMetricByAssoc(ASSOC_TYPE_SECTION, $this->getId());
	}
}

?>

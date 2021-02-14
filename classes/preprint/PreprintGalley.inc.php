<?php

/**
 * @file classes/preprint/PreprintGalley.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PreprintGalley
 * @ingroup preprint
 * @see PreprintGalleyDAO
 *
 * @brief A galley is a final presentation version of the full-text of an preprint.
 */

import('lib.pkp.classes.submission.Representation');

class PreprintGalley extends Representation {
	/** @var SubmissionFile */
	var $_submissionFile;


	//
	// Get/set methods
	//
	/**
	 * Get views count.
	 * @return int
	 */
	function getViews() {
		$application = Application::get();
		$submissionFileId = $this->getFileId();
		if ($submissionFileId) {
			return $application->getPrimaryMetricByAssoc(ASSOC_TYPE_SUBMISSION_FILE, $submissionFileId);
		} else {
			return 0;
		}
	}

	/**
	 * Get label/title.
	 * @return string
	 */
	function getLabel() {
		return $this->getData('label');
	}

	/**
	 * Set label/title.
	 * @param $label string
	 */
	function setLabel($label) {
		return $this->setData('label', $label);
	}

	/**
	 * Get locale.
	 * @return string
	 */
	function getLocale() {
		return $this->getData('locale');
	}

	/**
	 * Set locale.
	 * @param $locale string
	 */
	function setLocale($locale) {
		return $this->setData('locale', $locale);
	}

	/**
	 * Return the "best" preprint ID -- If a public preprint ID is set,
	 * use it; otherwise use the internal preprint Id.
	 * @return string
	 */
	function getBestGalleyId() {
		return $this->getData('urlPath')
			? $this->getData('urlPath')
			: $this->getId();
	}

	/**
	 * Set file ID.
	 * @deprecated 3.3
	 * @param $submissionFileId int
	 */
	function setFileId($submissionFileId) {
		$this->setData('submissionFileId', $submissionFileId);
	}

	/**
	 * Get file id
	 * @deprecated 3.3
	 * @return int
	 */
	function getFileId() {
		return $this->getData('submissionFileId');
	}

	/**
	 * Get the submission file corresponding to this galley.
	 * @deprecated 3.3
	 * @return SubmissionFile
	 */
	function getFile() {
		if (!isset($this->_submissionFile)) {
			$this->_submissionFile = Services::get('submissionFile')->get($this->getFileId());
		}
		return $this->_submissionFile;
	}

	/**
	 * Get the file type corresponding to this galley.
	 * @deprecated 3.3
	 * @return string MIME type
	 */
	function getFileType() {
		$galleyFile = $this->getFile();
		return $galleyFile ? $galleyFile->getData('mimetype') : null;
	}

	/**
	 * Determine whether the galley is a PDF.
	 * @return boolean
	 */
	function isPdfGalley() {
		return $this->getFileType() == 'application/pdf';
	}

	/**
	 * Get the localized galley label.
	 * @return string
	 */
	function getGalleyLabel() {
		$label = $this->getLabel();
		if ($this->getLocale() != AppLocale::getLocale()) {
			$locales = AppLocale::getAllLocales();
			$label .= ' (' . $locales[$this->getLocale()] . ')';
		}
		return $label;
	}

	/**
	 * @see Representation::getName()
	 *
	 * This override exists to provide a functional getName() in order to make
	 * native XML export work correctly.  It is only used in that single instance.
	 *
	 * @param $locale string unused, except to match the function prototype in Representation.
	 * @return array
	 */
	function getName($locale) {
		return array($this->getLocale() => $this->getLabel());
	}

	/**
	 * Override the parent class to fetch the non-localized label.
	 * @see Representation::getLocalizedName()
	 * @return string
	 */
	function getLocalizedName() {
		return $this->getLabel();
	}
}



<?php

/**
 * @file classes/submission/SupplementaryFile.inc.php
 *
 * Copyright (c) 2014-2015 Simon Fraser University Library
 * Copyright (c) 2003-2015 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class SupplementaryFile
 * @ingroup submission
 * @see SubmissionFileDAO
 *
 * @brief Supplementary file class.
 */

import('lib.pkp.classes.submission.SubmissionFile');

class SupplementaryFile extends SubmissionFile {
	/** @var array image file information */
	var $_imageInfo;

	/**
	 * Constructor
	 */
	function SupplementaryFile() {
		parent::SubmissionFile();
	}


	//
	// Getters and Setters
	//
	/**
	 * Get "localized" creator (if applicable).
	 * @param $preferredLocale string
	 * @return string
	 */
	function getLocalizedCreator($preferredLocale = null) {
		return $this->getLocalizedData('creator', $preferredLocale);
	}

	/**
	 * Get creator.
	 * @param $locale
	 * @return string
	 */
	function getCreator($locale) {
		return $this->getData('creator', $locale);
	}

	/**
	 * Set creator.
	 * @param $creator string
	 * @param $locale
	 */
	function setCreator($creator, $locale) {
		return $this->setData('creator', $creator, $locale);
	}

	/**
	 * Copy the user-facing (editable) metadata from another submission
	 * file.
	 * @param $submissionFile SubmissionFile
	 */
	function copyEditableMetadataFrom($submissionFile) {
		if (is_a($submissionFile, 'SupplementaryFile')) {
		}

		parent::copyEditableMetadataFrom($submissionFile);
	}

	/**
	 * @copydoc SubmissionFile::getMetadataForm
	 */
	function getMetadataForm($stageId, $reviewRound) {
		import('lib.pkp.controllers.wizard.fileUpload.form.SupplementaryFileMetadataForm');
		return new SupplementaryFileMetadataForm($this, $stageId, $reviewRound);
	}
}

?>

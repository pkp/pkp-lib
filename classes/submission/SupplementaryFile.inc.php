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
	 * Get localized subject
	 * @return string
	 */
	function getLocalizedSubject() {
		return $this->getLocalizedData('subject');
	}

	/**
	 * Get subject.
	 * @param $locale string
	 * @return string
	 */
	function getSubject($locale) {
		return $this->getData('subject', $locale);
	}

	/**
	 * Set subject.
	 * @param $subject string
	 * @param $locale string
	 */
	function setSubject($subject, $locale) {
		return $this->setData('subject', $subject, $locale);
	}

	/**
	 * Get localized description
	 * @return string
	 */
	function getLocalizedDescription() {
		return $this->getLocalizedData('description');
	}

	/**
	 * Get file description.
	 * @param $locale string
	 * @return string
	 */
	function getDescription($locale) {
		return $this->getData('description', $locale);
	}

	/**
	 * Set file description.
	 * @param $description string
	 * @param $locale string
	 */
	function setDescription($description, $locale) {
		return $this->setData('description', $description, $locale);
	}

	/**
	 * Get localized publisher
	 * @return string
	 */
	function getLocalizedPublisher() {
		return $this->getLocalizedData('publisher');
	}

	/**
	 * Get publisher.
	 * @param $locale string
	 * @return string
	 */
	function getPublisher($locale) {
		return $this->getData('publisher', $locale);
	}

	/**
	 * Set publisher.
	 * @param $publisher string
	 * @param $locale string
	 */
	function setPublisher($publisher, $locale) {
		return $this->setData('publisher', $publisher, $locale);
	}

	/**
	 * Get localized sponsor
	 * @return string
	 */
	function getLocalizedSponsor() {
		return $this->getLocalizedData('sponsor');
	}

	/**
	 * Get sponsor.
	 * @param $locale string
	 * @return string
	 */
	function getSponsor($locale) {
		return $this->getData('sponsor', $locale);
	}

	/**
	 * Set sponsor.
	 * @param $sponsor string
	 * @param $locale string
	 */
	function setSponsor($sponsor, $locale) {
		return $this->setData('sponsor', $sponsor, $locale);
	}

	/**
	 * Get date created.
	 * @return date
	 */
	function getDateCreated() {
		return $this->getData('dateCreated');
	}

	/**
	 * Set date created.
	 * @param $dateCreated date
	 */
	function setDateCreated($dateCreated) {
		return $this->setData('dateCreated', $dateCreated);
	}

	/**
	 * Get localized source
	 * @return string
	 */
	function getLocalizedSource() {
		return $this->getLocalizedData('source');
	}

	/**
	 * Get source.
	 * @param $locale string
	 * @return string
	 */
	function getSource($locale) {
		return $this->getData('source', $locale);
	}

	/**
	 * Set source.
	 * @param $source string
	 * @param $locale string
	 */
	function setSource($source, $locale) {
		return $this->setData('source', $source, $locale);
	}

	/**
	 * Get language.
	 * @return string
	 */
	function getLanguage() {
		return $this->getData('language');
	}

	/**
	 * Set language.
	 * @param $language string
	 */
	function setLanguage($language) {
		return $this->setData('language', $language);
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

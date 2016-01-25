<?php

/**
 * @file classes/submission/SubmissionArtworkFile.inc.php
 *
 * Copyright (c) 2014-2016 Simon Fraser University Library
 * Copyright (c) 2003-2016 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class SubmissionArtworkFile
 * @ingroup submission
 * @see SubmissionFileDAO
 *
 * @brief Artwork file class.
 */

import('lib.pkp.classes.submission.SubmissionFile');

class SubmissionArtworkFile extends SubmissionFile {
	/** @var array image file information */
	var $_imageInfo;

	/**
	 * Constructor
	 */
	function SubmissionArtworkFile() {
		parent::SubmissionFile();
	}


	//
	// Getters and Setters
	//
	/**
	 * Get artwork caption.
	 * @return string
	 */
	function getCaption() {
		return $this->getData('caption');
	}

	/**
	 * Set artwork caption.
	 * @param $caption string
	 */
	function setCaption($caption) {
		$this->setData('caption', $caption);
	}

	/**
	 * Get the credit.
	 * @return string
	 */
	function getCredit() {
		return $this->getData('credit');
	}

	/**
	 * Set the credit.
	 * @param $credit string
	 */
	function setCredit($credit) {
		$this->setData('credit', $credit);
	}

	/**
	 * Get the copyright owner.
	 * @return string
	 */
	function getCopyrightOwner() {
		return $this->getData('copyrightOwner');
	}

	/**
	 * Set the copyright owner.
	 * @param $owner string
	 */
	function setCopyrightOwner($owner) {
		$this->setData('copyrightOwner', $owner);
	}

	/**
	 * Get contact details for the copyright owner.
	 * @return string
	 */
	function getCopyrightOwnerContactDetails() {
		return $this->getData('copyrightOwnerContact');
	}

	/**
	 * Set the contact details for the copyright owner.
	 * @param $contactDetails string
	 */
	function setCopyrightOwnerContactDetails($contactDetails) {
		$this->setData('copyrightOwnerContact', $contactDetails);
	}

	/**
	 * Get the permission terms.
	 * @return string
	 */
	function getPermissionTerms() {
		return $this->getData('terms');
	}

	/**
	 * Set the permission terms.
	 * @param $terms string
	 */
	function setPermissionTerms($terms) {
		$this->setData('terms', $terms);
	}

	/**
	 * Get the permission form file id.
	 * @return int
	 */
	function getPermissionFileId() {
		return $this->getData('permissionFileId');
	}

	/**
	 * Set the permission form file id.
	 * @param $fileId int
	 */
	function setPermissionFileId($fileId) {
		$this->setData('permissionFileId', $fileId);
	}

	/**
	 * Get the contact author's id.
	 * @return int
	 */
	function getContactAuthor() {
		return $this->getData('contactAuthor');
	}

	/**
	 * Set the contact author's id.
	 * @param $authorId int
	 */
	function setContactAuthor($authorId) {
		$this->setData('contactAuthor', $authorId);
	}

	/**
	 * Get the submission chapter id.
	 * @return int
	 */
	function getChapterId() {
		return $this->getData('chapterId');
	}

	/**
	 * Set the submission chapter id.
	 * @param $chapterId int
	 */
	function setChapterId($chapterId) {
		$this->setData('chapterId', $chapterId);
	}

	/**
	 * Get the width of the image in pixels.
	 * @return integer
	 */
	function getWidth() {
		if (!$this->_imageInfo) {
			$this->_imageInfo = getimagesize($this->getFilePath());
		}
		return $this->_imageInfo[0];
	}

	/**
	 * Get the height of the image in pixels.
	 * @return integer
	 */
	function getHeight() {
		if (!$this->_imageInfo) {
			$this->_imageInfo = getimagesize($this->getFilePath());
		}
		return $this->_imageInfo[1];
	}

	/**
	 * Copy the user-facing (editable) metadata from another submission
	 * file.
	 * @param $submissionFile SubmissionFile
	 */
	function copyEditableMetadataFrom($submissionFile) {
		if (is_a($submissionFile, 'SubmissionArtworkFile')) {
			$this->setCaption($submissionFile->getCaption());
			$this->setCredit($submissionFile->getCredit());
			$this->setCopyrightOwner($submissionFile->getCopyrightOwner());
			$this->setCopyrightOwnerContactDetails($submissionFile->getCopyrightOwnerContactDetails());
			$this->setPermissionTerms($submissionFile->getPermissionTerms());
		}

		parent::copyEditableMetadataFrom($submissionFile);
	}

	/**
	 * @copydoc SubmissionFile::getMetadataForm
	 */
	function getMetadataForm($stageId, $reviewRound) {
		import('lib.pkp.controllers.wizard.fileUpload.form.SubmissionFilesArtworkMetadataForm');
		return new SubmissionFilesArtworkMetadataForm($this, $stageId, $reviewRound);
	}
}

?>

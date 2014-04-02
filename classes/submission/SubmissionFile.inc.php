<?php

/**
 * @file classes/submission/SubmissionFile.inc.php
 *
 * Copyright (c) 2013-2014 Simon Fraser University Library
 * Copyright (c) 2003-2014 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class SubmissionFile
 * @ingroup submission
 *
 * @brief Submission file class.
 */

import('lib.pkp.classes.file.PKPFile');

class SubmissionFile extends PKPFile {
	/**
	 * Constructor.
	 */
	function SubmissionFile() {
		parent::PKPFile();
	}


	//
	// Getters and Setters
	//
	/**
	 * Get ID of file.
	 * @return int
	 */
	function getFileId() {
		// WARNING: Do not modernize getter/setters without considering
		// ID clash with subclasses ArticleGalley and ArticleNote!
		return $this->getData('fileId');
	}

	/**
	 * Set ID of file.
	 * @param $fileId int
	 */
	function setFileId($fileId) {
		// WARNING: Do not modernize getter/setters without considering
		// ID clash with subclasses ArticleGalley and ArticleNote!
		return $this->setData('fileId', $fileId);
	}

	/**
	 * Get source file ID of this file.
	 * @return int
	 */
	function getSourceFileId() {
		return $this->getData('sourceFileId');
	}

	/**
	 * Set source file ID of this file.
	 * @param $sourceFileId int
	 */
	function setSourceFileId($sourceFileId) {
		return $this->setData('sourceFileId', $sourceFileId);
	}

	/**
	 * Get source revision of this file.
	 * @return int
	 */
	function getSourceRevision() {
		return $this->getData('sourceRevision');
	}

	/**
	 * Set source revision of this file.
	 * @param $sourceRevision int
	 */
	function setSourceRevision($sourceRevision) {
		return $this->setData('sourceRevision', $sourceRevision);
	}

	/**
	 * Get associated ID of file. (Used, e.g., for email log attachments.)
	 * @return int
	 */
	function getAssocId() {
		return $this->getData('assocId');
	}

	/**
	 * Set associated ID of file. (Used, e.g., for email log attachments.)
	 * @param $assocId int
	 */
	function setAssocId($assocId) {
		return $this->setData('assocId', $assocId);
	}

	/**
	 * Get revision number.
	 * @return int
	 */
	function getRevision() {
		return $this->getData('revision');
	}

	/**
	 * Get the combined key of the file
	 * consisting of the file id and the revision.
	 * @return string
	 */
	function getFileIdAndRevision() {
		$id = $this->getFileId();
		$revision = $this->getRevision();
		$idAndRevision = $id;
		if ($revision) {
			$idAndRevision .= '-'.$revision;
		}
		return $idAndRevision;
	}

	/**
	 * Set revision number.
	 * @param $revision int
	 */
	function setRevision($revision) {
		return $this->setData('revision', $revision);
	}

	/**
	 * Get ID of submission.
	 * @return int
	 */
	function getSubmissionId() {
		return $this->getData('submissionId');
	}

	/**
	 * Set ID of submission.
	 * @param $submissionId int
	 */
	function setSubmissionId($submissionId) {
		return $this->setData('submissionId', $submissionId);
	}

	/**
	 * Get type of the file.
	 * @return int
	 */
	function getType() {
		if (Config::getVar('debug', 'deprecation_warnings')) trigger_error('Deprecated function.');
		return $this->getFileStage();
	}

	/**
	 * Set type of the file.
	 * @param $type int
	 */
	function setType($type) {
		if (Config::getVar('debug', 'deprecation_warnings')) trigger_error('Deprecated function.');
		return $this->setFileStage($type);
	}

	/**
	 * Get file stage of the file.
	 * @return int
	 */
	function getFileStage() {
		return $this->getData('fileStage');
	}

	/**
	 * Set file stage of the file.
	 * @param $fileStage int
	 */
	function setFileStage($fileStage) {
		return $this->setData('fileStage', $fileStage);
	}

	/**
	 * Get modified date of file.
	 * @return date
	 */

	function getDateModified() {
		return $this->getData('dateModified');
	}

	/**
	 * Set modified date of file.
	 * @param $dateModified date
	 */

	function setDateModified($dateModified) {
		return $this->SetData('dateModified', $dateModified);
	}

	/**
	 * Get round.
	 * @return int
	 */

	function getRound() {
		return $this->getData('round');
	}

	/**
	 * Set round.
	 * @param $round int
	 */
	function setRound($round) {
		return $this->SetData('round', $round);
	}

	/**
	 * Get viewable.
	 * @return boolean
	 */
	function getViewable() {
		return $this->getData('viewable');
	}


	/**
	 * Set viewable.
	 * @param $viewable boolean
	 */
	function setViewable($viewable) {
		return $this->SetData('viewable', $viewable);
	}


	//
	// Public methods
	//
	/**
	 * Check if the file may be displayed inline.
	 * FIXME: Move to DAO to remove coupling of the domain
	 *  object to its DAO.
	 * @return boolean
	 */
	function isInlineable() {
		$submissionFileDao =& DAORegistry::getDAO('SubmissionFileDAO'); /* @var $submissionFileDao SubmissionFileDAO */
		return $submissionFileDao->isInlineable($this);
	}

}

?>

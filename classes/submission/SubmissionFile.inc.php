<?php

/**
 * @file classes/submission/SubmissionFile.inc.php
 *
 * Copyright (c) 2003-2013 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class SubmissionFile
 * @ingroup submission
 *
 * @brief Submission file class.
 */

import('lib.pkp.classes.file.PKPFile');

// Define the file stage identifiers.
define('SUBMISSION_FILE_PUBLIC', 1);
define('SUBMISSION_FILE_SUBMISSION', 2);
define('SUBMISSION_FILE_NOTE', 3);
define('SUBMISSION_FILE_REVIEW_FILE', 4);
define('SUBMISSION_FILE_REVIEW_ATTACHMENT', 5);
//	SUBMISSION_FILE_REVIEW_REVISION defined below (FIXME: re-order before release)
define('SUBMISSION_FILE_FINAL', 6);
define('SUBMISSION_FILE_FAIR_COPY', 7);
define('SUBMISSION_FILE_EDITOR', 8);
define('SUBMISSION_FILE_COPYEDIT', 9);
define('SUBMISSION_FILE_PROOF', 10);
define('SUBMISSION_FILE_PRODUCTION_READY', 11);
define('SUBMISSION_FILE_ATTACHMENT', 13);
define('SUBMISSION_FILE_SIGNOFF', 14);
define('SUBMISSION_FILE_REVIEW_REVISION', 15);
define('SUBMISSION_FILE_DEPENDENT', 17);

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
	 * Get price of submission file.
	 * @return numeric
	 */
	function getDirectSalesPrice() {
		return $this->getData('directSalesPrice');
	}

	/**
	 * Set direct sales price.
	 * @param $directSalesPrice numeric
	 */
	function setDirectSalesPrice($directSalesPrice) {
		return $this->setData('directSalesPrice', $directSalesPrice);
	}

	/**
	 * Get sales type of submission file.
	 * @return string
	 */
	function getSalesType() {
		return $this->getData('salesType');
	}

	/**
	 * Set sales type.
	 * @param $salesType string
	 */
	function setSalesType($salesType) {
		return $this->setData('salesType', $salesType);
	}

	/**
	 * Set the name of the file
	 * @param $name string
	 * @param $locale string
	 */
	function setName($name, $locale) {
		$this->setData('name', $name, $locale);
	}

	/**
	 * Get the name of the file
	 * @param $locale string
	 * @return string
	 */
	function getName($locale) {
		return $this->getData('name', $locale);
	}

	/**
	 * Get the localized name of the file
	 * @return string
	 */
	function getLocalizedName() {
		return $this->getLocalizedData('name');
	}

	/**
	 * Get the file's extension.
	 * @return string
	 */
	function getExtension() {
		import('lib.pkp.classes.file.FileManager');
		$fileManager = new FileManager();
		return strtoupper($fileManager->parseFileExtension($this->getOriginalFileName()));
	}

	/**
	 * Get the file's document type (enumerated types)
	 * @return string
	 */
	function getDocumentType() {
		import('lib.pkp.classes.file.FileManager');
		$fileManager = new FileManager();
		return $fileManager->getDocumentType($this->getFileType());
	}

	/**
	 * Set the genre id of this file (i.e. referring to Manuscript, Index, etc)
	 * Foreign key into genres table
	 * @param $genreId int
	 */
	function setGenreId($genreId) {
		$this->setData('genreId', $genreId);
	}

	/**
	 * Get the genre id of this file (i.e. referring to Manuscript, Index, etc)
	 * Foreign key into genres table
	 * @return int
	 */
	function getGenreId() {
		return $this->getData('genreId');
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

	/**
	 * Set the uploader's user id.
	 * @param $uploaderUserId integer
	 */
	function setUploaderUserId($uploaderUserId) {
		$this->setData('uploaderUserId', $uploaderUserId);
	}

	/**
	 * Get the uploader's user id.
	 * @return integer
	 */
	function getUploaderUserId() {
		return $this->getData('uploaderUserId');
	}

	/**
	 * Set the uploader's user group id
	 * @param $userGroupId int
	 */
	function setUserGroupId($userGroupId) {
		$this->setData('userGroupId', $userGroupId);
	}

	/**
	 * Get the uploader's user group id
	 * @return int
	 */
	function getUserGroupId() {
		return $this->getData('userGroupId');
	}

	/**
	 * Get type that is associated with this file.
	 * @return int
	 */
	function getAssocType() {
		return $this->getData('assocType');
	}

	/**
	 * Set type that is associated with this file.
	 * @param $assocType int
	 */
	function setAssocType($assocType) {
		return $this->setData('assocType', $assocType);
	}

	/**
	 * Return a context-aware file path.
	 */
	function getFilePath() {
		// Get the context ID
		$submissionDao = Application::getSubmissionDAO();
		$submission = $submissionDao->getById($this->getSubmissionId());
		if (!$submission) return null;
		$contextId = $submission->getContextId();
		unset($submission);

		// Construct the file path
		import('lib.pkp.classes.file.SubmissionFileManager');
		$submissionFileManager = new SubmissionFileManager($contextId, $this->getSubmissionId());
		return $submissionFileManager->getBasePath() . $this->_fileStageToPath($this->getFileStage()) . '/' . $this->getFileName();
	}

	/**
	 * Build a file name label.
	 * @return string
	 */
	function getFileLabel($locale = null) {
		// Retrieve the localized file name as basis for the label.
		if ($locale) {
			$fileLabel = $this->getName($locale);
		} else {
			$fileLabel = $this->getLocalizedName();
		}

		// If we have no file name then use a default name.
		if (empty($fileLabel)) $fileLabel = $this->getOriginalFileName();

		// Add the revision number to the label if we have more than one revision.
		if ($this->getRevision() > 1) $fileLabel .= ' (' . $this->getRevision() . ')';

		return $fileLabel;
	}


	/**
	 * Copy the user-facing (editable) metadata from another submission
	 * file.
	 * @param $submissionFile SubmissionFile
	 */
	function copyEditableMetadataFrom($submissionFile) {
		assert(is_a($submissionFile, 'SubmissionFile'));
		$this->setName($submissionFile->getName(null), null);
	}

	//
	// Overridden public methods from PKPFile
	//
	/**
	 * @see PKPFile::getFileName()
	 * Generate the file name from identification data rather than
	 * retrieving it from the database.
	 */
	function getFileName() {
		return $this->_generateFileName();
	}

	/**
	 * @see PKPFile::setFileName()
	 * Do not allow setting the file name of a submission file
	 * directly because it is generated from identification data.
	 */
	function setFileName($fileName) {
		// FIXME: Remove this setter from PKPFile, too? See #6446.
		assert(false);
	}

	//
	// Private helper methods
	//

	/**
	 * Generate the unique filename for this submission file.
	 * @return string
	 */
	function _generateFileName() {
		// Remember the ID information we generated the file name
		// on so that we only have to re-generate the name if the
		// relevant information changed.
		static $lastIds = array();
		static $fileName = null;

		// Retrieve the current id information.
		$currentIds = array(
				'genreId' => $this->getGenreId(),
				'dateUploaded' => $this->getDateUploaded(),
				'submissionId' => $this->getSubmissionId(),
				'fileId' => $this->getFileId(),
				'revision' => $this->getRevision(),
				'fileStage' => $this->getFileStage(),
				'extension' => strtolower_codesafe($this->getExtension())
		);

		// Check whether we need a refresh.
		$refreshRequired = false;
		foreach($currentIds as $key => $currentId) {
			if (!isset($lastIds[$key]) || $lastIds[$key] !== $currentId) {
				$refreshRequired = true;
				$lastIds = $currentIds;
				break;
			}
		}

		// Refresh the file name if required.
		if ($refreshRequired) {
			// If the file has a file genre set then include
			// human readable genre information.
			$genreName = '';
			if ($currentIds['genreId']) {
				$primaryLocale = AppLocale::getPrimaryLocale();
				$genreDao = DAORegistry::getDAO('GenreDAO'); /* @var $genreDao GenreDAO */
				$genre =& $genreDao->getById($currentIds['genreId']);
				assert(is_a($genre, 'Genre'));
				$genreName = $genre->getDesignation().'_'.$genre->getName($primaryLocale).'-';
			}

			// Generate a human readable time stamp.
			$timestamp = date('Ymd', strtotime($currentIds['dateUploaded']));

			// Make the file name unique across all files and file revisions.
			// Also make sure that files can be ordered sensibly by file name.
			$fileName = $currentIds['submissionId'].'-'.$genreName.$currentIds['fileId'].'-'.$currentIds['revision'].'-'.$currentIds['fileStage'].'-'.$timestamp.'.'.$currentIds['extension'];
		}

		return $fileName;
	}

	/**
	 * Return path associated with a file stage code.
	 * @param $fileStage string
	 * @return string
	 */
	function _fileStageToPath($fileStage) {
		static $fileStageToPath = array(
				SUBMISSION_FILE_PUBLIC => 'public',
				SUBMISSION_FILE_SUBMISSION => 'submission',
				SUBMISSION_FILE_NOTE => 'note',
				SUBMISSION_FILE_REVIEW_FILE => 'submission/review',
				SUBMISSION_FILE_REVIEW_ATTACHMENT => 'submission/review/attachment',
				SUBMISSION_FILE_REVIEW_REVISION => 'submission/review/revision',
				SUBMISSION_FILE_FINAL => 'submission/final',
				SUBMISSION_FILE_FAIR_COPY => 'submission/fairCopy',
				SUBMISSION_FILE_EDITOR => 'submission/editor',
				SUBMISSION_FILE_COPYEDIT => 'submission/copyedit',
				SUBMISSION_FILE_DEPENDENT => 'submission/proof',
				SUBMISSION_FILE_PROOF => 'submission/proof',
				SUBMISSION_FILE_PRODUCTION_READY => 'submission/productionReady',
				SUBMISSION_FILE_ATTACHMENT => 'attachment',
				SUBMISSION_FILE_SIGNOFF => 'submission/signoff',
		);

		assert(isset($fileStageToPath[$fileStage]));
		return $fileStageToPath[$fileStage];
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
		$submissionFileDao = DAORegistry::getDAO('SubmissionFileDAO'); /* @var $submissionFileDao SubmissionFileDAO */
		return $submissionFileDao->isInlineable($this);
	}

}

?>

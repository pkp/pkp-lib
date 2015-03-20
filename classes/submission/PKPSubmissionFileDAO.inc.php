<?php

/**
 * @file classes/submission/PKPSubmissionFileDAO.inc.php
 *
 * Copyright (c) 2014-2015 Simon Fraser University Library
 * Copyright (c) 2003-2015 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class PKPSubmissionFileDAO
 * @ingroup submission
 * @see SubmissionFile
 * @see SubmissionFileDAODelegate
 *
 * @brief Abstract base class for retrieving and modifying SubmissionFile
 * objects and their decendents (e.g. SubmissionFile, SubmissionArtworkFile).
 *
 * This class provides access to all SubmissionFile implementations. It
 * instantiates and uses delegates internally to provide the right database
 * access behaviour depending on the type of the accessed file.
 *
 * The state classes are named after the data object plus the "DAODelegate"
 * extension, e.g. ArtworkFileDAODelegate. An internal factory method will
 * provide the correct implementation to the DAO.
 *
 * This design allows clients to access all types of files without having
 * to know about the specific file implementation unless the client really
 * wishes to access file implementation specific data. This also enables
 * us to let delegates inherit from each others to avoid code duplication
 * between DAO implementations.
 */

import('lib.pkp.classes.file.PKPFileDAO');
import('lib.pkp.classes.submission.Genre'); // GENRE_CATEGORY_... constants

abstract class PKPSubmissionFileDAO extends PKPFileDAO {
	/**
	 * @var array a private list of delegates that provide operations for
	 *  different SubmissionFile implementations.
	 */
	var $_delegates = array();

	/**
	 * Constructor
	 */
	function PKPSubmissionFileDAO() {
		parent::DAO();
	}


	//
	// Public methods
	//
	/**
	 * Retrieve a specific revision of a file.
	 * @param $fileId int
	 * @param $revision int
	 * @param $fileStage int (optional) further restricts
	 *  the selection to a given file stage.
	 * @param $submissionId int (optional) for validation
	 *  purposes only
	 */
	function getRevision($fileId, $revision, $fileStage = null, $submissionId = null) {
		if (!($fileId && $revision)) return null;
		$revisions = $this->_getInternally($submissionId, $fileStage, $fileId, $revision);
		return $this->_checkAndReturnRevision($revisions);
	}


	/**
	 * Retrieve the latest revision of a file.
	 * @param $fileId int
	 * @param $fileStage int (optional) further restricts
	 *  the selection to a given file stage.
	 * @param $submissionId int (optional) for validation
	 *  purposes only
	 * @return SubmissionFile
	 */
	function getLatestRevision($fileId, $fileStage = null, $submissionId = null) {
		if (!$fileId) return null;

		$revisions = $this->_getInternally($submissionId, $fileStage, $fileId, null, null, null, null, null, null, null, true);
		return $this->_checkAndReturnRevision($revisions);
	}

	/**
	 * Retrieve a list of current revisions.
	 * @param $submissionId int
	 * @param $fileStage int (optional) further restricts
	 *  the selection to a given file stage.
	 * @param $rangeInfo DBResultRange (optional)
	 * @return array a list of SubmissionFile instances
	 */
	function getLatestRevisions($submissionId, $fileStage = null, $rangeInfo = null) {
		if (!$submissionId) return null;
		return $this->_getInternally($submissionId, $fileStage, null, null, null, null, null, null, null, null, true, $rangeInfo);
	}

	/**
	 * Retrieve all revisions of a submission file.
	 * @param $fileId int
	 * @param $fileStage int (optional) further restricts
	 *  the selection to a given file stage.
	 * @param $submissionId int (optional) for validation
	 *  purposes only
	 * @param $rangeInfo DBResultRange (optional)
	 * @return array a list of SubmissionFile instances
	 */
	function getAllRevisions($fileId, $fileStage = null, $submissionId = null, $rangeInfo = null) {
		if (!$fileId) return null;
		return $this->_getInternally($submissionId, $fileStage, $fileId, null, null, null, null, null, null, null, false, $rangeInfo);
	}

	/**
	 * Retrieve all submission files & revisions for a submission.
	 * @param $submissionId int
	 * @param $rangeInfo DBResultRange (optional)
	 * @return array a list of SubmissionFile instances
	 */
	function getBySubmissionId($submissionId, $rangeInfo = null) {
		if (!$submissionId) return null;
		return $this->_getInternally($submissionId, null, null, null, null, null, null, null, null, null, false, $rangeInfo);
	}

	/**
	 * Retrieve the latest revision of all files associated
	 * to a certain object.
	 * @param $assocType int
	 * @param $assocId int
	 * @param $fileStage int (optional) further restricts
	 *  the selection to a given file stage.
	 * @param $rangeInfo DBResultRange (optional)
	 * @return array a list of SubmissionFile instances
	 */
	function getLatestRevisionsByAssocId($assocType, $assocId, $submissionId = null, $fileStage = null, $rangeInfo = null) {
		if (!($assocType && $assocId)) return null;
		return $this->_getInternally($submissionId, $fileStage, null, null, $assocType, $assocId, null, null, null, null, true, $rangeInfo);
	}

	/**
	 * Retrieve all files associated to a certain object.
	 * @param $assocType int
	 * @param $assocId int
	 * @param $fileStage int (optional) further restricts
	 *  the selection to a given file stage.
	 * @param $rangeInfo DBResultRange (optional)
	 * @return array a list of SubmissionFile instances
	 */
	function getAllRevisionsByAssocId($assocType, $assocId, $fileStage = null, $rangeInfo = null) {
		if (!($assocType && $assocId)) return null;
		return $this->_getInternally(null, $fileStage, null, null, $assocType, $assocId, null, null, null, null, false, $rangeInfo);
	}

	/**
	 * Get all file revisions assigned to the given review round.
	 * @param $reviewRound ReviewRound
	 * @param $fileStage int SUBMISSION_FILE_...
	 * @param $uploaderUserId int Uploader's user ID
	 * @param $uploaderUserGroupId int Uploader's user group ID
	 * @return array A list of SubmissionFiles.
	 */
	function getRevisionsByReviewRound($reviewRound, $fileStage = null,
			$uploaderUserId = null, $uploaderUserGroupId = null) {
		if (!is_a($reviewRound, 'ReviewRound')) return null;
		return $this->_getInternally($reviewRound->getSubmissionId(),
			$fileStage, null, null, null, null, null,
			$uploaderUserId, $uploaderUserGroupId, $reviewRound->getId()
		);
	}

	/**
	 * Get the latest revisions of all files that are in the specified
	 * review round.
	 * @param $reviewRound ReviewRound
	 * @param $fileStage int SUBMISSION_FILE_... (Optional)
	 * @return array A list of SubmissionFiles.
	 */
	function getLatestRevisionsByReviewRound($reviewRound, $fileStage = null) {
		if (!$reviewRound) return array();
		return $this->_getInternally($reviewRound->getSubmissionId(),
			$fileStage, null, null, null, null, $reviewRound->getStageId(),
			null, null, $reviewRound->getId(), true
		);
	}

	/**
	 * Retrieve the current revision number for a file.
	 * @param $fileId int
	 * @return int
	 */
	function getLatestRevisionNumber($fileId) {
		assert(!is_null($fileId));

		// Retrieve the latest revision from the database.
		$result = $this->retrieve(
			'SELECT MAX(revision) AS max_revision FROM submission_files WHERE file_id = ?',
			(int) $fileId
		);
		if($result->RecordCount() != 1) return null;

		$row = $result->FetchRow();
		$result->Close();

		$latestRevision = (int)$row['max_revision'];
		assert($latestRevision > 0);
		return $latestRevision;
	}

	/**
	 * Insert a new SubmissionFile.
	 * @param $submissionFile SubmissionFile
	 * @param $sourceFile string The place where the physical file
	 *  resides right now or the file name in the case of an upload.
	 *  The file will be copied to its canonical target location.
	 * @param $isUpload boolean set to true if the file has just been
	 *  uploaded.
	 * @return SubmissionFile
	 */
	function insertObject($submissionFile, $sourceFile, $isUpload = false) {
		// Make sure that the implementation of the updated file
		// is compatible with its genre (upcast but no downcast).
		$submissionFile = $this->_castToGenre($submissionFile);

		// Find the required target implementation and delegate.
		$targetImplementation = strtolower_codesafe(
			$this->_getFileImplementationForGenreId(
			$submissionFile->getGenreId())
		);
		$targetDaoDelegate = $this->_getDaoDelegate($targetImplementation);
		$insertedFile = $targetDaoDelegate->insertObject($submissionFile, $sourceFile, $isUpload);

		// If the updated file does not have the correct target type then we'll have
		// to retrieve it again from the database to cast it to the right type (downcast).
		if (strtolower_codesafe(get_class($insertedFile)) != $targetImplementation) {
			$insertedFile = $this->_castToDatabase($insertedFile);
		}
		return $insertedFile;
	}

	/**
	 * Update an existing submission file.
	 *
	 * NB: We implement a delete + insert strategy to deal with
	 * various casting problems (e.g. file implementation/genre
	 * may change, file path may change, etc.).
	 *
	 * @param $updatedFile SubmissionFile
	 * @param $previousFileId integer The file id before the file
	 *  was changed. Must only be given if the file id changed
	 *  so that the previous file can be identified.
	 * @param $previousRevision integer The revision before the file
	 *  was changed. Must only be given if the revision changed
	 *  so that the previous file can be identified.
	 * @return SubmissionFile The updated file. This file may be of
	 *  a different file implementation than the file passed into the
	 *  method if the genre of the file didn't fit its implementation.
	 */
	function updateObject($updatedFile, $previousFileId = null, $previousRevision = null) {
		// Make sure that the implementation of the updated file
		// is compatible with its genre.
		$updatedFile = $this->_castToGenre($updatedFile);

		// Complete the identifying data of the previous file if not given.
		$previousFileId = (int)($previousFileId ? $previousFileId : $updatedFile->getFileId());
		$previousRevision = (int)($previousRevision ? $previousRevision : $updatedFile->getRevision());

		// Retrieve the previous file.
		$previousFile = $this->getRevision($previousFileId, $previousRevision);
		assert(is_a($previousFile, 'SubmissionFile'));

		// Canonicalized the implementation of the previous file.
		$previousImplementation = strtolower_codesafe(get_class($previousFile));

		// Find the required target implementation and delegate.
		$targetImplementation = strtolower_codesafe(
			$this->_getFileImplementationForGenreId(
			$updatedFile->getGenreId())
		);
		$targetDaoDelegate = $this->_getDaoDelegate($targetImplementation);

		// If the implementation in the database differs from the target
		// implementation then we'll have to delete + insert the object
		// to make sure that the database contains consistent data.
		if ($previousImplementation != $targetImplementation) {
			// We'll have to copy the previous file to its target
			// destination so that it is not lost when we delete the
			// previous file.
			// When the implementation (i.e. genre) changes then the
			// file locations will also change so we should not get
			// a file name clash.
			$previousFilePath = $previousFile->getFilePath();
			$targetFilePath = $updatedFile->getFilePath();

			assert($previousFilePath != $targetFilePath && !file_exists($targetFilePath));
			import('lib.pkp.classes.file.FileManager');
			$fileManager = new FileManager();
			$fileManager->copyFile($previousFilePath, $targetFilePath);

			// We use the delegates directly to make sure
			// that we address the right implementation in the database
			// on delete and insert.
			$sourceDaoDelegate = $this->_getDaoDelegate($previousImplementation);
			$sourceDaoDelegate->deleteObject($previousFile);
			$targetDaoDelegate->insertObject($updatedFile, $targetFilePath);
		} else {
			// If the implementation in the database does not change then we
			// can do an efficient update.
			if (!$targetDaoDelegate->updateObject($updatedFile, $previousFile)) {
				return null;
			}
		}

		// If the updated file does not have the correct target type then we'll have
		// to retrieve it again from the database to cast it to the right type.
		if (strtolower_codesafe(get_class($updatedFile)) != $targetImplementation) {
			$updatedFile = $this->_castToDatabase($updatedFile);
		}

		return $updatedFile;
	}

	/**
	 * Set the latest revision of a file as the latest revision
	 * of another file.
	 * @param $revisedFileId integer the revised file
	 * @param $newFileId integer the file that will become the
	 *  latest revision of the revised file.
	 * @param $submissionId integer the submission id the two files
	 *  must belong to.
	 * @param $fileStage integer the file stage the two files
	 *  must belong to.
	 * @return SubmissionFile the new revision or null if something went wrong.
	 */
	function setAsLatestRevision($revisedFileId, $newFileId, $submissionId, $fileStage) {
		$revisedFileId = (int)$revisedFileId;
		$newFileId = (int)$newFileId;
		$submissionId = (int)$submissionId;
		$fileStage = (int)$fileStage;

		// Check whether the two files are already revisions of each other.
		if ($revisedFileId == $newFileId) return null;

		// Retrieve the latest revisions of the two submission files.
		$revisedFile = $this->getLatestRevision($revisedFileId, $fileStage, $submissionId);
		$newFile = $this->getLatestRevision($newFileId, $fileStage, $submissionId);
		if (!($revisedFile && $newFile)) return null;

		// Save identifying data of the changed file required for update.
		$previousFileId = $newFile->getFileId();
		$previousRevision = $newFile->getRevision();

		// Copy data over from the revised file to the new file.
		$newFile->setFileId($revisedFileId);
		$newFile->setRevision($revisedFile->getRevision()+1);
		$newFile->setGenreId($revisedFile->getGenreId());
		$newFile->setAssocType($revisedFile->getAssocType());
		$newFile->setAssocId($revisedFile->getAssocId());

		// Update the file in the database.
		return $this->updateObject($newFile, $previousFileId, $previousRevision);
	}

	/**
	 * Assign file to a review round.
	 * @param $fileId int The file to be assigned.
	 * @param $revision int The revision of the file to be assigned.
	 * @param $reviewRound ReviewRound
	 */
	function assignRevisionToReviewRound($fileId, $revision, $reviewRound) {
		if (!is_numeric($fileId) || !is_numeric($revision)) fatalError('Invalid file!');
		return $this->update('INSERT INTO review_round_files
				(submission_id, review_round_id, stage_id, file_id, revision)
			VALUES (?, ?, ?, ?, ?)',
			array(
				(int)$reviewRound->getSubmissionId(),
				(int)$reviewRound->getId(),
				(int)$reviewRound->getStageId(),
				(int)$fileId,
				(int)$revision
			)
		);
	}

	/**
	 * Delete a specific revision of a submission file.
	 * @param $submissionFile SubmissionFile
	 * @return integer the number of deleted file revisions
	 */
	function deleteRevision($submissionFile) {
		return $this->deleteRevisionById($submissionFile->getFileId(), $submissionFile->getRevision(), $submissionFile->getFileStage(), $submissionFile->getSubmissionId());
	}

	/**
	 * Delete a specific revision of a submission file by id.
	 * @param $fileId int
	 * @param $revision int
	 * @param $fileStage int (optional) further restricts
	 *  the selection to a given file stage.
	 * @param $submissionId int (optional) for validation
	 *  purposes only
	 * @return integer the number of deleted file revisions
	 */
	function deleteRevisionById($fileId, $revision, $fileStage = null, $submissionId = null) {
		return $this->_deleteInternally($submissionId, $fileStage, $fileId, $revision);
	}

	/**
	 * Delete the latest revision of a submission file by id.
	 * @param $fileId int
	 * @param $fileStage int (optional) further restricts
	 *  the selection to a given file stage.
	 * @param $submissionId int (optional) for validation
	 *  purposes only
	 * @return integer the number of deleted file revisions
	 */
	function deleteLatestRevisionById($fileId, $fileStage= null, $submissionId = null) {
		return $this->_deleteInternally($submissionId, $fileStage, $fileId, null, null, null, null, null, null, true);
	}

	/**
	 * Delete all revisions of a file, optionally
	 * restricted to a given file stage.
	 * @param $fileId int
	 * @param $fileStage int (optional) further restricts
	 *  the selection to a given file stage.
	 * @param $submissionId int (optional) for validation
	 *  purposes only
	 * @return integer the number of deleted file revisions
	 */
	function deleteAllRevisionsById($fileId, $fileStage = null, $submissionId = null) {
		return $this->_deleteInternally($submissionId, $fileStage, $fileId);
	}

	/**
	 * Delete all revisions of all files of a submission,
	 * optionally restricted to a given file stage.
	 * @param $submissionId int
	 * @param $fileStage int (optional) further restricts
	 *  the selection to a given file stage.
	 * @return integer the number of deleted file revisions
	 */
	function deleteAllRevisionsBySubmissionId($submissionId, $fileStage = null) {
		return $this->_deleteInternally($submissionId, $fileStage);
	}

	/**
	 * Retrieve all files associated to a certain object.
	 * @param $assocType int
	 * @param $assocId int
	 * @param $fileStage int (optional) further restricts
	 *  the selection to a given file stage.
	 * @return integer the number of deleted file revisions
	 */
	function deleteAllRevisionsByAssocId($assocType, $assocId, $fileStage = null) {
		return $this->_deleteInternally(null, $fileStage, null, null, $assocType, $assocId);
	}

	/**
	 * Remove all file assignements for the given review round.
	 * @param $reviewRoundId int The review round ID
	 */
	function deleteAllRevisionsByReviewRound($reviewRoundId) {
		// Remove currently assigned review files.
		return $this->update('DELETE FROM review_round_files WHERE review_round_id = ?', (int)$reviewRoundId);
	}

	/**
	 * Remove a specific file assignment from a review round.
	 * @param $submissionId int The submission id of
	 *  the file
	 * @param $stageId int The review round type.
	 * @param $fileId int The file id
	 * @param $revision int The file revision
	 */
	function deleteReviewRoundAssignment($submissionId, $stageId, $fileId, $revision) {
		// Remove currently assigned review files.
		return $this->update('DELETE FROM review_round_files
				WHERE submission_id = ? AND stage_id = ? AND file_id = ? AND revision = ?',
				array((int)$submissionId, (int)$stageId, (int)$fileId, (int)$revision));
	}

	/**
	 * Transfer the ownership of the submission files of one user to another.
	 * @param $oldUserId int
	 * @param $newUserId int
	 */
	function transferOwnership($oldUserId, $newUserId) {
		$submissionFiles = $this->_getInternally(null, null, null, null, null, null, null, $oldUserId, null);
		foreach ($submissionFiles as $file) {
			$daoDelegate = $this->_getDaoDelegateForObject($file);
			$file->setUploaderUserId($newUserId);
			$daoDelegate->updateObject($file, $file); // nothing else changes
		}
	}

	/**
	 * Construct a new data object corresponding to this DAO.
	 * @param $genreId integer The genre is required to identify the right
	 *  file implementation.
	 * @return SubmissionFile
	 */
	function newDataObjectByGenreId($genreId) {
		// Identify the delegate.
		$daoDelegate = $this->_getDaoDelegateForGenreId($genreId);

		// Instantiate and return the object.
		return $daoDelegate->newDataObject();
	}


	//
	// Abstract template methods to be implemented by subclasses.
	//
	/**
	 * Return the available delegates mapped by lower
	 * case class names.
	 * @return array a list of fully qualified class names
	 *  indexed by the lower case class name of the file
	 *  implementation they serve.
	 *  NB: Be careful to order class names such that they
	 *  can be called in the given order to delete files
	 *  without offending foreign key constraints, i.e.
	 *  place the sub-classes before the super-classes.
	 */
	function getDelegateClassNames() {
		return array(
			'submissionfile' => 'lib.pkp.classes.submission.SubmissionFileDAODelegate',
			'submissionartworkfile' => 'lib.pkp.classes.submission.SubmissionArtworkFileDAODelegate',
		);
	}


	/**
	 * Return the mapping of genre categories to the lower
	 * case class name of file implementation.
	 * @return array a list of lower case class names of
	 *  file implementations.
	 */
	function getGenreCategoryMapping() {
		return array(
			GENRE_CATEGORY_DOCUMENT => 'submissionfile',
			GENRE_CATEGORY_ARTWORK => 'submissionartworkfile',
		);
	}

	/**
	 * Return the basic join over all file class tables.
	 * @return string
	 */
	function baseQueryForFileSelection() {
		// Build the basic query that joins the class tables.
		// The DISTINCT is required to de-dupe the review_round_files join in
		// PKPSubmissionFileDAO.
		return 'SELECT DISTINCT
				sf.file_id AS submission_file_id, sf.revision AS submission_revision,
				af.file_id AS artwork_file_id, af.revision AS artwork_revision,
				sf.*, af.*
			FROM	submission_files sf
				LEFT JOIN submission_artwork_files af ON sf.file_id = af.file_id AND sf.revision = af.revision ';
	}


	//
	// Protected helper methods
	//
	/**
	 * Internal function to return a SubmissionFile object from a row.
	 * @param $row array
	 * @param $fileImplementation string
	 * @return SubmissionFile
	 */
	function fromRow($row, $fileImplementation) {
		// Identify the delegate.
		$daoDelegate = $this->_getDaoDelegate($fileImplementation); /* @var $daoDelegate SubmissionFileDAODelegate */

		// Let the DAO delegate instantiate the file implementation.
		return $daoDelegate->fromRow($row);
	}


	/**
	 * Return all file stages.
	 * @return array
	 */
	function getAllFileStages() {
		// Bring in the file stages definition.
		import('lib.pkp.classes.submission.SubmissionFile');
		return array(
			SUBMISSION_FILE_SUBMISSION,
			SUBMISSION_FILE_NOTE,
			SUBMISSION_FILE_REVIEW_FILE,
			SUBMISSION_FILE_REVIEW_ATTACHMENT,
			SUBMISSION_FILE_FINAL,
			SUBMISSION_FILE_FAIR_COPY,
			SUBMISSION_FILE_EDITOR,
			SUBMISSION_FILE_COPYEDIT,
			SUBMISSION_FILE_PROOF,
			SUBMISSION_FILE_PRODUCTION_READY,
			SUBMISSION_FILE_ATTACHMENT,
			SUBMISSION_FILE_SIGNOFF,
			SUBMISSION_FILE_REVIEW_REVISION,
			SUBMISSION_FILE_DEPENDENT,
		);
	}

	//
	// Private helper methods
	//
	/**
	 * Map a genre to the corresponding file implementation.
	 * @param $genreId integer
	 * @return string The class name of the file implementation.
	 */
	function _getFileImplementationForGenreId($genreId) {
		static $genreCache = array();

		if (!isset($genreCache[$genreId])) {
			if (is_null($genreId)) {
				// If no genreId is given fall back to the document category
				$genreCategory = GENRE_CATEGORY_DOCUMENT;

			} else {
				// We have to instantiate the genre to find out about
				// its category.
				$genreDao = DAORegistry::getDAO('GenreDAO'); /* @var $genreDao GenreDAO */
				$genre = $genreDao->getById($genreId);
				$genreCategory = $genre->getCategory();
			}

			// Identify the file implementation.
			$genreMapping = $this->getGenreCategoryMapping();
			assert(isset($genreMapping[$genreCategory]));
			$genreCache[$genreId] = $genreMapping[$genreCategory];
		}

		return $genreCache[$genreId];
	}

	/**
	 * Instantiates an approprate SubmissionFileDAODelegate
	 * based on the given genre identifier.
	 * @param $genreId integer
	 * @return SubmissionFileDAODelegate
	 */
	function _getDaoDelegateForGenreId($genreId) {
		// Find the required file implementation.
		$fileImplementation = $this->_getFileImplementationForGenreId($genreId);

		// Return the DAO delegate.
		return $this->_getDaoDelegate($fileImplementation);
	}

	/**
	 * Instantiates an appropriate SubmissionFileDAODelegate
	 * based on the given SubmissionFile.
	 * @param $object SubmissionFile
	 * @return SubmissionFileDAODelegate
	 */
	function _getDaoDelegateForObject($object) {
		return $this->_getDaoDelegate(get_class($object));
	}

	/**
	 * Return the requested SubmissionFileDAODelegate.
	 * @param $fileImplementation string the class name of
	 *  a file implementation that the requested delegate
	 *  should serve.
	 * @return SubmissionFileDAODelegate
	 */
	function _getDaoDelegate($fileImplementation) {
		// Normalize the file implementation name.
		$fileImplementation = strtolower_codesafe($fileImplementation);

		// Did we already instantiate the requested delegate?
		if (!isset($this->_delegates[$fileImplementation])) {
			// Instantiate the requested delegate.
			$delegateClasses = $this->getDelegateClassNames();
			assert(isset($delegateClasses[$fileImplementation]));
			$delegateClass = $delegateClasses[$fileImplementation];
			$this->_delegates[$fileImplementation] = instantiate($delegateClass, 'SubmissionFileDAODelegate', null, null, $this);
		}

		// Return the delegate.
		return $this->_delegates[$fileImplementation];
	}

	/**
	 * Private method to retrieve submission file revisions
	 * according to the given filters.
	 * @param $submissionId int
	 * @param $fileStage int
	 * @param $fileId int
	 * @param $revision int
	 * @param $assocType int
	 * @param $assocId int
	 * @param $stageId int
	 * @param $uploaderUserId int
	 * @param $uploaderUserGroupId int
	 * @param $reviewRoundId int
	 * @param $latestOnly boolean
	 * @param $rangeInfo DBResultRange
	 * @return array a list of SubmissionFile instances
	 */
	function _getInternally($submissionId = null, $fileStage = null, $fileId = null, $revision = null,
			$assocType = null, $assocId = null, $stageId = null, $uploaderUserId = null, $uploaderUserGroupId = null,
			$reviewRoundId = null, $latestOnly = false, $rangeInfo = null) {
		// Retrieve the base query.
		$sql = $this->baseQueryForFileSelection();

		// Add the revision round file join if a revision round
		// filter was requested.
		if ($reviewRoundId) {
			$sql .= 'INNER JOIN review_round_files rrf
					ON sf.submission_id = rrf.submission_id
					AND sf.file_id = rrf.file_id
					AND sf.revision '.($latestOnly ? '>=' : '=').' rrf.revision ';
		}

		// Filter the query.
		list($filterClause, $params) = $this->_buildFileSelectionFilter(
				$submissionId, $fileStage, $fileId, $revision,
				$assocType, $assocId, $stageId, $uploaderUserId, $uploaderUserGroupId, $reviewRoundId);

		// Did the user request all or only the latest revision?
		if ($latestOnly) {
			// Filter the latest revision of each file.
			// NB: We have to do this in the SQL for paging to work
			// correctly. We use a partial cartesian join here to
			// maintain MySQL 3.23 backwards compatibility. This
			// should be ok as we usually only have few revisions per
			// file.
			$sql .= 'LEFT JOIN submission_files sf2 ON sf.file_id = sf2.file_id AND sf.revision < sf2.revision
				WHERE sf2.revision IS NULL AND '.$filterClause;
		} else {
			$sql .= 'WHERE '.$filterClause;
		}

		// Order the query.
		$sql .= ' ORDER BY sf.submission_id ASC, sf.file_stage ASC, sf.file_id ASC, sf.revision DESC';

		// Execute the query.
		if ($rangeInfo) {
			$result = $this->retrieveRange($sql, $params, $rangeInfo);
		} else {
			$result = $this->retrieve($sql, $params);
		}

		// Build the result array.
		$submissionFiles = array();
		while (!$result->EOF) {
			// Retrieve the next result row.
			$row = $result->GetRowAssoc(false);

			// Construct a combined id from file id and revision
			// that uniquely identifies the file.
			$idAndRevision = $row['submission_file_id'].'-'.$row['submission_revision'];

			// Check for duplicates.
			assert(!isset($submissionFiles[$idAndRevision]));

			// Instantiate the file and add it to the
			// result array with a unique key.
			// N.B. The subclass implementation of fromRow receives just the $row
			// but calls PKPSubmissionFileDAO::fromRow($row, $fileImplementation) as defined here.
			$submissionFiles[$idAndRevision] = $this->fromRow($row);

			// Move the query cursor to the next record.
			$result->MoveNext();
		}
		$result->Close();
		return $submissionFiles;
	}

	/**
	 * Private method to delete submission file revisions
	 * according to the given filters.
	 * @param $submissionId int
	 * @param $fileStage int
	 * @param $fileId int
	 * @param $revision int
	 * @param $assocType int
	 * @param $assocId int
	 * @param $stageId int
	 * @param $uploaderUserId int
	 * @param $uploaderUserGroupId int
	 * @param $latestOnly boolean
	 * @return boolean|integer Returns boolean false if an error occurs, otherwise the number
	 *  of deleted files.
	 */
	function _deleteInternally($submissionId = null, $fileStage = null, $fileId = null, $revision = null,
			$assocType = null, $assocId = null, $stageId = null, $uploaderUserId = null, $uploaderUserGroupId = null,
			$latestOnly = false) {

		// Identify all matched files.
		$deletedFiles = $this->_getInternally($submissionId, $fileStage, $fileId, $revision,
				$assocType, $assocId, $stageId, $uploaderUserId, $uploaderUserGroupId, null, $latestOnly);
		if (empty($deletedFiles)) return 0;

		foreach($deletedFiles as $deletedFile) { /* @var $deletedFile SubmissionFile */
			// Delete file in the database.
			// NB: We cannot safely bulk-delete because MySQL 3.23
			// does not support multi-column IN-clauses. Same is true
			// for multi-table access or subselects in the DELETE
			// statement. And having a long (... AND ...) OR (...)
			// clause could hit length limitations.
			$daoDelegate = $this->_getDaoDelegateForObject($deletedFile);
			if (!$daoDelegate->deleteObject($deletedFile)) return false;
		}

		// Return the number of deleted files.
		return count($deletedFiles);
	}

	/**
	 * Build an SQL where clause to select
	 * submissions based on the given filter information.
	 * @param $submissionId int
	 * @param $fileStage int
	 * @param $fileId int
	 * @param $revision int
	 * @param $assocType int
	 * @param $assocId int
	 * @param $stageId int
	 * @param $uploaderUserId int
	 * @param $uploaderUserGroupId int
	 * @param $reviewRoundId int
	 * @return array an array that contains the generated SQL
	 *  filter clause and the corresponding parameters.
	 */
	function _buildFileSelectionFilter($submissionId, $fileStage,
			$fileId, $revision, $assocType, $assocId, $stageId, $uploaderUserId, $uploaderUserGroupId, $reviewRoundId) {

		// Make sure that at least one entity filter has been set.
		assert((int)$submissionId || (int)$uploaderUserId || (int)$fileId || (int)$assocId);

		// Both, assoc type and id, must be set (or unset) together.
		assert(((int)$assocType && (int)$assocId) || !((int)$assocType || (int)$assocId));

		// Collect the filtered columns and ids in
		// an array for consistent handling.
		$filters = array(
			'sf.submission_id' => $submissionId,
			'sf.file_stage' => $fileStage,
			'sf.file_id' => $fileId,
			'sf.revision' => $revision,
			'sf.assoc_type' => $assocType,
			'sf.assoc_id' => $assocId,
			'sf.uploader_user_id' => $uploaderUserId,
			'sf.user_group_id' => $uploaderUserGroupId,
			'rrf.stage_id' => $stageId,
			'rrf.review_round_id' => $reviewRoundId
		);

		// Build and return a SQL where clause and a parameter
		// array.
		$filterClause = '';
		$params = array();
		$conjunction = '';
		foreach($filters as $filteredColumn => $filteredId) {
			if ($filteredId) {
				$filterClause .= $conjunction.' '.$filteredColumn.' = ?';
				$conjunction = ' AND';
				$params[] = (int)$filteredId;
			}
		}
		return array($filterClause, $params);
	}

	/**
	 * Make sure that the genre of the file and its file
	 * implementation are compatible.
	 *
	 * NB: In the case of a downcast this means that not all data in the
	 * object will be saved to the database. It is the UI's responsibility
	 * to inform users about potential loss of data if they change to
	 * a genre that permits less meta-data than the prior genre!
	 *
	 * @param $submissionFile SubmissionFile
	 * @return SubmissionFile The same file in a compatible implementation.
	 */
	function _castToGenre($submissionFile) {
		// Find the required target implementation.
		$targetImplementation = strtolower_codesafe(
			$this->_getFileImplementationForGenreId(
			$submissionFile->getGenreId())
		);

		// If the current implementation of the updated object
		// is the same as the target implementation, skip cast.
		if (is_a($submissionFile, $targetImplementation)) return $submissionFile;

		// The updated file has to be upcast by manually
		// instantiating the target object and copying data
		// to the target.
		$targetDaoDelegate = $this->_getDaoDelegate($targetImplementation);
		$targetFile = $targetDaoDelegate->newDataObject();
		$targetFile = $submissionFile->upcastTo($targetFile);
		return $targetFile;
	}

	/**
	 * Make sure that a file's implementation corresponds to the way it is
	 * saved in the database.
	 * @param $submissionFile SubmissionFile
	 * @return SubmissionFile
	 */
	function _castToDatabase($submissionFile) {
		return $this->getRevision(
			$submissionFile->getFileId(),
			$submissionFile->getRevision()
		);
	}

	/**
	 * Check whether the given array contains exactly
	 * zero or one revisions and return it.
	 * @param $revisions array
	 * @return SubmissionFile
	 */
	function _checkAndReturnRevision($revisions) {
		assert(count($revisions) <= 1);
		if (empty($revisions)) return null;

		$revision = array_pop($revisions);
		assert(is_a($revision, 'SubmissionFile'));
		return $revision;
	}
}

?>

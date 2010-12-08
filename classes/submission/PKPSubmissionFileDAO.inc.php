<?php

/**
 * @file classes/submission/PKPSubmissionFileDAO.inc.php
 *
 * Copyright (c) 2003-2010 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class PKPSubmissionFileDAO
 * @ingroup submission
 * @see SubmissionFile
 * @see SubmissionFileDAODelegate
 *
 * @brief Abstract base class for retrieving and modifying SubmissionFile
 * objects and their decendents (e.g. MonographFile, ArtworkFile).
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


define('INLINEABLE_TYPES_FILE', 'inlineTypes.txt');

class PKPSubmissionFileDAO extends DAO {
	/**
	 * @var array a private list of delegates that provide operations for
	 *  different SubmissionFile implementations.
	 */
	var $_delegates = array();

	/**
	 * @var array a private list of MIME types that can be shown inline
	 *  in the browser
	 */
	var $_inlineableTypes;

	/**
	 * @var array a list of localized field names, will be set by delegates
	 *  before calling DAO methods that require this information.
	 */
	var $_localeFieldNames;

	/**
	 * Constructor
	 */
	function PKPSubmissionFileDAO() {
		return parent::DAO();
	}


	//
	// Setters and Getters
	//
	/**
	 * Set the locale field names.
	 * @param $localeFieldNames array
	 */
	function setLocaleFieldNames($localeFieldNames) {
		$this->_localeFieldNames = $localeFieldNames;
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
	function &getRevision($fileId, $revision, $fileStage = null, $submissionId = null) {
		if (!($fileId && $revision)) {
			$nullVar = null;
			return $nullVar;
		}

		$revisions =& $this->_getInternally($submissionId, $fileStage, $fileId, $revision);
		assert(count($revisions) <= 1);
		if (empty($revisions)) {
			$nullVar = null;
			return $nullVar;
		} else {
			assert(isset($revisions[0]));
			return $revisions[0];
		}
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
	function &getLatestRevision($fileId, $fileStage = null, $submissionId = null) {
		if (!$fileId) {
			$nullVar = null;
			return $nullVar;
		}

		$revisions =& $this->_getInternally($submissionId, $fileStage, $fileId, null, null, null, true);
		assert(count($revisions) <= 1);
		if (empty($revisions)) {
			$nullVar = null;
			return $nullVar;
		} else {
			assert(isset($revisions[0]));
			return $revisions[0];
		}
	}

	/**
	 * Retrieve a list of current revisions.
	 * @param $submissionId int
	 * @param $fileStage int (optional) further restricts
	 *  the selection to a given file stage.
	 * @param $rangeInfo DBResultRange (optional)
	 * @return array a list of SubmissionFile instances
	 */
	function &getLatestRevisions($submissionId, $fileStage = null, $rangeInfo = null) {
		if (!$submissionId) {
			$nullVar = null;
			return $nullVar;
		}
		return $this->_getInternally($submissionId, $fileStage, null, null, null, null, true, $rangeInfo);
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
	function &getAllRevisions($fileId, $fileStage = null, $submissionId = null, $rangeInfo = null) {
		if (!$fileId) {
			$nullVar = null;
			return $nullVar;
		}
		return $this->_getInternally($submissionId, $fileStage, $fileId, null, null, null, false, $rangeInfo);
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
	function &getLatestRevisionsByAssocId($assocType, $assocId, $fileStage = null, $rangeInfo = null) {
		if (!($assocType && $assocId)) {
			$nullVar = null;
			return $nullVar;
		}
		return $this->_getInternally(null, $fileStage, null, null, $assocType, $assocId, true, $rangeInfo);
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
	function &getAllRevisionsByAssocId($assocType, $assocId, $fileStage = null, $rangeInfo = null) {
		if (!($assocType && $assocId)) {
			$nullVar = null;
			return $nullVar;
		}
		return $this->_getInternally(null, $fileStage, null, null, $assocType, $assocId, false, $rangeInfo);
	}

	/**
	 * Set a file as the latest revision of an existing file
	 * @param $revisedFileId integer the revised file
	 * @param $newFileId integer the file that will become the
	 *  latest revision of the revised file.
	 * @param $submissionId integer the submission id the two files
	 *  must belong to.
	 * @param $fileStage integer the file stage the two files
	 *  must belong to.
	 * @return SubmissionFile the new revision or null if something went wrong.
	 */
	function &setAsLatestRevision($revisedFileId, $newFileId, $submissionId, $fileStage) {
		$revisedFileId = (int)$revisedFileId;
		$newFileId = (int)$newFileId;
		$submissionId = (int)$submissionId;
		$fileStage = (int)$fileStage;

		// Check whether the two files are already revisions of each other.
		$nullVar = null;
		if ($revisedFileId == $newFileId) return $nullVar;

		// Retrieve the latest revisions of the two submission files.
		$revisedFile =& $this->getLatestRevision($revisedFileId, $fileStage, $submissionId);
		$newFile =& $this->getLatestRevision($newFileId, $fileStage, $submissionId);
		if (!($revisedFile && $newFile)) return $nullVar;

		// Check that the two files have the same implementation.
		if (get_class($revisedFile) != get_class($newFile)) return $nullVar;

		// Copy data over from the revised file to the new file.
		$newFile->setRevision($revisedFile->getRevision()+1);
		$newFile->setGenreId($revisedFile->getGenreId());
		$newFile->setAssocType($revisedFile->getAssocType());
		$newFile->setAssocId($revisedFile->getAssocId());

		// Update the revision information in the database.
		return $this->_updateNewRevision($revisedFile, $newFile);
	}

	/**
	 * Retrieve the current revision number for a file.
	 * @param $fileId int
	 * @return int
	 */
	function getLatestRevisionNumber($fileId) {
		assert(!is_null($fileId));

		// Retrieve the latest revision from the database.
		$result =& $this->retrieve(
			'SELECT MAX(revision) AS max_revision FROM '.$this->getSubmissionEntityName().'_files WHERE file_id = ?',
			$fileId
		);
		if($result->RecordCount() != 1) return null;

		$row =& $result->FetchRow();
		$result->Close();
		unset($result);

		$latestRevision = (int)$row['max_revision'];
		assert($latestRevision > 0);
		return $latestRevision;
	}

	/**
	 * Insert a new SubmissionFile.
	 * @param $submissionFile SubmissionFile
	 * @return SubmissionFile
	 */
	function &insertObject(&$submissionFile) {
		$daoDelegate =& $this->_getDaoDelegateForObject($submissionFile);
		return $daoDelegate->insertObject($submissionFile);
	}

	/**
	 * Update an existing submission file.
	 * @param $submissionFile SubmissionFile
	 * @return boolean
	 */
	function updateObject(&$submissionFile) {
		$daoDelegate =& $this->_getDaoDelegateForObject($submissionFile);
		return $daoDelegate->updateObject($submissionFile);
	}

	/**
	 * Delete a specific revision of a submission file.
	 * @param $submissionFile SubmissionFile
	 * @return integer the number of deleted file revisions
	 */
	function deleteRevision(&$submissionFile) {
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
		return $this->_deleteInternally($submissionId, $fileStage, $fileId, null, null, null, true);
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
	 * Check whether a file may be displayed inline.
	 * @param $submissionFile SubmissionFile
	 * @return boolean
	 */
	function isInlineable(&$submissionFile) {
		// Retrieve MIME types.
		if (!isset($this->_inlineableTypes)) {
			$this->_inlineableTypes = array_filter(file(Config::getVar('general', 'registry_dir') . DIRECTORY_SEPARATOR . INLINEABLE_TYPES_FILE), create_function('&$a', 'return ($a = trim($a)) && !empty($a) && $a[0] != \'#\';'));
		}

		// Check the MIME type of the file.
		return in_array($submissionFile->getFileType(), $this->_inlineableTypes);
	}


	//
	// Implement template methods from DAO
	//
	/**
	 * Get the list of fields for which data is localized.
	 * @return array
	 */
	function getLocaleFieldNames() {
		return $this->_localeFieldNames;
	}


	//
	// Abstract template methods to be implemented by subclasses.
	//
	/**
	 * Return the name of the base submission entity
	 * (i.e. 'monograph', 'paper', 'article', etc.)
	 * @return string
	 */
	function getSubmissionEntityName() {
		assert(false);
	}

	/**
	 * Return the available delegate.
	 * @return array a list of fully qualified class names
	 *  indexed by the lower case class name of the file
	 *  implementation they serve.
	 *  NB: Be careful to order class names such that they
	 *  can be called in the given order to delete files
	 *  without offending foreign key constraints, i.e.
	 *  place the sub-classes before the super-classes.
	 */
	function getDelegateClassNames() {
		assert(false);
	}

	/**
	 * Return the basic join over all file class tables.
	 * @return string
	 */
	function baseQueryForFileSelection() {
		assert(false);
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
	function &fromRow(&$row, $fileImplementation) {
		// Identify the delegate.
		$daoDelegate =& $this->_getDaoDelegate($fileImplementation); /* @var $daoDelegate SubmissionFileDAODelegate */

		// Let the DAO delegate instantiate the file implementation.
		return $daoDelegate->fromRow($row);
	}

	/**
	 * Get the ID of the last inserted submission file.
	 * @return int
	 */
	function getInsertSubmissionFileId() {
		$submissionEntityName = $this->getSubmissionEntityName();
		return $this->getInsertId($submissionEntityName.'_files', $submissionEntityName.'_id');
	}


	//
	// Private helper methods
	//
	/**
	 * Instantiates an appropriate SubmissionFileDAODelegate
	 * based on the given SubmissionFile.
	 * @param $object SubmissionFile
	 * @return SubmissionFileDAODelegate
	 */
	function &_getDaoDelegateForObject(&$object) {
		return $this->_getDaoDelegate(get_class($object));
	}

	/**
	 * Return the requested SubmissionFileDAODelegate.
	 * @param $fileImplementation string the class name of
	 *  a file implementation that the requested delegate
	 *  should serve.
	 * @return SubmissionFileDAODelegate
	 */
	function &_getDaoDelegate($fileImplementation) {
		// Normalize the file implementation name.
		$fileImplementation = strtolower($fileImplementation);

		// Did we already instantiate the requested delegate?
		if (!isset($this->_delegates[$fileImplementation])) {
			// Instantiate the requested delegate.
			$delegateClasses = $this->getDelegateClassNames();
			assert(isset($delegateClasses[$fileImplementation]));
			$delegateClass = $delegateClasses[$fileImplementation];
			$this->_delegates[$fileImplementation] =& instantiate($delegateClass, 'SubmissionFileDAODelegate', null, null, $this);
		}

		// Return the delegate.
		return $this->_delegates[$fileImplementation];
	}

	/**
	 * Protected method to retrieve submission file revisions
	 * according to the given filters.
	 * @param $submissionId integer
	 * @param $fileStage integer
	 * @param $fileId integer
	 * @param $revision integer
	 * @param $assocType integer
	 * @param $assocId integer
	 * @param $latestOnly boolean
	 * @param $rangeInfo DBResultRange
	 * @return array a list of SubmissionFile instances
	 */
	function &_getInternally($submissionId = null, $fileStage = null, $fileId = null, $revision = null,
			$assocType = null, $assocId = null, $latestOnly = false, $rangeInfo = null) {

		// Sanitize parameters.
		$latestOnly = (boolean)$latestOnly;
		if (!is_null($rangeInfo)) assert(is_a($rangeInfo, 'DBResultRange'));

		// Retrieve the base query.
		$sql = $this->baseQueryForFileSelection($latestOnly);

		// Filter the query.
		list($filterClause, $params) = $this->_buildFileSelectionFilter(
				$submissionId, $fileStage, $fileId, $revision, $assocType, $assocId);

		// Did the user request all or only the latest revision?
		$submissionEntity = $this->getSubmissionEntityName();
		if ($latestOnly) {
			// Filter the latest revision of each file.
			// NB: We have to do this in the SQL for paging to work
			// correctly. We use a partial cartesian join here to
			// maintain MySQL 3.23 backwards compatibility. This
			// should be ok as we usually only have few revisions per
			// file.
			$sql .= 'LEFT JOIN '.$submissionEntity.'_files sf2 ON sf.file_id = sf2.file_id AND sf.revision < sf2.revision
			         WHERE sf2.revision IS NULL AND '.$filterClause;
		} else {
			$sql .= 'WHERE '.$filterClause;
		}

		// Order the query.
		$sql .= ' ORDER BY sf.'.$submissionEntity.'_id ASC, sf.file_stage ASC, sf.file_id ASC, sf.revision DESC';

		// Execute the query.
		if ($rangeInfo) {
			$result =& $this->retrieveRange($sql, $params, $rangeInfo);
		} else {
			$result =& $this->retrieve($sql, $params);
		}

		// Build the result array.
		$submissionFiles = array();
		while (!$result->EOF) {
			// Retrieve the next result row.
			$row =& $result->GetRowAssoc(false);

			// Instantiate the file.
			$submissionFiles[] =& $this->fromRow($row);

			// Move the query cursor to the next record.
			$result->moveNext();
		}
		$result->Close();
		unset($result);

		return $submissionFiles;
	}

	/**
	 * Protected method to retrieve submission file revisions
	 * according to the given filters.
	 * @param $submissionId int
	 * @param $fileStage int
	 * @param $fileId int
	 * @param $revision int
	 * @param $assocType integer
	 * @param $assocId integer
	 * @param $latestOnly boolean
	 * @return boolean
	 */
	function _deleteInternally($submissionId = null, $fileStage = null, $fileId = null, $revision = null,
			$assocType = null, $assocId = null, $latestOnly = false) {

		// Identify all matched files.
		$deletedFiles =& $this->_getInternally($submissionId, $fileStage, $fileId, $revision, $assocType, $assocId, $latestOnly);
		if (empty($deletedFiles)) return 0;

		$filterClause = '';
		$conjunction = '';
		$params = array();
		foreach($deletedFiles as $deletedFile) { /* @var $deletedFile SubmissionFile */
			// Delete the the matched files on the file system.
			FileManager::deleteFile($deletedFile->getFilePath());

			// Concatenate the IDs together for use in the SQL delete
			// statement.
			// NB: We cannot use an IN clause here because MySQL 3.23
			// does not support multi-column IN-clauses. Same is true
			// for multi-table access or subselects in the DELETE
			// statement.
			$filterClause .= $conjunction.' (file_id=? AND revision=?)';
			$conjunction = ' OR';
			$params[] = $deletedFile->getFileId();
			$params[] = $deletedFile->getRevision();
		}

		// Delete the matched files in the database. We do so by calling
		// all delegates in turn with the given SQL parameters. The DAO
		// delegates will then bulk-delete the files in the corresponding
		// class table. We have to call all delegates because we cannot
		// be sure what mixture of file types we have to delete.
		foreach($this->getDelegateClassNames() as $fileImplementation => $delegateClassName) {
			$daoDelegate =& $this->_getDaoDelegate($fileImplementation);
			$daoDelegate->deleteObjects($filterClause, $params);
			unset($daoDelegate);
		}

		// Return the number of deleted files.
		return count($deletedFiles);
	}

	/**
	 * Update the data and id of a file that will become
	 * the latest revision of an existing (revised) file.
	 * @param $revisedFile SubmissionFile
	 * @param $newFile SubmissionFile
	 * @return SubmissionFile the updated new file
	 */
	function &_updateNewRevision(&$revisedFile, &$newFile) {
		// NB: We cannot use updateObject() becase we have
		// to change the id of the file.
		$this->update(
			'UPDATE '.$this->getSubmissionEntityName().'_files
			 SET
			     file_id = ?,
			     revision = ?,
			     genre_id = ?,
			     assoc_type =?,
			     assoc_id = ?
			 WHERE file_id = ?',
			array(
				$revisedFile->getFileId(),
				$newFile->getRevision(),
				$newFile->getGenreId(),
				$newFile->getAssocType(),
				$newFile->getAssocId(),
				$newFile->getFileId())
		);

		$newFile->setFileId($revisedFile->getFileId());
		return $newFile;
	}

	/**
	 * Build an SQL where clause to select
	 * submissions based on the given filter information.
	 * @param $submissionId integer
	 * @param $fileStage integer
	 * @param $fileId integer
	 * @param $revision integer
	 * @param $assocType integer
	 * @param $assocId integer
	 * @param $alias string the alias of the table to be filtered
	 * @return array an array that contains the generated SQL
	 *  filter clause and the corresponding parameters.
	 */
	function _buildFileSelectionFilter($submissionId, $fileStage,
			$fileId, $revision, $assocType, $assocId) {

		// Make sure that at least one entity filter has been set.
		assert((int)$submissionId || (int)$fileId || (int)$assocId);

		// Both, assoc type and id, must be set (or unset) together.
		assert(((int)$assocType && (int)$assocId) || !((int)$assocType || (int)$assocId));

		// Collect the filtered columns and ids in
		// an array for consistent handling.
		$submissionEntity = $this->getSubmissionEntityName();
		$filters = array(
			'sf.'.$submissionEntity.'_id' => $submissionId,
			'sf.file_stage' => $fileStage,
			'sf.file_id' => $fileId,
			'sf.revision' => $revision,
			'sf.assoc_type' => $assocType,
			'sf.assoc_id' => $assocId
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
}

?>
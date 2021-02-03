<?php

/**
 * @file classes/context/LibraryFileDAO.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class LibraryFileDAO
 * @ingroup context
 * @see LibraryFile
 *
 * @brief Operations for retrieving and modifying LibraryFile objects.
 */

import('lib.pkp.classes.context.LibraryFile');

class LibraryFileDAO extends DAO {

	/**
	 * Retrieve a library file by ID.
	 * @param $fileId int
	 * @param $contextId int optional
	 * @return LibraryFile
	 */
	function getById($fileId, $contextId = null) {
		$params = [(int) $fileId];
		if ($contextId) $params[] = (int) $contextId;

		$result = $this->retrieve(
			'SELECT file_id, context_id, file_name, original_file_name, file_type, file_size, type, date_uploaded, submission_id, public_access FROM library_files WHERE file_id = ?'
			. ($contextId ? ' AND context_id = ?' : ''),
			$params
		);
		$row = $result->current();
		return $row ? $this->_fromRow((array) $row) : null;
	}

	/**
	 * Retrieve all library files for a context.
	 * @param $contextId int
	 * @param $type (optional)
	 * @return array LibraryFiles
	 */
	function getByContextId($contextId, $type = null) {
		$params = [(int) $contextId];
		if (isset($type)) $params[] = (int) $type;

		$result = $this->retrieve(
			'SELECT	*
			FROM	library_files
			WHERE	context_id = ? AND submission_id = 0 ' . (isset($type)?' AND type = ?' : ''),
			$params
		);
		return new DAOResultFactory($result, $this, '_fromRow', ['id']);
	}

	/**
	 * Retrieve all library files for a submission.
	 * @param $submissionId int
	 * @param $type (optional)
	 * @param $contextId (optional) int
	 * @return array LibraryFiles
	 */
	function getBySubmissionId($submissionId, $type = null, $contextId = null) {
		$params = [(int) $submissionId];
		if (isset($type)) $params[] = (int) $type;
		if (isset($contextId)) $params[] = (int) $contextId;

		$result = $this->retrieve(
			'SELECT	*
			FROM	library_files
			WHERE	submission_id = ? ' . (isset($contextId)?' AND context_id = ?' : '') . (isset($type)?' AND type = ?' : ''),
			$params
		);
		return new DAOResultFactory($result, $this, '_fromRow', array('id'));
	}

	/**
	 * Construct a new data object corresponding to this DAO.
	 * @return LibraryFile
	 */
	function newDataObject() {
		return new LibraryFile();
	}


	/**
	 * Get the list of fields for which data is localized.
	 * @return array
	 */
	function getLocaleFieldNames() {
		return ['name'];
	}

	/**
	 * Update the localized fields for this file.
	 * @param $libraryFile
	 */
	function updateLocaleFields(&$libraryFile) {
		$this->updateDataObjectSettings(
			'library_file_settings',
			$libraryFile,
			['file_id' => $libraryFile->getId()]
		);
	}

	/**
	 * Internal function to return a LibraryFile object from a row.
	 * @param $row array
	 * @return LibraryFile
	 */
	function _fromRow($row) {
		$libraryFile = $this->newDataObject();

		$libraryFile->setId($row['file_id']);
		$libraryFile->setContextId($row['context_id']);
		$libraryFile->setServerFileName($row['file_name']);
		$libraryFile->setOriginalFileName($row['original_file_name']);
		$libraryFile->setFileType($row['file_type']);
		$libraryFile->setFileSize($row['file_size']);
		$libraryFile->setType($row['type']);
		$libraryFile->setDateUploaded($this->datetimeFromDB($row['date_uploaded']));
		$libraryFile->setSubmissionId($row['submission_id']);
		$libraryFile->setPublicAccess($row['public_access']);

		$this->getDataObjectSettings('library_file_settings', 'file_id', $row['file_id'], $libraryFile);

		HookRegistry::call('LibraryFileDAO::_fromRow', array(&$libraryFile, &$row));

		return $libraryFile;
	}

	/**
	 * Insert a new LibraryFile.
	 * @param $libraryFile LibraryFile
	 * @return int
	 */
	function insertObject($libraryFile) {
		$params = [
			(int) $libraryFile->getContextId(),
			$libraryFile->getServerFileName(),
			$libraryFile->getOriginalFileName(),
			$libraryFile->getFileType(),
			(int) $libraryFile->getFileSize(),
			(int) $libraryFile->getType(),
			(int) $libraryFile->getSubmissionId(),
			(int) $libraryFile->getPublicAccess()
		];

		if ($libraryFile->getId()) $params[] = (int) $libraryFile->getId();

		$this->update(
			sprintf('INSERT INTO library_files
				(context_id, file_name, original_file_name, file_type, file_size, type, submission_id, public_access, date_uploaded, date_modified' . ($libraryFile->getId()?', file_id':'') . ')
				VALUES
				(?, ?, ?, ?, ?, ?, ?, ?, %s, %s' . ($libraryFile->getId()?', ?':'') . ')',
				$this->datetimeToDB($libraryFile->getDateUploaded()),
				$this->datetimeToDB($libraryFile->getDateModified())
			),
			$params
		);

		if (!$libraryFile->getId()) $libraryFile->setId($this->getInsertId());

		$this->updateLocaleFields($libraryFile);
		return $libraryFile->getId();
	}

	/**
	 * Update a LibraryFile
	 * @param $libraryFile LibraryFile
	 * @return int
	 */
	function updateObject($libraryFile) {
		$this->update(
			sprintf('UPDATE	library_files
				SET	context_id = ?,
					file_name = ?,
					original_file_name = ?,
					file_type = ?,
					file_size = ?,
					type = ?,
					submission_id = ?,
					public_access = ?,
					date_uploaded = %s
				WHERE	file_id = ?',
				$this->datetimeToDB($libraryFile->getDateUploaded())
			), [
				(int) $libraryFile->getContextId(),
				$libraryFile->getServerFileName(),
				$libraryFile->getOriginalFileName(),
				$libraryFile->getFileType(),
				(int) $libraryFile->getFileSize(),
				(int) $libraryFile->getType(),
				(int) $libraryFile->getSubmissionId(),
				(int) $libraryFile->getPublicAccess(),
				(int) $libraryFile->getId()
			]
		);

		$this->updateLocaleFields($libraryFile);
		return $libraryFile->getId();
	}

	/**
	 * Delete a library file by ID.
	 * @param $libraryId int
	 * @param $revision int
	 */
	function deleteById($fileId, $revision = null) {
		$this->update('DELETE FROM library_files WHERE file_id = ?', [(int) $fileId]);
		$this->update('DELETE FROM library_file_settings WHERE file_id = ?', [(int) $fileId]);
	}

	/**
	 * Check if a file with this filename already exists
	 * @param $contextId int the context to check in.
	 * @param $filename String the filename to be checked
	 * @return bool
	 */
	function filenameExists($contextId, $fileName) {
		$result = $this->retrieve(
			'SELECT COUNT(*) AS row_count FROM library_files WHERE context_id = ? AND file_name = ?',
			[(int) $contextId, $fileName]
		);
		$row = $result->current();
		return $row ? (boolean) $row->row_count : false;
	}

	/**
	 * Get the ID of the last inserted library file.
	 * @return int
	 */
	function getInsertId() {
		return $this->_getInsertId('library_files', 'file_id');
	}
}



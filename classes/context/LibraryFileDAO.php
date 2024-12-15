<?php

/**
 * @file classes/context/LibraryFileDAO.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class LibraryFileDAO
 *
 * @ingroup context
 *
 * @see LibraryFile
 *
 * @brief Operations for retrieving and modifying LibraryFile objects.
 */

namespace PKP\context;

use Illuminate\Support\Facades\DB;
use PKP\db\DAOResultFactory;
use PKP\plugins\Hook;

class LibraryFileDAO extends \PKP\db\DAO
{
    /**
     * Retrieve a library file by ID.
     *
     * @param int $fileId
     *
     * @return LibraryFile
     */
    public function getById($fileId, ?int $contextId = null)
    {
        $params = [(int) $fileId];
        if ($contextId) {
            $params[] = (int) $contextId;
        }

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
     *
     * @param string $type (optional)
     *
     * @return DAOResultFactory<LibraryFile> LibraryFiles
     */
    public function getByContextId(int $contextId, $type = null)
    {
        $params = [$contextId];
        if (isset($type)) {
            $params[] = (int) $type;
        }

        $result = $this->retrieve(
            'SELECT	*
			FROM	library_files
			WHERE	context_id = ? AND submission_id IS NULL ' . (isset($type) ? ' AND type = ?' : ''),
            $params
        );
        return new DAOResultFactory($result, $this, '_fromRow', ['id']);
    }

    /**
     * Retrieve all library files for a submission.
     *
     * @param string $type (optional)
     *
     * @return DAOResultFactory<LibraryFile> LibraryFiles
     */
    public function getBySubmissionId(int $submissionId, $type = null, ?int $contextId = null)
    {
        $params = [(int) $submissionId];
        if (isset($type)) {
            $params[] = (int) $type;
        }
        if (isset($contextId)) {
            $params[] = (int) $contextId;
        }

        $result = $this->retrieve(
            'SELECT	*
			FROM	library_files
			WHERE	submission_id = ? ' . (isset($contextId) ? ' AND context_id = ?' : '') . (isset($type) ? ' AND type = ?' : ''),
            $params
        );
        return new DAOResultFactory($result, $this, '_fromRow', ['id']);
    }

    /**
     * Construct a new data object corresponding to this DAO.
     *
     * @return LibraryFile
     */
    public function newDataObject()
    {
        return new LibraryFile();
    }


    /**
     * Get the list of fields for which data is localized.
     */
    public function getLocaleFieldNames(): array
    {
        return ['name', 'description'];
    }

    /**
     * Update the localized fields for this file.
     *
     * @param LibraryFile $libraryFile
     */
    public function updateLocaleFields(&$libraryFile)
    {
        $this->updateDataObjectSettings(
            'library_file_settings',
            $libraryFile,
            ['file_id' => $libraryFile->getId()]
        );
    }

    /**
     * Internal function to return a LibraryFile object from a row.
     *
     * @param array $row
     *
     * @return LibraryFile
     *
     * @hook LibraryFileDAO::_fromRow [[&$libraryFile, &$row]]
     */
    public function _fromRow($row)
    {
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

        Hook::call('LibraryFileDAO::_fromRow', [&$libraryFile, &$row]);

        return $libraryFile;
    }

    /**
     * Insert a new LibraryFile.
     *
     * @param LibraryFile $libraryFile
     *
     * @return int
     */
    public function insertObject($libraryFile)
    {
        $params = [
            (int) $libraryFile->getContextId(),
            $libraryFile->getServerFileName(),
            $libraryFile->getOriginalFileName(),
            $libraryFile->getFileType(),
            (int) $libraryFile->getFileSize(),
            (int) $libraryFile->getType(),
            $libraryFile->getSubmissionId() ? (int) $libraryFile->getSubmissionId() : null,
            (int) $libraryFile->getPublicAccess()
        ];

        if ($libraryFile->getId()) {
            $params[] = (int) $libraryFile->getId();
        }

        $this->update(
            sprintf(
                'INSERT INTO library_files
				(context_id, file_name, original_file_name, file_type, file_size, type, submission_id, public_access, date_uploaded, date_modified' . ($libraryFile->getId() ? ', file_id' : '') . ')
				VALUES
				(?, ?, ?, ?, ?, ?, ?, ?, %s, %s' . ($libraryFile->getId() ? ', ?' : '') . ')',
                $this->datetimeToDB($libraryFile->getDateUploaded()),
                $this->datetimeToDB($libraryFile->getDateModified())
            ),
            $params
        );

        if (!$libraryFile->getId()) {
            $libraryFile->setId($this->getInsertId());
        }

        $this->updateLocaleFields($libraryFile);
        return $libraryFile->getId();
    }

    /**
     * Update a LibraryFile
     *
     * @param LibraryFile $libraryFile
     *
     * @return int
     */
    public function updateObject($libraryFile)
    {
        $this->update(
            sprintf(
                'UPDATE	library_files
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
            ),
            [
                (int) $libraryFile->getContextId(),
                $libraryFile->getServerFileName(),
                $libraryFile->getOriginalFileName(),
                $libraryFile->getFileType(),
                (int) $libraryFile->getFileSize(),
                (int) $libraryFile->getType(),
                $libraryFile->getSubmissionId() ? (int) $libraryFile->getSubmissionId() : null,
                (int) $libraryFile->getPublicAccess(),
                (int) $libraryFile->getId()
            ]
        );

        $this->updateLocaleFields($libraryFile);
        return $libraryFile->getId();
    }

    /**
     * Delete a library file by ID.
     */
    public function deleteById(int $fileId): int
    {
        return DB::table('library_files')
            ->where('file_id', '=', $fileId)
            ->delete();
    }

    /**
     * Check if a file with this filename already exists
     *
     * @return bool
     */
    public function filenameExists(int $contextId, $fileName)
    {
        $result = $this->retrieve(
            'SELECT COUNT(*) AS row_count FROM library_files WHERE context_id = ? AND file_name = ?',
            [$contextId, $fileName]
        );
        $row = $result->current();
        return $row ? (bool) $row->row_count : false;
    }
}

if (!PKP_STRICT_MODE) {
    class_alias('\PKP\context\LibraryFileDAO', '\LibraryFileDAO');
}

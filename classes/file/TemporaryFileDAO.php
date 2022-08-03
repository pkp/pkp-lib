<?php

/**
 * @file classes/file/TemporaryFileDAO.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class TemporaryFileDAO
 * @ingroup file
 *
 * @see TemporaryFile
 *
 * @brief Operations for retrieving and modifying TemporaryFile objects.
 */

namespace PKP\file;

use PKP\plugins\HookRegistry;

class TemporaryFileDAO extends \PKP\db\DAO
{
    /**
     * Retrieve a temporary file by ID.
     *
     * @param int $fileId
     * @param int $userId
     *
     * @return ?TemporaryFile
     */
    public function getTemporaryFile($fileId, $userId)
    {
        $result = $this->retrieve(
            'SELECT t.* FROM temporary_files t WHERE t.file_id = ? and t.user_id = ?',
            [(int) $fileId, (int) $userId]
        );

        $row = (array) $result->current();
        return $row ? $this->_returnTemporaryFileFromRow($row) : null;
    }

    /**
     * Instantiate and return a new data object.
     *
     * @return TemporaryFile
     */
    public function newDataObject()
    {
        return new TemporaryFile();
    }

    /**
     * Internal function to return a TemporaryFile object from a row.
     *
     * @param array $row
     *
     * @return TemporaryFile
     */
    public function _returnTemporaryFileFromRow($row)
    {
        $temporaryFile = $this->newDataObject();
        $temporaryFile->setId($row['file_id']);
        $temporaryFile->setServerFileName($row['file_name']);
        $temporaryFile->setFileType($row['file_type']);
        $temporaryFile->setFileSize($row['file_size']);
        $temporaryFile->setUserId($row['user_id']);
        $temporaryFile->setOriginalFileName($row['original_file_name']);
        $temporaryFile->setDateUploaded($this->datetimeFromDB($row['date_uploaded']));

        HookRegistry::call('TemporaryFileDAO::_returnTemporaryFileFromRow', [&$temporaryFile, &$row]);

        return $temporaryFile;
    }

    /**
     * Insert a new TemporaryFile.
     *
     * @param TemporaryFile $temporaryFile
     *
     * @return int
     */
    public function insertObject($temporaryFile)
    {
        $this->update(
            sprintf(
                'INSERT INTO temporary_files
				(user_id, file_name, file_type, file_size, original_file_name, date_uploaded)
				VALUES
				(?, ?, ?, ?, ?, %s)',
                $this->datetimeToDB($temporaryFile->getDateUploaded())
            ),
            [
                (int) $temporaryFile->getUserId(),
                $temporaryFile->getServerFileName(),
                $temporaryFile->getFileType(),
                (int) $temporaryFile->getFileSize(),
                $temporaryFile->getOriginalFileName()
            ]
        );

        $temporaryFile->setId($this->getInsertId());
        return $temporaryFile->getId();
    }

    /**
     * Update an existing temporary file.
     */
    public function updateObject($temporaryFile)
    {
        $this->update(
            sprintf(
                'UPDATE temporary_files
				SET
					file_name = ?,
					file_type = ?,
					file_size = ?,
					user_id = ?,
					original_file_name = ?,
					date_uploaded = %s
				WHERE file_id = ?',
                $this->datetimeToDB($temporaryFile->getDateUploaded())
            ),
            [
                $temporaryFile->getServerFileName(),
                $temporaryFile->getFileType(),
                (int) $temporaryFile->getFileSize(),
                (int) $temporaryFile->getUserId(),
                $temporaryFile->getOriginalFileName(),
                (int) $temporaryFile->getId()
            ]
        );

        return $temporaryFile->getId();
    }

    /**
     * Delete a temporary file by ID.
     *
     * @param int $fileId
     * @param int $userId
     */
    public function deleteTemporaryFileById($fileId, $userId)
    {
        return $this->update('DELETE FROM temporary_files WHERE file_id = ? AND user_id = ?', [(int) $fileId, (int) $userId]);
    }

    /**
     * Delete temporary files by user ID.
     *
     * @param int $userId
     */
    public function deleteByUserId($userId)
    {
        return $this->update('DELETE FROM temporary_files WHERE user_id = ?', [(int) $userId]);
    }

    /**
     * Get all expired temorary files.
     *
     * @return array
     */
    public function getExpiredFiles()
    {
        // Files older than one day can be cleaned up.
        $expiryThresholdTimestamp = time() - (60 * 60 * 24);
        $temporaryFiles = [];
        $result = $this->retrieve('SELECT * FROM temporary_files WHERE date_uploaded < ' . $this->datetimeToDB($expiryThresholdTimestamp));
        foreach ($result as $row) {
            $temporaryFiles[] = $this->_returnTemporaryFileFromRow((array) $row);
        }
        return $temporaryFiles;
    }

    /**
     * Get the ID of the last inserted temporary file.
     *
     * @return int
     */
    public function getInsertId()
    {
        return $this->_getInsertId('temporary_files', 'file_id');
    }
}

if (!PKP_STRICT_MODE) {
    class_alias('\PKP\file\TemporaryFileDAO', '\TemporaryFileDAO');
}

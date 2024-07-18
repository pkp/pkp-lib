<?php

/**
 * @file classes/file/TemporaryFileManager.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class TemporaryFileManager
 *
 * @ingroup file
 *
 * @see TemporaryFileDAO
 *
 * @brief Class defining operations for temporary file management.
 */

namespace PKP\file;

use PKP\core\Core;
use PKP\core\PKPString;
use PKP\db\DAORegistry;

class TemporaryFileManager extends PrivateFileManager
{
    /**
     * Constructor
     */
    public function __construct()
    {
        parent::__construct();

        $this->_performPeriodicCleanup();
    }

    /**
     * Get the base path for temporary file storage.
     *
     * @return string
     */
    public function getBasePath()
    {
        return parent::getBasePath() . '/temp/';
    }

    /**
     * Retrieve file information by file ID.
     *
     * @return TemporaryFile
     */
    public function getFile($fileId, $userId)
    {
        $temporaryFileDao = DAORegistry::getDAO('TemporaryFileDAO'); /** @var TemporaryFileDAO $temporaryFileDao */
        return $temporaryFileDao->getTemporaryFile($fileId, $userId);
    }

    /**
     * Delete a file by ID.
     */
    public function deleteById(int $fileId, int $userId): int
    {
        $temporaryFile = $this->getFile($fileId, $userId);

        parent::deleteByPath($this->getBasePath() . $temporaryFile->getServerFileName());

        $temporaryFileDao = DAORegistry::getDAO('TemporaryFileDAO'); /** @var TemporaryFileDAO $temporaryFileDao */
        return $temporaryFileDao->deleteTemporaryFileById($fileId, $userId);
    }

    /**
     * Download a file.
     *
     * @param int $fileId the file id of the file to download
     * @param bool $inline print file as inline instead of attachment, optional
     *
     * @return bool
     */
    public function downloadById(int $fileId, int $userId, bool $inline = false)
    {
        $temporaryFile = $this->getFile($fileId, $userId);
        if (isset($temporaryFile)) {
            $filePath = $this->getBasePath() . $temporaryFile->getServerFileName();
            return parent::downloadByPath($filePath, null, $inline);
        } else {
            return false;
        }
    }

    /**
     * Upload the file and add it to the database.
     *
     * @param string $fileName index into the $_FILES array
     * @param int $userId
     *
     * @return object|boolean The new TemporaryFile or false on failure
     */
    public function handleUpload($fileName, $userId)
    {
        // Get the file extension, then rename the file.
        $fileExtension = $this->parseFileExtension($this->getUploadedFileName($fileName));

        if (!$this->fileExists($this->getBasePath(), 'dir')) {
            // Try to create destination directory
            $this->mkdirtree($this->getBasePath());
        }

        $newFileName = basename(tempnam($this->getBasePath(), $fileExtension));
        if (!$newFileName) {
            return false;
        }

        if ($this->uploadFile($fileName, $this->getBasePath() . $newFileName)) {
            $temporaryFileDao = DAORegistry::getDAO('TemporaryFileDAO'); /** @var TemporaryFileDAO $temporaryFileDao */
            $temporaryFile = $temporaryFileDao->newDataObject();

            $temporaryFile->setUserId($userId);
            $temporaryFile->setServerFileName($newFileName);
            $exploded = explode('.', $_FILES[$fileName]['name']);
            $temporaryFile->setFileType(PKPString::mime_content_type($this->getBasePath() . $newFileName, array_pop($exploded)));
            $temporaryFile->setFileSize($_FILES[$fileName]['size']);
            $temporaryFile->setOriginalFileName($this->truncateFileName($_FILES[$fileName]['name'], 127));
            $temporaryFile->setDateUploaded(Core::getCurrentDate());

            $temporaryFileDao->insertObject($temporaryFile);

            return $temporaryFile;
        } else {
            return false;
        }
    }

    /**
     * Creates a TemporaryFile entry in DB based on an existing file in the system temp directory
     *
     *
     * @throws \Exception
     */
    public function createTempFileFromExisting(string $fileName, int $userId): int
    {
        $temporaryFileDao = DAORegistry::getDAO('TemporaryFileDAO'); /** @var TemporaryFileDAO $temporaryFileDao */
        $temporaryFile = $temporaryFileDao->newDataObject();

        $temporaryFile->setUserId($userId);
        $temporaryFile->setServerFileName(pathinfo($fileName, PATHINFO_BASENAME));
        $fileSize = filesize($fileName);
        $temporaryFile->setFileSize($fileSize);
        $temporaryFile->setDateUploaded(Core::getCurrentDate());

        return $temporaryFileDao->insertObject($temporaryFile);
    }

    /**
     * Perform periodic cleanup tasks. This is used to occasionally
     * remove expired temporary files.
     */
    public function _performPeriodicCleanup()
    {
        if (time() % 100 == 0) {
            $temporaryFileDao = DAORegistry::getDAO('TemporaryFileDAO'); /** @var TemporaryFileDAO $temporaryFileDao */
            $expiredFiles = $temporaryFileDao->getExpiredFiles();
            foreach ($expiredFiles as $expiredFile) {
                $this->deleteById($expiredFile->getId(), $expiredFile->getUserId());
            }
        }
    }
}

if (!PKP_STRICT_MODE) {
    class_alias('\PKP\file\TemporaryFileManager', '\TemporaryFileManager');
}

<?php

/**
 * @file classes/file/PKPLibraryFileManager.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PKPLibraryFileManager
 *
 * @ingroup file
 *
 * @brief Wrapper class for uploading files to a site/context' library directory.
 */

namespace PKP\file;

use PKP\context\LibraryFile;
use PKP\context\LibraryFileDAO;
use PKP\core\PKPString;
use PKP\db\DAORegistry;

class PKPLibraryFileManager extends PrivateFileManager
{
    /** @var int Context id for the current context */
    public $contextId;

    /**
     * Constructor
     *
     * @param int $contextId
     */
    public function __construct($contextId)
    {
        parent::__construct();
        $this->contextId = $contextId;
    }

    /**
     * Get the base path for file storage.
     *
     * @return string
     */
    public function getBasePath()
    {
        return parent::getBasePath() . '/contexts/' . $this->contextId . '/library/';
    }

    /**
     * Delete a file by ID.
     *
     * @param int $fileId
     */
    public function deleteById($fileId)
    {
        $libraryFileDao = DAORegistry::getDAO('LibraryFileDAO'); /** @var LibraryFileDAO $libraryFileDao */
        $libraryFile = $libraryFileDao->getById($fileId);

        parent::deleteByPath($this->getBasePath() . $libraryFile->getServerFileName());

        $libraryFileDao->deleteById($fileId);
    }

    /**
     * Generate a filename for a library file.
     *
     * @param int $type LIBRARY_FILE_TYPE_...
     * @param string $originalFileName
     *
     * @return string
     */
    public function generateFileName($type, $originalFileName)
    {
        $libraryFileDao = DAORegistry::getDAO('LibraryFileDAO'); /** @var LibraryFileDAO $libraryFileDao */
        $suffix = $this->getFileSuffixFromType($type);
        $ext = $this->getExtension($originalFileName);
        $truncated = $this->truncateFileName($originalFileName, 127 - PKPString::strlen($suffix) - 1);
        $baseName = PKPString::substr($truncated, 0, PKPString::strpos($originalFileName, $ext) - 1);

        // Try a simple syntax first
        $fileName = $baseName . '-' . $suffix . '.' . $ext;
        if (!$libraryFileDao->filenameExists($this->contextId, $fileName)) {
            return $fileName;
        }

        for ($i = 1; ; $i++) {
            $fullSuffix = $suffix . '-' . $i;
            //truncate more if necessary
            $truncated = $this->truncateFileName($originalFileName, 127 - PKPString::strlen($fullSuffix) - 1);
            // get the base name and append the suffix
            $baseName = PKPString::substr($truncated, 0, PKPString::strpos($originalFileName, $ext) - 1);

            //try the following
            $fileName = $baseName . '-' . $fullSuffix . '.' . $ext;
            if (!$libraryFileDao->filenameExists($this->contextId, $fileName)) {
                return $fileName;
            }
        }
    }

    /**
     * Routine to copy a library file from a temporary file.
     *
     * @param object $temporaryFile
     * @param int $libraryFileType LIBRARY_FILE_TYPE_...
     *
     * @return false|LibraryFile the generated file, prepared as much as possible for insert (false if upload failed)
     */
    public function &copyFromTemporaryFile(&$temporaryFile, $libraryFileType)
    {
        $libraryFileDao = DAORegistry::getDAO('LibraryFileDAO'); /** @var LibraryFileDAO $libraryFileDao */
        $libraryFile = $libraryFileDao->newDataObject();

        $libraryFile->setDateUploaded($temporaryFile->getDateUploaded());
        $libraryFile->setDateModified($temporaryFile->getDateUploaded());
        $libraryFile->setFileType($temporaryFile->getFileType());
        $libraryFile->setFileSize($temporaryFile->getFileSize());
        $libraryFile->setServerFileName($this->generateFileName($libraryFileType, $temporaryFile->getOriginalFileName()));
        $libraryFile->setOriginalFileName($temporaryFile->getOriginalFileName());
        if (!$this->copyFile($temporaryFile->getFilePath(), $this->getBasePath() . $libraryFile->getServerFileName())) {
            return false;
        }

        return $libraryFile;
    }

    /**
     * Get the file suffix for the given file type
     *
     * @param int $type LIBRARY_FILE_TYPE_...
     */
    public function getFileSuffixFromType($type)
    {
        $typeSuffixMap = & $this->getTypeSuffixMap();
        return $typeSuffixMap[$type];
    }

    /**
     * Get the type => suffix mapping array
     *
     * @return array
     */
    public function &getTypeSuffixMap()
    {
        static $map = [
            LibraryFile::LIBRARY_FILE_TYPE_MARKETING => 'MAR',
            LibraryFile::LIBRARY_FILE_TYPE_PERMISSION => 'PER',
            LibraryFile::LIBRARY_FILE_TYPE_REPORT => 'REP',
            LibraryFile::LIBRARY_FILE_TYPE_OTHER => 'OTH'
        ];
        return $map;
    }

    /**
     * Get the symbolic name from the type
     *
     * @param int $type LIBRARY_FILE_TYPE_...
     */
    public function getNameFromType($type)
    {
        $typeNameMap = & $this->getTypeNameMap();
        if (isset($typeNameMap[$type])) {
            return $typeNameMap[$type];
        } else {
            return false;
        }
    }

    /**
     * Get the type => locale key mapping array
     *
     * @return array
     */
    public function &getTypeTitleKeyMap()
    {
        static $map = [
            LibraryFile::LIBRARY_FILE_TYPE_MARKETING => 'settings.libraryFiles.category.marketing',
            LibraryFile::LIBRARY_FILE_TYPE_PERMISSION => 'settings.libraryFiles.category.permissions',
            LibraryFile::LIBRARY_FILE_TYPE_REPORT => 'settings.libraryFiles.category.reports',
            LibraryFile::LIBRARY_FILE_TYPE_OTHER => 'settings.libraryFiles.category.other'
        ];
        return $map;
    }

    /**
     * Get the display name locale key from the type title
     *
     * @param int $type LIBRARY_FILE_TYPE_...
     */
    public function getTitleKeyFromType($type)
    {
        $typeTitleKeyMap = & $this->getTypeTitleKeyMap();
        return $typeTitleKeyMap[$type];
    }

    /**
     * Get the type => name mapping array
     *
     * @return array
     */
    public function &getTypeNameMap()
    {
        static $typeNameMap = [
            LibraryFile::LIBRARY_FILE_TYPE_MARKETING => 'marketing',
            LibraryFile::LIBRARY_FILE_TYPE_PERMISSION => 'permissions',
            LibraryFile::LIBRARY_FILE_TYPE_REPORT => 'reports',
            LibraryFile::LIBRARY_FILE_TYPE_OTHER => 'other',
        ];
        return $typeNameMap;
    }
}

if (!PKP_STRICT_MODE) {
    class_alias('\PKP\file\PKPLibraryFileManager', '\PKPLibraryFileManager');
}

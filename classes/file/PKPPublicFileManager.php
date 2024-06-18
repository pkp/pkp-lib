<?php

/**
 * @file classes/file/PKPPublicFileManager.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PKPPublicFileManager
 *
 * @ingroup file
 *
 * @brief Wrapper class for uploading files to a site/journal's public directory.
 */

namespace PKP\file;

use PKP\config\Config;

abstract class PKPPublicFileManager extends FileManager
{
    /**
     * Get the path to the site public files directory.
     *
     * @return string
     */
    public function getSiteFilesPath()
    {
        return Config::getVar('files', 'public_files_dir') . '/site';
    }

    /**
     * Get the path to a context's public files directory.
     *
     * @param int $contextId Context ID
     *
     * @return string
     */
    abstract public function getContextFilesPath(int $contextId);

    /**
     * Upload a file to a context's public directory.
     *
     * @param int $contextId The context ID
     * @param string $fileName the name of the file in the upload form
     * @param string $destFileName the destination file name
     *
     * @return bool
     */
    public function uploadContextFile(int $contextId, $fileName, $destFileName)
    {
        return $this->uploadFile($fileName, $this->getContextFilesPath($contextId) . '/' . $destFileName);
    }

    /**
     * Write a file to a context's public directory.
     *
     * @param int $contextId Context ID
     * @param string $destFileName the destination file name
     * @param string $contents the contents to write to the file
     *
     * @return bool
     */
    public function writeContextFile(int $contextId, $destFileName, $contents)
    {
        return $this->writeFile($this->getContextFilesPath($contextId) . '/' . $destFileName, $contents);
    }

    /**
     * Upload a file to the site's public directory.
     *
     * @param string $fileName the name of the file in the upload form
     * @param string $destFileName the destination file name
     *
     * @return bool
     */
    public function uploadSiteFile($fileName, $destFileName)
    {
        return $this->uploadFile($fileName, $this->getSiteFilesPath() . '/' . $destFileName);
    }

    /**
     * Copy a file to the site's public directory.
     *
     * @param string $sourceFile the source of the file to copy
     * @param string $destFileName the destination file name
     *
     * @return bool
     */
    public function copySiteFile($sourceFile, $destFileName)
    {
        return $this->copyFile($sourceFile, $this->getSiteFilesPath() . '/' . $destFileName);
    }

    /**
     * Copy a file to a site's public directory.
     *
     * @param int $contextId Context ID
     * @param string $sourceFile the source of the file to copy
     * @param string $destFileName the destination file name
     *
     * @return bool
     */
    public function copyContextFile(int $contextId, $sourceFile, $destFileName)
    {
        return $this->copyFile($sourceFile, $this->getContextFilesPath($contextId) . '/' . $destFileName);
    }

    /**
     * Delete a file from a context's public directory.
     *
     * @param int $contextId Context ID
     * @param string $fileName the target file name
     *
     * @return bool
     */
    public function removeContextFile(int $contextId, $fileName)
    {
        return $this->deleteByPath($this->getContextFilesPath($contextId) . '/' . $fileName);
    }

    /**
     * Delete a file from the site's public directory.
     *
     * @param string $fileName the target file name
     *
     * @return bool
     */
    public function removeSiteFile($fileName)
    {
        return $this->deleteByPath($this->getSiteFilesPath() . '/' . $fileName);
    }
}

if (!PKP_STRICT_MODE) {
    class_alias('\PKP\file\PKPPublicFileManager', '\PKPPublicFileManager');
}

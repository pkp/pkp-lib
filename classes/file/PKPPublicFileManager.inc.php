<?php

/**
 * @file classes/file/PKPPublicFileManager.inc.php
 *
 * Copyright (c) 2014-2018 Simon Fraser University
 * Copyright (c) 2000-2018 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class PKPPublicFileManager
 * @ingroup file
 *
 * @brief Wrapper class for uploading files to a site/journal's public directory.
 */


import('lib.pkp.classes.file.FileManager');

abstract class PKPPublicFileManager extends FileManager {

	/**
	 * Get the path to the site public files directory.
	 * @return string
	 */
	public function getSiteFilesPath() {
		return Config::getVar('files', 'public_files_dir') . '/site';
	}

	/**
	 * Get the path to a context's public files directory.
	 * @param $assocType int Assoc type for context
	 * @param $contextId int Context ID
	 * @return string
	 */
	abstract public function getContextFilesPath($contextId);

	/**
	 * Upload a file to a context's public directory.
	 * @param $contextId int The context ID
	 * @param $fileName string the name of the file in the upload form
	 * @param $destFileName string the destination file name
	 * @return boolean
	 */
	public function uploadContextFile($contextId, $fileName, $destFileName) {
		return $this->uploadFile($fileName, $this->getContextFilesPath($contextId) . '/' . $destFileName);
	}

	/**
	 * Write a file to a context's public directory.
	 * @param $contextId int Context ID
	 * @param $destFileName string the destination file name
	 * @param $contents string the contents to write to the file
	 * @return boolean
	 */
	public function writeContextFile($contextId, $destFileName, $contents) {
		return $this->writeFile($this->getContextFilesPath($contextId) . '/' . $destFileName, $contents);
	}

	/**
	 * Upload a file to the site's public directory.
	 * @param $fileName string the name of the file in the upload form
	 * @param $destFileName string the destination file name
	 * @return boolean
	 */
	public function uploadSiteFile($fileName, $destFileName) {
		return $this->uploadFile($fileName, $this->getSiteFilesPath() . '/' . $destFileName);
	}

	/**
	 * Copy a file to a context's public directory.
	 * @param $assocType Assoc type for context
	 * @param $contextId int Context ID
	 * @param $sourceFile string the source of the file to copy
	 * @param $destFileName string the destination file name
	 * @return boolean
	 */
	public function copyContextFile($assocType, $contextId, $sourceFile, $destFileName) {
		return $this->copyFile($sourceFile, $this->getContextFilesPath($contextId) . '/' . $destFileName);
	}

	/**
	 * Delete a file from a context's public directory.
	 * @param $contextId int Context ID
	 * @param $fileName string the target file name
	 * @return boolean
	 */
	public function removeContextFile($contextId, $fileName) {
		return $this->deleteByPath($this->getContextFilesPath($contextId) . '/' . $fileName);
	}

	/**
	 * Delete a file from the site's public directory.
	 * @param $fileName string the target file name
	 * @return boolean
	 */
	public function removeSiteFile($fileName) {
		return $this->deleteByPath($this->getSiteFilesPath() . '/' . $fileName);
	}
}



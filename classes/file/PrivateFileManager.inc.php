<?php

/**
 * @file classes/file/PrivateFileManager.inc.php
 *
 * Copyright (c) 2013-2015 Simon Fraser University Library
 * Copyright (c) 2000-2015 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class PrivateFileManager
 * @ingroup file
 *
 * @brief Class defining operations for private file management.
 */

import('lib.pkp.classes.file.FileManager');

class PrivateFileManager extends FileManager {
	/**
	 * Constructor
	 */
	function PrivateFileManager() {
		parent::FileManager();
	}

	/**
	 * Get the base path for file storage.
	 * @return string
	 */
	function getBasePath() {
		return Config::getVar('files', 'files_dir');
	}
}

?>

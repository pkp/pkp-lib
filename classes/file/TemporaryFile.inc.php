<?php

/**
 * @file classes/file/TemporaryFile.inc.php
 *
 * Copyright (c) 2013-2015 Simon Fraser University Library
 * Copyright (c) 2000-2015 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class TemporaryFile
 * @ingroup file
 * @see TemporaryFileDAO
 *
 * @brief Temporary file class.
 */

import('lib.pkp.classes.file.PKPFile');

class TemporaryFile extends PKPFile {

	/**
	 * Constructor.
	 */
	function TemporaryFile() {
		parent::PKPFile();
	}

	/**
	 * Return absolute path to the file on the host filesystem.
	 * @return string
	 */
	function getFilePath() {
		import('lib.pkp.classes.file.PKPTemporaryFileManager');
		$temporaryFileManager = new PKPTemporaryFileManager();
		return $temporaryFileManager->getBasePath() . $this->getFileName();
	}

	//
	// Get/set methods
	//

	/**
	 * Get ID of associated user.
	 * @return int
	 */
	function getUserId() {
		return $this->getData('userId');
	}

	/**
	 * Set ID of associated user.
	 * @param $userId int
	 */
	function setUserId($userId) {
		return $this->setData('userId', $userId);
	}
}

?>

<?php

/**
 * @defgroup file
 */

/**
 * @file classes/file/FileArchive.inc.php
 *
 * Copyright (c) 2013-2015 Simon Fraser University Library
 * Copyright (c) 2000-2015 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class FileArchive
 * @ingroup file
 *
 * @brief Class provides functionality for creating an archive of files.
 */

class FileArchive {

	function FileArchive() {
	}

	/**
	 * Assembles an array of filenames into either a tar.gz or a .zip
	 * file, based on what is available.  Returns a string representing
	 * the path to the archive on disk.
	 * @param array $files the files to add.
	 * @param string $filesDir a path to the files on disk.
	 * @return string the path to the archive.
	 */
	function create($files, $filesDir) {
		// Create a temporary file.
		$archivePath = tempnam('/tmp', 'sf-');

		// attempt to use Zip first, if it is available.  Otherwise
		// fall back to the tar CLI.
		$zipTest = false;
		if ($this->zipFunctional()) {
			$zipTest = true;
			$zip = new ZipArchive();
			if ($zip->open($archivePath, ZIPARCHIVE::CREATE) == true) {
				foreach ($files as $file) {
					$zip->addFile($filesDir . '/' . $file, basename($file));
				}
				$zip->close();
			}
		} else {
			// Create the archive and download the file.
			exec(Config::getVar('cli', 'tar') . ' -c -z ' .
					'-f ' . escapeshellarg($archivePath) . ' ' .
					'-C ' . escapeshellarg($filesDir) . ' ' .
					implode(' ', array_map('escapeshellarg', $files))
			);
		}

		return $archivePath;
	}

	/**
	 * Return true if PHP is new enough and has the zip extension loaded.
	 * @return boolean
	 */
	function zipFunctional() {
		return (checkPhpVersion('5.2.0') && extension_loaded('zip'));
	}
}
?>

<?php

/**
 * @defgroup file_wrapper
 */

/**
 * @file classes/file/FileWrapper.inc.php
 *
 * Copyright (c) 2000-2012 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class FileWrapper
 * @ingroup file
 *
 * @brief Class abstracting operations for reading remote files using various protocols.
 * (for when allow_url_fopen is disabled).
 *
 * TODO:
 *     - Other protocols?
 *     - Write mode (where possible)
 */

// $Id$


class FileWrapper {

	/** @var $url string URL to the file */
	var $url;

	/** @var $info array parsed URL info */
	var $info;

	/** @var $fp int the file descriptor */
	var $fp;

	/**
	 * Constructor.
	 * @param $url string
	 * @param $info array
	 */
	function FileWrapper($url, &$info) {
		$this->url = $url;
		$this->info = $info;
	}

	/**
	 * Read and return the contents of the file (like file_get_contents()).
	 * @return string
	 */
	function contents() {
		$contents = '';
		if ($retval = $this->open()) {
			if (is_object($retval)) { // It may be a redirect
				return $retval->contents();
			}
			while (!$this->eof())
				$contents .= $this->read();
			$this->close();
		}
		return $contents;
	}

	/**
	 * Open the file.
	 * @param $mode string only 'r' (read-only) is currently supported
	 * @return boolean
	 */
	function open($mode = 'r') {
		$this->fp = null;
		$this->fp = fopen($this->url, $mode);
		return ($this->fp !== false);
	}

	/**
	 * Close the file.
	 */
	function close() {
		fclose($this->fp);
		unset($this->fp);
	}

	/**
	 * Read from the file.
	 * @param $len int
	 * @return string
	 */
	function read($len = 8192) {
		return fread($this->fp, $len);
	}

	/**
	 * Check for end-of-file.
	 * @return boolean
	 */
	function eof() {
		return feof($this->fp);
	}


	//
	// Static
	//

	/**
	 * Return instance of a class for reading the specified URL.
	 * @param $url string
	 * @return FileWrapper
	 */
	function &wrapper($url) {
		$info = parse_url($url);
		if (ini_get('allow_url_fopen') && Config::getVar('general', 'allow_url_fopen')) {
			$wrapper = new FileWrapper($url, $info);
		} else {
			switch (@$info['scheme']) {
				case 'http':
					import('file.wrappers.HTTPFileWrapper');
					$wrapper = new HTTPFileWrapper($url, $info);
					$wrapper->addHeader('User-Agent', 'PKP-OJS/2.x');
					break;
				case 'https':
					import('file.wrappers.HTTPSFileWrapper');
					$wrapper = new HTTPSFileWrapper($url, $info);
					$wrapper->addHeader('User-Agent', 'PKP-OJS/2.x');
					break;
				case 'ftp':
					import('file.wrappers.FTPFileWrapper');
					$wrapper = new FTPFileWrapper($url, $info);
					break;
				default:
					$wrapper = new FileWrapper($url, $info);
			}
		}

		return $wrapper;
	}
}

?>

<?php

/**
 * @file classes/file/EditableFile.inc.php
 *
 * Copyright (c) 2013-2015 Simon Fraser University Library
 * Copyright (c) 2000-2015 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class EditableFile
 * @ingroup file
 *
 * @brief Hack-and-slash class to help with editing XML files without losing
 * formatting and comments (i.e. unparsed editing).
 */


class EditableFile {
	var $contents;
	var $filename;

	function exists() {
		return file_exists($this->filename);
	}

	function EditableFile($filename) {
		import('lib.pkp.classes.file.FileWrapper');
		$this->filename = $filename;
		$wrapper =& FileWrapper::wrapper($this->filename);
		$this->setContents($wrapper->contents());
	}

	function &getContents() {
		return $this->contents;
	}

	function setContents(&$contents) {
		$this->contents =& $contents;
	}

	function write() {
		$fp = fopen($this->filename, 'w+');
		if ($fp === false) return false;
		fwrite($fp, $this->getContents());
		fclose($fp);
		return true;
	}

	function xmlEscape($value) {
		$escapedValue = XMLNode::xmlentities($value, ENT_NOQUOTES);
		if ($value !== $escapedValue) return "<![CDATA[$value]]>";
		return $value;
	}
}

?>

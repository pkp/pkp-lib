<?php

/**
 * @file classes/file/EditableEmailFile.inc.php
 *
 * Copyright (c) 2013-2015 Simon Fraser University Library
 * Copyright (c) 2000-2015 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class EditableEmailFile
 * @ingroup file
 *
 * @brief This class supports updating for email XML files.
 *
 */


import('lib.pkp.classes.file.EditableFile');

class EditableEmailFile {
	var $locale;
	var $editableFile;

	function EditableEmailFile($locale, $filename) {
		$this->locale = $locale;
		$this->editableFile = new EditableFile($filename);
	}

	function exists() {
		return $this->editableFile->exists();
	}

	function write() {
		$this->editableFile->write();
	}

	function &getContents() {
		return $this->editableFile->getContents();
	}

	function setContents(&$contents) {
		$this->editableFile->setContents($contents);
	}

	function update($key, $subject, $body, $description) {
		$matches = null;
		$quotedKey = String::regexp_quote($key);
		preg_match(
			"/<email_text[\W]+key=\"$quotedKey\">/",
			$this->getContents(),
			$matches,
			PREG_OFFSET_CAPTURE
		);
		if (!isset($matches[0])) return false;

		$offset = $matches[0][1];
		$closeOffset = strpos($this->getContents(), '</email_text>', $offset);
		if ($closeOffset === FALSE) return false;

		$newContents = substr($this->getContents(), 0, $offset);
		$newContents .= '<email_text key="' . $this->editableFile->xmlEscape($key) . '">
		<subject>' . $this->editableFile->xmlEscape($subject) . '</subject>
		<body>' . $this->editableFile->xmlEscape($body) . '</body>
		<description>' . $this->editableFile->xmlEscape($description) . '</description>
	';
		$newContents .= substr($this->getContents(), $closeOffset);
		$this->setContents($newContents);
		return true;
	}

	function delete($key) {
		$matches = null;
		$quotedKey = String::regexp_quote($key);
		preg_match(
			"/<email_text[\W]+key=\"$quotedKey\">/",
			$this->getContents(),
			$matches,
			PREG_OFFSET_CAPTURE
		);
		if (!isset($matches[0])) return false;
		$offset = $matches[0][1];

		preg_match("/<\/email_text>[ \t]*[\r]?\n/", $this->getContents(), $matches, PREG_OFFSET_CAPTURE, $offset);
		if (!isset($matches[0])) return false;
		$closeOffset = $matches[0][1] + strlen($matches[0][0]);

		$newContents = substr($this->getContents(), 0, $offset);
		$newContents .= substr($this->getContents(), $closeOffset);
		$this->setContents($newContents);
		return true;
	}

	function insert($key, $subject, $body, $description) {
		$offset = strrpos($this->getContents(), '</email_texts>');
		if ($offset === false) return false;
		$newContents = substr($this->getContents(), 0, $offset);
		$newContents .= '	<email_text key="' . $this->editableFile->xmlEscape($key) . '">
		<subject>' . $this->editableFile->xmlEscape($subject) . '</subject>
		<body>' . $this->editableFile->xmlEscape($body) . '</body>
		<description>' . $this->editableFile->xmlEscape($description) . '</description>
	</email_text>
';
		$newContents .= substr($this->getContents(), $offset);
		$this->setContents($newContents);
	}
}

?>

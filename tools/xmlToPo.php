<?php

/**
 * @file tools/xmlToPo.php
 *
 * Copyright (c) 2014-2020 Simon Fraser University
 * Copyright (c) 2003-2020 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class xmlToPo
 * @ingroup tools
 */

require(dirname(dirname(dirname(dirname(__FILE__)))) . '/tools/bootstrap.inc.php');

class xmlToPo extends CommandLineTool {
	/** @var string Name of source file/directory */
	protected $source;

	/** @var string Name of target file/directory */
	protected $target;

	/**
	 * Constructor
	 */
	function __construct($argv = array()) {
		parent::__construct($argv);

		array_shift($argv); // Shift the tool name off the top

		$this->source = array_shift($argv);
		$this->target = array_shift($argv);

		// The source file/directory must be specified and exist.
		if (empty($this->source) || !file_exists($this->source)) {
			$this->usage();
			exit(2);
		}

		// The target file must be specified, unless we're converting an entire directory.
		if (empty($this->target)) {
			if (is_file($this->source)) {
				$this->usage();
				exit(3);
			} else {
				// If we're converting a directory and no target was specified, we use the source dir.
				$this->target = $this->source;
			}
		}

		// If the target file/directory exists, it must be like the source file/directory.
		if (file_exists($this->target) && (is_dir($this->source) != is_dir($this->target))) {
			$this->usage();
			exit(4);
		}
	}

	/**
	 * Print command usage information.
	 */
	function usage() {
		echo "Script to convert XML locale file to PO format\n\n"
			. "Usage: {$this->scriptName} input-locale-file.xml output-file.po\n\n"
			. "or, to convert all locale files in a specified directory:\n\n"
			. "{$this->scriptName} input-path [output-path]\n\n"
			. "When specifying a path, the output path is optional. If it is not specified, the input path will be used.\n";
	}

	/**
	 * Parse a locale file into an array.
	 * @param $filename string Filename to locale file
	 * @return array? (key => message)
	 */
	static function parseLocaleFile($filename) {
		$localeData = null;
		$xmlDao = new XMLDAO();
		$data = $xmlDao->parseStruct($filename, array('message'));

		// Build array with ($key => $string)
		if (isset($data['message'])) {
			foreach ($data['message'] as $messageData) {
				$localeData[$messageData['attributes']['key']] = $messageData['value'];
			}
		}

		return $localeData;
	}

	/**
	 * Convert a file from XML to PO.
	 * @param $source Source filename
	 * @param $target Target filename
	 */
	static function convertFile($source, $target) {
		$localeData = array();

		$sourceData = self::parseLocaleFile($source);
		if (!$sourceData) throw new Exception('Unable to load source file ' . $source);

		$translations = new \Gettext\Translations();
		foreach ($sourceData as $key => $sourceTranslation) {
			$translation = new \Gettext\Translation('', $key);
			$translation->setTranslation("$sourceTranslation");
			$translations->append($translation);
		}

		return $translations->toPoFile($target);
	}

	/**
	 * Convert XML locale content to PO format.
	 */
	function execute() {
		if (is_dir($this->source)) {
			// The caller specified a directory of files.
			import('lib.pkp.classes.file.FileManager');
			$fileManager = new FileManager();

			// Look recursively for files to convert.
			$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($this->source));
			foreach ($iterator as $file) {
				if ($file->isDir()) continue;
				$pathname = $file->getPathname();
				if (strrchr($pathname, '.') != '.xml') continue;

				// If we can't glean any data from the file, skip it.
				if (!self::parseLocaleFile($pathname)) continue;

				// This seems to be a locale file. Try to convert it.
				if (substr($pathname, 0, strlen($this->source)) !== $this->source) continue;
				$targetPath = $this->target . dirname(substr($pathname, strlen($this->source)));
				$targetFile = $targetPath . '/' . basename($pathname, '.xml') . '.po';

				// Ensure the target directory exists.
				$fileManager->mkdirtree($targetPath);

				echo "$pathname => $targetFile\n";
				self::convertFile($pathname, $targetFile);
			}
		} else {
			// Convert just a single file, as specified.
			self::convertFile($this->source, $this->target);
		}
	}
}

$tool = new xmlToPo(isset($argv) ? $argv : array());
$tool->execute();


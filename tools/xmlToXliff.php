<?php

/**
 * @file tools/poToCountries.php
 *
 * Copyright (c) 2014-2019 Simon Fraser University
 * Copyright (c) 2003-2019 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class poToCountries
 * @ingroup tools
 *
 * @brief CLI tool to convert a .PO file for ISO3166 into the countries.xml format
 * supported by the PKP suite. These .po files can be sourced from e.g.:
 * https://packages.debian.org/source/sid/iso-codes
 */

require(dirname(dirname(dirname(dirname(__FILE__)))) . '/tools/bootstrap.inc.php');

class poToCountries extends CommandLineTool {
	/** @var string Name of source XML locale file */
	protected $xmlFile;

	/** @var string Name of target XLIFF file */
	protected $xliffFile;

	/**
	 * Constructor
	 */
	function __construct($argv = array()) {
		parent::__construct($argv);

		array_shift($argv); // Shift the tool name off the top

		$this->xmlFile = array_shift($argv);
		$this->xliffFile = array_shift($argv);

		if (empty($this->xmlFile) || !file_exists($this->xmlFile)) {
			$this->usage();
			exit(1);
		}

		if (empty($this->xliffFile)) {
			$this->usage();
			exit(2);
		}
	}

	/**
	 * Print command usage information.
	 */
	function usage() {
		echo "Script to convert XML locale file to XLIFF format\n"
			. "Usage: {$this->scriptName} path/to/input-locale-file.xml path/to/output-xliff-file.xliff\n";
	}

	/**
	 * Rebuild the search index for all articles in all journals.
	 */
	function execute() {
		$localeData = array();
		$xmlDao = new XMLDAO();
		$data = $xmlDao->parseStruct($this->xmlFile, array('message'));

		// Build array with ($key => $string)
		if (isset($data['message'])) {
			foreach ($data['message'] as $messageData) {
				$localeData[$messageData['attributes']['key']] = $messageData['value'];
			}
		}

		$translations = new \Gettext\Translations();
		foreach ($localeData as $key => $translation) {
			$translationObject = new \Gettext\Translation('', $key);
			$translationObject->setTranslation($translation);
			$translations->append($translationObject);
		}

		$translations->toXliffFile($this->xliffFile);
	}
}

$tool = new poToCountries(isset($argv) ? $argv : array());
$tool->execute();



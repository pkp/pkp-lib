<?php

/**
 * @file tools/xmlToXLiff.php
 *
 * Copyright (c) 2014-2019 Simon Fraser University
 * Copyright (c) 2003-2019 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class xmlToXLiff
 * @ingroup tools
 *
 * @brief CLI tool to convert a .PO file for ISO3166 into the countries.xml format
 * supported by the PKP suite. These .po files can be sourced from e.g.:
 * https://packages.debian.org/source/sid/iso-codes
 */

require(dirname(dirname(dirname(dirname(__FILE__)))) . '/tools/bootstrap.inc.php');

class xmlToXLiff extends CommandLineTool {
	/** @var string Name of reference XML locale file */
	protected $referenceXmlFile;

	/** @var string Name of source XML locale file */
	protected $sourceXmlFile;

	/** @var string Name of target XLIFF file */
	protected $xliffFile;

	/**
	 * Constructor
	 */
	function __construct($argv = array()) {
		parent::__construct($argv);

		array_shift($argv); // Shift the tool name off the top

		$this->referenceXmlFile = array_shift($argv);
		$this->sourceXmlFile = array_shift($argv);
		$this->xliffFile = array_shift($argv);

		if (empty($this->referenceXmlFile) || !file_exists($this->referenceXmlFile)) {
			$this->usage();
			exit(1);
		}

		if (empty($this->sourceXmlFile) || !file_exists($this->sourceXmlFile)) {
			$this->usage();
			exit(2);
		}

		if (empty($this->xliffFile)) {
			$this->usage();
			exit(3);
		}
	}

	/**
	 * Print command usage information.
	 */
	function usage() {
		echo "Script to convert XML locale file to XLIFF format\n"
			. "Usage: {$this->scriptName} source-locale-file.xml input-locale-file.xml output-xliff-file.xliff\n";
	}

	/**
	 * Parse a locale file into an array.
	 * @param $filename string Filename to locale file
	 * @return array (key => message)
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
	 * Rebuild the search index for all articles in all journals.
	 */
	function execute() {
		$localeData = array();

		$referenceData = self::parseLocaleFile($this->referenceXmlFile);
		$sourceData = self::parseLocaleFile($this->sourceXmlFile);

		$translations = new \Gettext\Translations();
		foreach ($referenceData as $key => $referenceTranslation) {
			$translation = new \Gettext\XliffTranslation('', $referenceTranslation);
			// Translate '.' into '-' (. is not allowed in XLIFF unit IDs)
			$translation->setUnitId(str_replace('.', '-', $key));
			@$translation->setTranslation($sourceData[$key]);
			$translations->append($translation);
		}

		$translations->toXliffFile($this->xliffFile);
	}
}

$tool = new xmlToXLiff(isset($argv) ? $argv : array());
$tool->execute();



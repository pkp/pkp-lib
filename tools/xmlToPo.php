<?php

/**
 * @file tools/xmlToPo.php
 *
 * Copyright (c) 2014-2019 Simon Fraser University
 * Copyright (c) 2003-2019 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class xmlToPo
 * @ingroup tools
 */

require(dirname(dirname(dirname(dirname(__FILE__)))) . '/tools/bootstrap.inc.php');

class xmlToPo extends CommandLineTool {
	/** @var string Name of source XML locale file */
	protected $sourceXmlFile;

	/** @var string Name of target PO file */
	protected $poFile;

	/**
	 * Constructor
	 */
	function __construct($argv = array()) {
		parent::__construct($argv);

		array_shift($argv); // Shift the tool name off the top

		$this->sourceXmlFile = array_shift($argv);
		$this->poFile = array_shift($argv);

		if (empty($this->sourceXmlFile) || !file_exists($this->sourceXmlFile)) {
			$this->usage();
			exit(2);
		}

		if (empty($this->poFile)) {
			$this->usage();
			exit(3);
		}
	}

	/**
	 * Print command usage information.
	 */
	function usage() {
		echo "Script to convert XML locale file to PO format\n"
			. "Usage: {$this->scriptName} input-locale-file.xml output-file.po\n";
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
	 * Convert an XML locale file to a PO file.
	 */
	function execute() {
		$localeData = array();

		$sourceData = self::parseLocaleFile($this->sourceXmlFile);
		if (!$sourceData) throw new Exception('Unable to load source file ' . $this->sourceXmlFile);

		$translations = new \Gettext\Translations();
		foreach ($sourceData as $key => $sourceTranslation) {
			$translation = new \Gettext\Translation('', $key);
			$translation->setTranslation("$sourceTranslation");
			$translations->append($translation);
		}

		$translations->toPoFile($this->poFile);
	}
}

$tool = new xmlToPo(isset($argv) ? $argv : array());
$tool->execute();



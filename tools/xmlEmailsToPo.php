<?php

/**
 * @file tools/xmlEmailsToPo.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class xmlEmailsToPo
 * @ingroup tools
 */

require(dirname(dirname(dirname(dirname(__FILE__)))) . '/tools/bootstrap.inc.php');

class xmlEmailsToPo extends CommandLineTool {
	/** @var string Name of source XML email file */
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
		echo "Script to convert XML email file to PO format\n"
			. "Usage: {$this->scriptName} input-locale-file.xml output-file.po\n";
	}

	/**
	 * Parse a locale file into an array.
	 * @param $filename string Filename to locale file
	 * @return array (key => message)
	 */
	static function parseEmailFile($filename, &$locale) {
		$localeData = array();
		$xmlDao = new XMLDAO();
		$data = $xmlDao->parse($filename, array('email_texts', 'email_text', 'subject', 'body', 'description'));
		if (!$data) return null;

		$locale = $data->getAttribute('locale');

		foreach ($data->getChildren() as $emailNode) {
			$key = $emailNode->getAttribute('key');

			$subject = $emailNode->getChildValue('subject');
			$body = $emailNode->getChildValue('body');
			$description = $emailNode->getChildValue('description');

			$localeData[$key] = array(
				'subject' => $emailNode->getChildValue('subject'),
				'body' => $emailNode->getChildValue('body'),
				'description' => $emailNode->getChildValue('description'),
			);
		}

		return $localeData;
	}

	/**
	 * Convert an XML locale file to a PO file.
	 */
	function execute() {
		$localeData = array();
		$locale = null;

		$sourceData = self::parseEmailFile($this->sourceXmlFile, $locale);
		if (!$sourceData) throw new Exception('Unable to load source file ' . $this->sourceXmlFile);

		import('lib.pkp.classes.file.EditableEmailFile');
		$editableEmailFile = new EditableEmailFile($locale, $this->sourceXmlFile);

		$translations = new \Gettext\Translations();
		foreach ($sourceData as $emailKey => $sourceEmailData) {
			// Convert EMAIL_KEY_NAME to emailKeyName
			$camelEmailKey = str_replace(' ', '', ucwords(str_replace('_', ' ', strtolower($emailKey))));
			$camelEmailKey[0] = strtolower($camelEmailKey[0]);

			foreach (array('subject', 'body', 'description') as $elementName) {
				$elementValue = $sourceEmailData[$elementName];
				$key = "emails.$camelEmailKey.$elementName";

				$translation = new \Gettext\Translation('', $key);
				$translation->setTranslation($elementValue);
				$translations->append($translation);
			}

			$editableEmailFile->update(
				$emailKey,
				"{translate key=\"emails.$camelEmailKey.subject\"}",
				"{translate key=\"emails.$camelEmailKey.body\"}",
				"{translate key=\"emails.$camelEmailKey.description\"}"
			);
		}
		$editableEmailFile->write();

		$translations->toPoFile($this->poFile);
	}
}

$tool = new xmlEmailsToPo(isset($argv) ? $argv : array());
$tool->execute();


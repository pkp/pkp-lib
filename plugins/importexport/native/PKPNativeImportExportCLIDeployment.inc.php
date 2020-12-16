<?php

/**
 * @file plugins/importexport/native/PKPNativeImportExportCLIDeployment.inc.php
 *
 * Copyright (c) 2014-2020 Simon Fraser University
 * Copyright (c) 2003-2020 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PKPNativeImportExportCLIDeployment
 * @ingroup plugins_importexport_native
 *
 * @brief CLI Deployment for Import/Export operations
 */

class PKPNativeImportExportCLIDeployment {

	/**
	 * The import/export script name
	 * @var string
	 */
	var $scriptName;

	/**
	 * The import/export arguments
	 * @var array
	 */
	var $args;

	/**
	 * The import/export additional directives
	 * @var array
	 */
	public $opts;

	/**
	 * The import/export command
	 * @var string
	 */
	public $command;

	/**
	 * The import/export xml file name
	 * @var string
	 */
	public $xmlFile;

	/**
	 * The import/export operation context path
	 * @var string
	 */
	public $contextPath;

	/**
	 * The import/export operation user name
	 * @var string
	 */
	public $userName;

	/**
	 * The export entity
	 * @var string
	 */
	public $exportEntity;

	/**
	 * Any remaining arguments that have not being processed
	 * @var array
	 */
	public $remainingArgs;

	function __construct($scriptName, $args) {
		$this->scriptName = $scriptName;
		$this->args = $args;

		$this->parseCLI();
	}

	/**
	 * Parse CLI Command to populate the Deployment's variables
	 */
	function parseCLI() {
		$this->opts = $this->parseOpts($this->args, ['no-embed', 'use-file-urls']);
		$this->command = array_shift($this->args);
		$this->xmlFile = array_shift($this->args);
		$this->contextPath = array_shift($this->args);

		switch ($this->command) {
			case 'import':
				$this->userName = array_shift($this->args);
				break;
			case 'export':
				$this->exportEntity = array_shift($this->args);
				break;
		}

		$this->remainingArgs = $this->args;
	}

	/**
	 * Pull out getopt style long options.
	 * WARNING: This method is checked for by name in DepositPackage in the PLN plugin
	 * to determine if options are supported!
	 * @param &$args array
	 * #param $optCodes array
	 */
	function parseOpts(&$args, $optCodes) {
		$newArgs = [];
		$opts = [];
		$sticky = null;
		foreach ($args as $arg) {
			if ($sticky) {
				$opts[$sticky] = $arg;
				$sticky = null;
				continue;
			}
			if (substr($arg, 0, 2) != '--') {
				$newArgs[] = $arg;
				continue;
			}
			$opt = substr($arg, 2);
			if (in_array($opt, $optCodes)) {
				$opts[$opt] = true;
				continue;
			}
			if (in_array($opt . ":", $optCodes)) {
				$sticky = $opt;
				continue;
			}
		}
		$args = $newArgs;
		return $opts;
	}
}

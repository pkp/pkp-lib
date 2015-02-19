<?php

/**
 * @file tools/buildCss.php
 *
 * Copyright (c) 2013-2015 Simon Fraser University Library
 * Copyright (c) 2000-2015 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class buildCss
 * @ingroup tools
 *
 * @brief CLI tool for processing CSS into a single compiled file using Less for PHP.
 */


require(dirname(dirname(dirname(dirname(__FILE__)))) . '/tools/bootstrap.inc.php');

define('APPLICATION_STYLES_DIR', 'styles');
define('APPLICATION_LESS_WRAPPER', 'index.less');
define('APPLICATION_CSS_WRAPPER', 'compiled.css');

class buildCss extends CommandLineTool {
	/** @var $force boolean true to force recompilation */
	var $force;

	/**
	 * Constructor.
	 * @param $argv array command-line arguments
	 */
	function buildCss($argv = array()) {
		parent::CommandLineTool($argv);

		array_shift($argv); // Flush the tool name from the argv list

		$this->force = false;

		while ($option = array_shift($argv)) switch ($option) {
			case 'force':
				$this->force = true;
				break;
			default:
				$this->usage();
				exit(-1);
		}
	}

	/**
	 * Print command usage information.
	 */
	function usage() {
		echo "CSS Compilation tool\n"
			. "Use this tool to compile CSS into a single file.\n\n"
			. "Usage: {$this->scriptName}\n";
	}

	/**
	 * Execute the build CSS command.
	 */
	function execute() {
		// Load the LESS compiler class.
		require_once('lib/pkp/lib/lessphp/lessc.inc.php');

		// Flush if necessary
		if ($this->force) unlink(APPLICATION_STYLES_DIR . '/' . APPLICATION_CSS_WRAPPER);

		// Perform the compile.
		try {
			// KLUDGE pending fix of https://github.com/leafo/lessphp/issues#issue/66
			// Once this issue is fixed, revisit paths and go back to using
			// lessc::ccompile to parse & compile.
			$less = new lessc(APPLICATION_STYLES_DIR . '/' . APPLICATION_LESS_WRAPPER);
			$less->importDir = './';
			file_put_contents(
				APPLICATION_STYLES_DIR . '/' . APPLICATION_CSS_WRAPPER,
				$less->parse()
			);
		} catch (exception $ex) {
			echo "ERROR: " . $ex->getMessage() . "\n";
			exit(-1);
		}
		exit(0);
	}
}

$tool = new buildCss(isset($argv) ? $argv : array());
$tool->execute();
?>

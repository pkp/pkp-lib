<?php

/**
 * @file tools/buildCss.php
 *
 * Copyright (c) 2000-2013 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class buildCss
 * @ingroup tools
 *
 * @brief CLI tool for processing CSS into a single compiled file using Less for PHP.
 */

require(dirname(dirname(dirname(dirname(__FILE__)))) . '/tools/bootstrap.inc.php');

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
			. "Usage: {$this->scriptName} [force]\n";
	}

	/**
	 * Execute the build CSS command.
	 */
	function execute() {
		// Load the LESS compiler class.
		require_once('lib/pkp/lib/lessphp/lessc.inc.php');

		try {
			TemplateManager::compileStylesheet($this->force);
		} catch (exception $ex) {
			echo 'ERROR: ' . $ex->getMessage() . "\n";
			exit(-1);
		}
		exit(0);
	}
}

$tool = new buildCss(isset($argv) ? $argv : array());
$tool->execute();
?>

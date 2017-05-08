<?php

/**
 * @file tools/installPluginVersionTool.php
 *
 * Copyright (c) 2014-2017 Simon Fraser University
 * Copyright (c) 2003-2017 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class InstallPluginVersionTool
 * @ingroup tools
 *
 * @brief CLI tool for installing a plugin version descriptor.
 */

define('RUNNING_UPGRADE', 1);

require(dirname(dirname(dirname(dirname(__FILE__)))) . '/tools/bootstrap.inc.php');

import('lib.pkp.classes.site.Version');
import('lib.pkp.classes.site.VersionCheck');

class InstallPluginVersionTool extends CommandLineTool {
	/** @var string Path to descriptor file to install */
	private $_descriptor;

	/**
	 * Constructor.
	 * @param $argv array command-line arguments
	 */
	function __construct($argv = array()) {
		parent::__construct($argv);

		if (!isset($this->argv[0]) || !file_exists($this->argv[0])) {
			$this->usage();
			exit(1);
		}

		$this->_descriptor = $this->argv[0];
	}

	/**
	 * Print command usage information.
	 */
	function usage() {
		echo "Install plugin version tool\n"
			. "Usage: {$this->scriptName} path/to/version.xml\n";
	}

	/**
	 * Execute the specified command.
	 */
	function execute() {
		$versionInfo = VersionCheck::parseVersionXML($this->_descriptor);
		$pluginVersion = $versionInfo['version'];
		$versionDao = DAORegistry::getDAO('VersionDAO');
		$versionDao->insertVersion($pluginVersion, true);
	}
}

$tool = new InstallPluginVersionTool(isset($argv) ? $argv : array());
$tool->execute();

?>

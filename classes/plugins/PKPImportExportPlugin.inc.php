<?php

/**
 * @file classes/plugins/PKPImportExportPlugin.inc.php
 *
 * Copyright (c) 2014 Simon Fraser University Library
 * Copyright (c) 2003-2014 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class ImportExportPlugin
 * @ingroup plugins
 *
 * @brief Abstract class for import/export plugins
 */

import('lib.pkp.classes.plugins.Plugin');

abstract class PKPImportExportPlugin extends Plugin {
	/**
	 * Constructor
	 */
	function PKPImportExportPlugin() {
		parent::Plugin();
	}

	/**
	 * Display the import/export plugin UI.
	 * @param $args array The array of arguments the user supplied.
	 * @param $request Request
	 */
	function display($args, $request) {
		$templateManager = TemplateManager::getManager($request);
		$templateManager->register_function('plugin_url', array($this, 'smartyPluginUrl'));
	}

	/**
	 * Execute import/export tasks using the command-line interface.
	 * @param $scriptName The name of the command-line script (displayed as usage info)
	 * @param $args Parameters to the plugin
	 */
	abstract function executeCLI($scriptName, &$args);

	/**
	 * Display the command-line usage information
	 * @param $scriptName string
	 */
	abstract function usage($scriptName);

	/**
	 * Display verbs for the management interface.
	 * @return array
	 */
	function getManagementVerbs() {
		return array(
			array(
				'importexport',
				__('manager.importExport')
			)
		);
	}
}

?>

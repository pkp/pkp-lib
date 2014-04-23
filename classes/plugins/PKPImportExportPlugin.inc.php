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
	 * Get the name of this plugin. The name must be unique within
	 * its category.
	 * @return String name of plugin
	 */
	abstract function getName();

	/**
	 * Get the display name of this plugin. This name is displayed on the
	 * Journal Manager's import/export page, for example.
	 * @return String
	 */
	abstract function getDisplayName();

	/**
	 * Get a description of the plugin.
	 * @return string
	 */
	abstract function getDescription();

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

 	/**
	 * @see Plugin::manage()
	 */
	abstract function manage($verb, $args, &$message, &$messageParams, &$pluginModalContent = null);

	/**
	 * Extend the {url ...} smarty to support import/export plugins.
	 * @param $params array
	 * @param $smarty Smarty
	 */
	abstract function smartyPluginUrl($params, $smarty);
}

?>

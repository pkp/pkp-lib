<?php

/**
 * @file classes/plugins/ImportExportPlugin.inc.php
 *
 * Copyright (c) 2014-2016 Simon Fraser University Library
 * Copyright (c) 2003-2016 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class ImportExportPlugin
 * @ingroup plugins
 *
 * @brief Abstract class for import/export plugins
 */

import('lib.pkp.classes.plugins.Plugin');

abstract class ImportExportPlugin extends Plugin {
	/** @var Request Request made available for plugin URL generation */
	var $_request;

	/**
	 * Constructor
	 */
	function ImportExportPlugin() {
		parent::Plugin();
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
	 * @copydoc Plugin::getActions()
	 */
	function getActions($request, $actionArgs) {
		$dispatcher = $request->getDispatcher();
		import('lib.pkp.classes.linkAction.request.RedirectAction');
		return array_merge(
			array(
				new LinkAction(
					'settings',
					new RedirectAction($dispatcher->url(
						$request, ROUTE_PAGE,
						null, 'management', 'importexport', array('plugin', $this->getName())
					)),
					__('manager.importExport'),
					null
				),
			),
			parent::getActions($request, $actionArgs)
		);
	}

	/**
	 * Display the import/export plugin.
	 * @param $args array
	 * @param $request PKPRequest
	 */
	function display($args, $request) {
		$templateMgr = TemplateManager::getManager($request);
		$templateMgr->register_function(
			'plugin_url',
			array($this, 'pluginUrl')
		);
		$this->_request = $request; // Store this for use by the pluginUrl function
	}

	/**
	 * Generate a URL into the plugin.
	 * @see calling conventions at http://www.smarty.net/docsv2/en/api.register.function.tpl
	 * @param $params array
	 * @param $smarty Smarty
	 * @return string
	 */
	function pluginUrl($params, &$smarty) {
		$dispatcher = $this->_request->getDispatcher();
		return $dispatcher->url($this->_request, ROUTE_PAGE, null, 'management', 'importexport', array_merge(array('plugin', $this->getName(), isset($params['path'])?$params['path']:array())));
	}
}

?>

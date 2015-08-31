<?php

/**
 * @file classes/plugins/ImportExportPlugin.inc.php
 *
 * Copyright (c) 2014-2015 Simon Fraser University Library
 * Copyright (c) 2003-2015 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class ImportExportPlugin
 * @ingroup plugins
 *
 * @brief Abstract class for import/export plugins
 */

import('lib.pkp.classes.plugins.Plugin');

abstract class ImportExportPlugin extends Plugin {
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
					__('manager.importexport'),
					null
				),
			),
			parent::getActions($request, $actionArgs)
		);
	}
}

?>

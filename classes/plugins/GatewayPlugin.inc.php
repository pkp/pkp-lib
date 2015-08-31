<?php

/**
 * @file classes/plugins/GatewayPlugin.inc.php
 *
 * Copyright (c) 2014-2015 Simon Fraser University Library
 * Copyright (c) 2003-2015 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class GatewayPlugin
 * @ingroup plugins
 *
 * @brief Abstract class for gateway plugins
 */

import('lib.pkp.classes.plugins.Plugin');

abstract class GatewayPlugin extends Plugin {
	/**
	 * Constructor
	 */
	function GatewayPlugin() {
		parent::Plugin();
	}

	/**
	 * Handle fetch requests for this plugin.
	 * @param $args array
	 * @param $request object
	 */
	abstract function fetch($args, $request);
}

?>

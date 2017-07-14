<?php

/**
 * @file plugins/generic/shibboleth/pages/ShibbolethHandler.inc.php
 *
 * Copyright (c) 2014-2017 Simon Fraser University
 * Copyright (c) 2003-2017 John Willinsky
 * Distributed under the GNU GPL v2 or later. For full terms see the file docs/COPYING.
 *
 * @class ShibbolethHandler
 * @ingroup plugins_generic_shibboleth
 *
 * @brief Handle Shibboleth responses
 */

import('classes.handler.Handler');

class ShibbolethHandler extends Handler {
	/**
	 * Login handler
	 * @param $args array
	 * @param $request Request
	 */
	function shibLogin($args, $request) {
		$plugin = $this->_getPlugin();
		$uin_header = $plugin->getSetting($contextId, 'shibbolethHeaderUin');

		// We rely on this header being present.
		if (!isset($_SERVER[$uin_header])) {
			syslog(LOG_ERR, "Shibboleth plugin enabled, but not properly configured; failed to find $uin_header.");
			Validation::logout();
			Validation::redirectLogin();
		}

		// ... and being set.
		$uin = $_SERVER[$uin_header];
		if ($uin == null) {
			Validation::logout();
			Validation::redirectLogin();
		}
	}

	//
	// Private helper methods
	//
	/**
	 * Get the Shibboleth plugin object
	 * @return ShibbolethAuthPlugin
	 */
	function &_getPlugin() {
		$plugin =& PluginRegistry::getPlugin('generic', SHIBBOLETH_PLUGIN_NAME);
		return $plugin;
	}
}
?>

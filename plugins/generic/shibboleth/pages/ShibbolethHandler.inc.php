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
		$contextId = $plugin->getCurrentContextId();
		$uin_header = $plugin->getSetting($contextId, 'shibbolethHeaderUin');
		$email_header = $plugin->getSetting($contextId, 'shibbolethHeaderEmail');

		// We rely on these headers being present.
		if (!isset($_SERVER[$uin_header])) {
			syslog(LOG_ERR, "Shibboleth plugin enabled, but not properly configured; failed to find $uin_header.");
			Validation::logout();
			Validation::redirectLogin();
		}
		if (!isset($_SERVER[$email_header])) {
			syslog(LOG_ERR, "Shibboleth plugin enabled, but not properly configured; failed to find $email_header.");
			Validation::logout();
			Validation::redirectLogin();
		}

		// The UIN must be set; otherwise login failed.
		$uin = $_SERVER[$uin_header];
		if ($uin == null) {
			Validation::logout();
			Validation::redirectLogin();
		}

		// Try to locate the user by UIN.
		$userDao =& DAORegistry::getDAO('UserDAO');
		$user =& $userDao->getUserByAuthStr($uin, true);
		if (isset($user)) {
			syslog(LOG_INFO, "Shibboleth located returning user $uin.");
			// @@@ TODO do the login
		}

		// We use the e-mail as a key.
		$user_email = $_SERVER[$email_header];
		if (!isset($user)) {
			$user =& $userDao->getUserByEmail($email);

			if (isset($user)) {
				syslog(LOG_INFO, "Shibboleth located returning email $email.");
			// @@@ TODO do the login
			}
		}

		// @@@ TODO admin stuff
		return true;
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

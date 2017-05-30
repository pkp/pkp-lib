<?php

/**
 * @file plugins/auth/shibboleth/ShibbolethAuthPlugin.inc.php
 *
 * Copyright (c) 2014-2017 Simon Fraser University
 * Copyright (c) 2003-2017 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class ShibbolethAuthPlugin
 * @ingroup plugins_auth_shibboleth
 *
 * @brief Shibboleth authentication plugin.
 */

import('lib.pkp.classes.plugins.AuthPlugin');

class ShibbolethAuthPlugin extends AuthPlugin {
	/**
	 * Called as a plugin is registered to the registry
	 * @param $category String Name of category plugin was registered to
	 * @return boolean True iff plugin initialized successfully; if false,
	 * the plugin will not be registered.
	 */
	function register($category, $path) {
		$success = parent::register($category, $path);
		$this->addLocaleData();
		return $success;
	}

	/**
	 * Return the name of this plugin.
	 * @return string
	 */
	function getName() {
		return 'ShibbolethAuthPlugin';
	}

	/**
	 * Return the localized name of this plugin.
	 * @return string
	 */
	function getDisplayName() {
		return __('plugins.auth.shibboleth.displayName');
	}

	/**
	 * Return the localized description of this plugin.
	 * @return string
	 */
	function getDescription() {
		return __('plugins.auth.shibboleth.description');
	}

	//
	// Core Plugin Functions
	// (Must be implemented by every authentication plugin)
	//

	/**
	 * Returns an instance of the authentication plugin
	 * @param $settings array settings specific to this instance.
	 * @param $authId int identifier for this instance
	 * @return ShibbolethAuthPlugin
	 */
	function getInstance($settings, $authId) {
		return new ShibbolethAuthPlugin($settings, $authId);
	}
}

?>

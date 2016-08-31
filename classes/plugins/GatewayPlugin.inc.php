<?php

/**
 * @file classes/plugins/GatewayPlugin.inc.php
 *
 * Copyright (c) 2014-2016 Simon Fraser University Library
 * Copyright (c) 2003-2016 John Willinsky
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

	/**
	 * Determine whether the plugin can be enabled.
	 * @return boolean
	 */
	function getCanEnable() {
		return true;
	}

	/**
	 * Determine whether the plugin can be disabled.
	 * @return boolean
	 */
	function getCanDisable() {
		return true;
	}

	/**
	 * Determine whether or not this plugin is currently enabled.
	 * @return boolean
	 */
	function getEnabled() {
		return $this->getContextSpecificSetting($this->getSettingMainContext(), 'enabled');
	}

	/**
	 * Set whether or not this plugin is currently enabled.
	 * @param $enabled boolean
	 */
	function setEnabled($enabled) {
		$this->updateContextSpecificSetting($this->getSettingMainContext(), 'enabled', $enabled, 'bool');
	}
}

?>

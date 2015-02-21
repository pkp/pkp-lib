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
	 * Display verbs for the management interface.
	 * @return array Management verbs.
	 */
	function getManagementVerbs() {
		$verbs = array();
		if ($this->getEnabled()) {
			$verbs[] = array(
				'disable',
				__('manager.plugins.disable')
			);
		} else {
			$verbs[] = array(
				'enable',
				__('manager.plugins.enable')
			);
		}
		return $verbs;
	}

	/**
	 * Determine whether or not this plugin is enabled.
	 * @return boolean True indicates enabled
	 */
	function getEnabled() {
		$request = $this->getRequest();
		$context = $request->getContext();
		if (!$context) return false;
		return $this->getSetting($context->getId(), 'enabled');
	}

	/**
	 * Set the enabled/disabled state of this plugin
	 * @param $enabled boolean Whether to enable the plugin or disable it.
	 */
	function setEnabled($enabled) {
		$request = $this->getRequest();
		$context = $request->getContext();
		if ($context) {
			$this->updateSetting(
				$context->getId(),
				'enabled',
				$enabled?true:false
			);
			return true;
		}
		return false;
	}

 	/**
	 * @copydoc Plugin::manage()
	 */
	function manage($verb, $args, &$message, &$messageParams, &$pluginModalContent = null) {
		$templateManager = TemplateManager::getManager($this->getRequest());
		$templateManager->register_function('plugin_url', array($this, 'smartyPluginUrl'));
		switch ($verb) {
			case 'enable':
				$this->setEnabled(true);
				$message = NOTIFICATION_TYPE_PLUGIN_ENABLED;
				$messageParams = array('pluginName' => $this->getDisplayName());
				return false;
			case 'disable':
				$this->setEnabled(false);
				$message = NOTIFICATION_TYPE_PLUGIN_DISABLED;
				$messageParams = array('pluginName' => $this->getDisplayName());
				return false;
		}
		return false;
	}

	/**
	 * Handle fetch requests for this plugin.
	 * @param $args array
	 * @param $request object
	 */
	abstract function fetch($args, $request);
}

?>

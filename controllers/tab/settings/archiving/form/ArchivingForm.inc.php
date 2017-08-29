<?php

/**
 * @file controllers/tab/settings/archiving/form/ArchivingForm.inc.php
 *
 * Copyright (c) 2014-2017 Simon Fraser University
 * Copyright (c) 2003-2017 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class ArchivingForm
 * @ingroup controllers_tab_settings_archiving_form
 *
 * @brief Form to edit archiving information.
 */

import('lib.pkp.classes.controllers.tab.settings.form.ContextSettingsForm');

class ArchivingForm extends ContextSettingsForm {

	/**
	 * Constructor.
	 */
	function __construct($wizardMode = false) {
		$settings = array(
			'enableLockss' => 'bool',
			'enableClockss' => 'bool',
			'enablePln' => 'bool',
		);

		parent::__construct($settings, 'controllers/tab/settings/archiving/form/archivingForm.tpl', $wizardMode);
	}

	/**
	 * @copydoc Form::fetch()
	 */
	function fetch($request, $params = null) {
		$isPLNPluginInstalled = false;
		$isPLNPluginEnabled = false;

		$context = $request->getContext();

		// Get if PLNPlugin is installed
		$versionDao = DAORegistry::getDAO('VersionDAO');
		$currentPLNVersion = $versionDao->getCurrentVersion("plugins.generic", "pln", true);
		if (isset($currentPLNVersion)) {
			$isPLNPluginInstalled = true;
		}

		// Get if PLNPlugin is enabled
		$pluginSettingsDao = DAORegistry::getDAO('PluginSettingsDAO');
		if ($pluginSettingsDao->settingExists($context->getId(), 'plnplugin', 'enabled')) {
			$isPLNPluginEnabled = $pluginSettingsDao->getSetting($context->getId(), 'plnplugin', 'enabled');
		}

		$params = array(
			'isPLNPluginInstalled' => $isPLNPluginInstalled,
			'isPLNPluginEnabled' => $isPLNPluginEnabled
		);

		return parent::fetch($request, $params);
	}

	//
	// Implement template methods from Form.
	//
	/**
	 * @copydoc Form::getLocaleFieldNames()
	 */
	function getLocaleFieldNames() {
		return array('lockssLicense', 'clockssLicense');
	}

	/**
	 * @see Form::execute()
	 * @param $request PKPRequest
	 */
	function execute($request) {
		parent::execute($request);

		$versionDao = DAORegistry::getDAO('VersionDAO');
		$product = $versionDao->getCurrentVersion('plugins.generic', 'pln', true);
		$categoryDir = PLUGINS_PREFIX . 'generic';
		if (isset($product)) {
			$file = $product->getProduct();

			$plugin =& PluginRegistry::_instantiatePlugin('generic', $categoryDir, $file, $product->getProductClassname());
			if ($plugin && is_object($plugin)) {
				if (isset($this->_data['enablePln'])) {
					if (!$plugin->getEnabled()) {
						if ($plugin->getCanEnable()) {
							$plugin->setEnabled(true);
							$user = $request->getUser();
							$notificationManager = new NotificationManager();
							$notificationManager->createTrivialNotification($user->getId(), NOTIFICATION_TYPE_PLUGIN_ENABLED, array('pluginName' => $plugin->getDisplayName()));
						}
					}
				} else {
					if ($plugin->getEnabled()) {
						if ($request->checkCSRF() && $plugin->getCanDisable()) {
							$plugin->setEnabled(false);
							$user = $request->getUser();
							$notificationManager = new NotificationManager();
							$notificationManager->createTrivialNotification($user->getId(), NOTIFICATION_TYPE_PLUGIN_DISABLED, array('pluginName' => $plugin->getDisplayName()));
						}
					}
				}
			}
		}
		
		return new JSONMessage(true, $this->fetch($request));
	}
}

?>

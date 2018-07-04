<?php

/**
 * @file controllers/tab/settings/archiving/form/ArchivingForm.inc.php
 *
 * Copyright (c) 2014-2018 Simon Fraser University
 * Copyright (c) 2003-2018 John Willinsky
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
			'enablePortico' => 'bool'
		);

		$this->addCheck(new FormValidatorCSRF($this));

		parent::__construct($settings, 'controllers/tab/settings/archiving/form/archivingForm.tpl', $wizardMode);
	}

	/**
	 * @copydoc ContextSettingsForm::fetch()
	 */
	function fetch($request, $template = null, $display = false, $params = null) {
		$isPLNPluginInstalled = false;
		$isPLNPluginEnabled = false;
		$isPorticoPluginInstalled = false;

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

		// Get if Portico is installed
		$currentPorticoVersion = $versionDao->getCurrentVersion("plugins.importexport", "portico", true);
		if (isset($currentPorticoVersion)) {
			$isPorticoPluginInstalled = true;
		}

		$plnSettingsShowAction = $this->_getPLNPluginSettingsLinkAction($request);

		$params = array(
			'isPLNPluginInstalled' => $isPLNPluginInstalled,
			'isPLNPluginEnabled' => $isPLNPluginEnabled,
			'isPorticoPluginInstalled' => $isPorticoPluginInstalled,
			'plnSettingsShowAction' => $plnSettingsShowAction,
		);

		return parent::fetch($request, $template, $display, $params);
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

		$this->enablePlugin($request, 'plugins.generic', 'generic', 'pln', 'enablePln');
		$this->enablePlugin($request, 'plugins.importexport', 'importexport', 'portico', 'enablePortico');

		return new JSONMessage(true, $this->fetch($request));
	}

	/**
	 * Get a link action for PLN Plugin Settings.
	 * @param $request Request
	 * @return LinkAction
	 */
	function _getPLNPluginSettingsLinkAction($request) {
		$router = $request->getRouter();
		import('lib.pkp.classes.linkAction.request.AjaxModal');

		$ajaxModal = new AjaxModal(
			$router->url($request, null, 'grid.settings.plugins.SettingsPluginGridHandler', 'manage', null, array('verb' => 'settings', 'plugin' => 'plnplugin', 'category' => 'generic')),
			'PLN Plugin'
		);

		import('lib.pkp.classes.linkAction.LinkAction');
		$linkAction = new LinkAction(
			'pln-settings',
			$ajaxModal,
			__('manager.plugins.settings'),
			null
		);

		return $linkAction;
	}

	/**
	 * Enable plugin by using check box.
	 * @param $request Request
	 * @param $pluginType string
	 * @param $pluginCategory string
	 * @param $pluginName string
	 * @param $parameterName string
	 *
	 */
	function enablePlugin($request, $pluginType, $pluginCategory, $pluginName, $parameterName) {
		$versionDao = DAORegistry::getDAO('VersionDAO');
		$product = $versionDao->getCurrentVersion($pluginType, $pluginName, true);
		if (isset($product)) {
			$plugin = PluginRegistry::loadPlugin($pluginCategory, $pluginName);
			if ($plugin && is_object($plugin)) {
				$notification = null;

				if (isset($this->_data[$parameterName])) {
					if (!$plugin->getEnabled()) {
						if ($plugin->getCanEnable()) {
							$plugin->setEnabled(true);
							$notification = NOTIFICATION_TYPE_PLUGIN_ENABLED;
						}
					}
				} else {
					if ($plugin->getEnabled()) {
						if ($plugin->getCanDisable()) {
							$plugin->setEnabled(false);
							$notification = NOTIFICATION_TYPE_PLUGIN_DISABLED;
						}
					}
				}

				if (isset($notification)) {
					$user = $request->getUser();
					$notificationManager = new NotificationManager();
					$notificationManager->createTrivialNotification($user->getId(), $notification, array('pluginName' => $plugin->getDisplayName()));
				}
			}
		}
	}
}

?>
